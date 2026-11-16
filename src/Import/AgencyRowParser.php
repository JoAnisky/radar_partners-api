<?php

namespace App\Import;

use App\Enum\CompanyType;
use App\Enum\Scope;
use App\Enum\Zone;

/** Ligne du CSV « Prospection agences » (Agence, Ville, Zone, Priorité, Statut, Date contact, Prochaine action, Interlocuteur, Infos, Notes). */
final class AgencyRowParser
{
    private const WEB_TERMS = '/web|site|symfony|wordpress|laravel|logiciel|dev|applications?|mobile|e-commerce|prestashop|drupal|shopify/iu';

    /** @param array<string, string> $row */
    public function parse(array $row): ?AgencyRow
    {
        $name = trim($row['Agence'] ?? '');
        if ('' === $name) {
            return null;
        }

        $warnings = [];
        $cities = array_values(array_filter(array_map('trim', explode('/', $row['Ville'] ?? ''))));
        $city = $cities[0] ?? null;
        if (null !== $city && str_starts_with($city, 'À vérifier')) {
            $warnings[] = sprintf('Ville à vérifier : %s', $city);
            $city = null;
        }

        if ('' !== trim($row['Date contact'] ?? '') || '' !== trim($row['Interlocuteur'] ?? '')) {
            $warnings[] = 'Date de contact ou interlocuteur ignorés (non gérés par cet import)';
        }

        $zone = $this->zone($row['Zone'] ?? '');
        $info = trim($row['Infos (stack, taille)'] ?? '');

        return new AgencyRow(
            $name,
            $city,
            $zone,
            Zone::SwissRomande === $zone ? Scope::National : Scope::Local,
            $this->type($name.' '.$info),
            $this->notes($row, $info, \count($cities) > 1 ? implode(' / ', $cities) : null, $warnings),
            $warnings,
        );
    }

    private function zone(string $value): Zone
    {
        return match (true) {
            str_contains($value, 'Franche') => Zone::FrancheComte,
            str_contains($value, 'Bourgogne') => Zone::Bourgogne,
            str_contains($value, 'Suisse') => Zone::SwissRomande,
            default => Zone::Other,
        };
    }

    private function type(string $text): CompanyType
    {
        return match (true) {
            1 === preg_match(self::WEB_TERMS, $text) => CompanyType::WebAgency,
            1 === preg_match('/\bcom\b|communication|\bpub\b/iu', $text) => CompanyType::ComAgency,
            default => CompanyType::Unknown,
        };
    }

    /**
     * @param array<string, string> $row
     * @param list<string>          $warnings
     */
    private function notes(array $row, string $info, ?string $cities, array $warnings): ?string
    {
        $lines = array_filter([
            '' !== trim($row['Priorité'] ?? '') ? 'Priorité : '.trim($row['Priorité']) : null,
            '' !== $info ? 'Infos : '.$info : null,
            '' !== trim($row['Prochaine action'] ?? '') ? 'Prochaine action : '.trim($row['Prochaine action']) : null,
            null !== $cities ? 'Villes : '.$cities : null,
            ...array_filter($warnings, static fn (string $w): bool => str_starts_with($w, 'Ville à vérifier')),
            '' !== trim($row['Notes'] ?? '') ? trim($row['Notes']) : null,
        ]);

        return [] === $lines ? null : implode("\n", $lines);
    }
}
