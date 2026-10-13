<?php

namespace App\Command;

use App\Referential\ReferentialClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:referential:search', description: "Interroge l'API Recherche d'entreprises (lecture seule)")]
final readonly class SearchReferentialCommand
{
    public function __construct(private ReferentialClient $client)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Texte libre (3 caractères minimum)')] ?string $query = null,
        #[Option(description: 'Codes NAF, séparés par des virgules (ex. 62.01Z,73.11Z)')] ?string $naf = null,
        #[Option(description: 'Départements (ex. 25,70)')] ?string $department = null,
        #[Option(description: 'Codes postaux')] ?string $postalCode = null,
        #[Option(description: "Codes de tranche d'effectif INSEE (ex. 03,11,12)")] ?string $headcount = null,
        #[Option(description: 'Nombre maximum de résultats')] int $limit = 25,
    ): int {
        $filters = array_filter([
            'activite_principale' => $naf,
            'departement' => $department,
            'code_postal' => $postalCode,
            'tranche_effectif_salarie' => $headcount,
        ]);

        if (null === $query && [] === $filters) {
            $io->error('Indique au moins une recherche ou un filtre.');

            return 1;
        }

        $rows = [];
        foreach ($this->client->search($filters, $query, $limit) as $company) {
            $local = array_map(
                static fn ($e) => trim(($e->postalCode ?? '').' '.($e->city ?? '')),
                $company->establishments,
            );
            $rows[] = [
                $company->siren,
                mb_strimwidth($company->name, 0, 40, '…'),
                $company->nafCode,
                $company->headcountRange,
                $company->headOffice?->city,
                implode(', ', \array_slice($local, 0, 2)),
            ];
        }

        $io->table(['SIREN', 'Nom', 'NAF', 'Effectif', 'Siège', 'Établissements filtrés'], $rows);
        $io->success(sprintf('%d résultat(s).', \count($rows)));

        return 0;
    }
}
