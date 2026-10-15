<?php

namespace App\Util;

/** Repère les dates complètes écrites dans un texte libre (« RDV le 17.09.2026 », « 3 octobre 2026 »). */
final class NoteDateExtractor
{
    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    /** @return list<array{text: string, date: \DateTimeImmutable}> dates distinctes, dans l'ordre du texte */
    public static function extract(string $text): array
    {
        $plain = self::unaccent($text);
        $found = [];

        // Chaque motif indique la position de l'année, du mois et du jour dans ses groupes.
        $patterns = [
            ['/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/', 1, 2, 3],
            ['/(?<!\d)(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})(?!\d)/', 3, 2, 1],
            ['/(?<!\d)(\d{1,2})(?:er)?\s+('.implode('|', array_keys(self::MONTHS)).')\s+(\d{4})(?!\d)/i', 3, 2, 1],
        ];

        foreach ($patterns as [$regex, $yearAt, $monthAt, $dayAt]) {
            preg_match_all($regex, $plain, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
            foreach ($matches as $match) {
                $month = $match[$monthAt][0];
                $monthNumber = ctype_digit($month) ? (int) $month : (self::MONTHS[strtolower($month)] ?? 0);
                $year = (int) $match[$yearAt][0];
                $day = (int) $match[$dayAt][0];
                if (!checkdate($monthNumber, $day, $year)) {
                    continue;
                }
                $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $monthNumber, $day));
                $found[$date->format('Y-m-d')] ??= ['offset' => $match[0][1], 'text' => $match[0][0], 'date' => $date];
            }
        }

        uasort($found, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        return array_values(array_map(static fn (array $item): array => ['text' => $item['text'], 'date' => $item['date']], $found));
    }

    /** Retire les accents courants du français (les positions ne servent qu'à ordonner les dates). */
    private static function unaccent(string $text): string
    {
        return strtr($text, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'û' => 'u', 'ù' => 'u', 'à' => 'a', 'â' => 'a', 'ô' => 'o', 'É' => 'E']);
    }
}
