<?php

namespace App\Command;

use App\Import\AgencyImporter;
use App\Import\AgencyRowParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:agencies', description: 'Importe le CSV de prospection d\'agences (complète les champs vides des entreprises existantes)')]
final readonly class ImportAgenciesCommand
{
    public function __construct(
        private AgencyRowParser $parser,
        private AgencyImporter $importer,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Fichier .csv')] string $file,
        #[Option(description: 'Simule sans écrire en base')] bool $dryRun = false,
    ): int {
        $handle = is_file($file) ? fopen($file, 'r') : false;
        if (false === $handle) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return 1;
        }

        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'ambiguous' => 0];
        $report = [];

        try {
            $header = array_map(strval(...), fgetcsv($handle, escape: '') ?: []);
            $header = array_map(static fn (string $h): string => ltrim($h, "\u{FEFF}"), $header);
            while ([] !== $header && false !== ($values = fgetcsv($handle, escape: ''))) {
                $row = $this->parser->parse(array_combine($header, array_map(strval(...), array_pad($values, \count($header), ''))));
                if (null === $row) {
                    continue;
                }

                [$outcome, $detail] = $this->importer->import($row);
                ++$counts[$outcome];
                $report[] = [$row->name, $outcome, $detail];
                foreach ($row->warnings as $warning) {
                    $report[] = [$row->name, 'à vérifier', $warning];
                }
            }

            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        $io->table(['Agence', 'Résultat', 'Détail'], array_filter($report, static fn (array $r): bool => 'created' !== $r[1]));
        $io->success(sprintf(
            '%d créée(s), %d complétée(s), %d inchangée(s), %d ambiguë(s)%s.',
            $counts['created'],
            $counts['updated'],
            $counts['unchanged'],
            $counts['ambiguous'],
            $dryRun ? ' (dry-run, rien écrit)' : '',
        ));

        return 0;
    }
}
