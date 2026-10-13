<?php

namespace App\Command;

use App\Referential\ReferentialClient;
use App\Referential\ReferentialImporter;
use App\Referential\ReferentialImportOutcome;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:referential:import', description: "Importe le référentiel local (API Recherche d'entreprises) et rapproche les entreprises existantes")]
final readonly class ImportReferentialCommand
{
    private const NAF = '62.01Z,62.02A,62.02B,62.03Z,62.09Z,63.11Z,63.12Z,73.11Z,58.29A,58.29C';
    private const HEADCOUNT = '01,02,03,11,12,21,22,31,32,41,42,51,52,53';
    private const EPCI_GRAND_BESANCON = '242500361';

    public function __construct(
        private ReferentialClient $client,
        private ReferentialImporter $importer,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Code EPCI (par défaut : Grand Besançon Métropole)')] string $epci = self::EPCI_GRAND_BESANCON,
        #[Option(description: 'Codes NAF séparés par des virgules')] string $naf = self::NAF,
        #[Option(description: "Codes de tranche d'effectif INSEE (par défaut : 1 salarié et plus)")] string $headcount = self::HEADCOUNT,
        #[Option(description: 'Nombre maximum de résultats')] int $limit = 500,
        #[Option(description: 'Simule sans écrire en base')] bool $dryRun = false,
    ): int {
        $filters = ['epci' => $epci, 'activite_principale' => $naf, 'tranche_effectif_salarie' => $headcount];
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        $counts = array_fill_keys(array_column(ReferentialImportOutcome::cases(), 'name'), 0);
        $enriched = $ambiguous = [];

        try {
            foreach ($this->client->search($filters, null, $limit) as $source) {
                $result = $this->importer->import($source);
                $this->em->flush();
                ++$counts[$result->outcome->name];

                if (ReferentialImportOutcome::Enriched === $result->outcome) {
                    $enriched[] = [$result->company?->getName(), $source->name, $source->siren];
                } elseif (ReferentialImportOutcome::Ambiguous === $result->outcome) {
                    $ambiguous[] = [$source->name, $source->siren, $result->note];
                }
            }

            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        if ([] !== $enriched) {
            $io->section('Entreprises du tableur rapprochées');
            $io->table(['Tableur', 'Référentiel', 'SIREN'], $enriched);
        }

        if ([] !== $ambiguous) {
            $io->section('Rapprochements ambigus (rien créé ni modifié)');
            $io->table(['Référentiel', 'SIREN', 'Remarque'], $ambiguous);
        }

        $unmatched = array_map(static fn ($c): array => [$c->getName()], $this->importer->unmatched());
        if ([] !== $unmatched) {
            $io->section('Entreprises du tableur sans correspondance (hors filtres ou nom différent)');
            $io->table(['Nom'], $unmatched);
        }

        $io->success(sprintf(
            '%d créée(s), %d rapprochée(s), %d déjà connue(s), %d ambiguë(s)%s.',
            $counts['Created'],
            $counts['Enriched'],
            $counts['AlreadyKnown'],
            $counts['Ambiguous'],
            $dryRun ? ' (dry-run, rien écrit)' : '',
        ));

        return 0;
    }
}
