<?php

namespace App\Referential;

/** Compare un nom du tableur à une dénomination légale (avec ses noms commerciaux entre parenthèses). */
final class CompanyNameMatcher
{
    private const LEGAL_FORMS = ['sas', 'sasu', 'sarl', 'eurl', 'sa'];
    private const GENERIC_WORDS = ['france', 'groupe', 'group'];
    private const MIN_CONTAINED_LENGTH = 5;

    public static function matches(string $a, string $b): bool
    {
        foreach (self::variants($a) as $x) {
            foreach (self::variants($b) as $y) {
                if (str_replace(' ', '', $x) === str_replace(' ', '', $y)) {
                    return true;
                }

                [$short, $long] = \strlen($x) <= \strlen($y) ? [$x, $y] : [$y, $x];
                if (\strlen($short) >= self::MIN_CONTAINED_LENGTH
                    && !\in_array($short, self::GENERIC_WORDS, true)
                    && str_contains(' '.$long.' ', ' '.$short.' ')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> noms normalisés (mots séparés par un espace) : nom principal puis alias entre parenthèses */
    public static function variants(string $name): array
    {
        $raw = [(string) preg_replace('/\s*\(.*$/u', '', $name)];
        if (preg_match_all('/\(([^)]*)\)/u', $name, $matches)) {
            foreach ($matches[1] as $group) {
                array_push($raw, ...explode(',', $group));
            }
        }

        $variants = [];
        foreach ($raw as $item) {
            $normalized = self::normalize($item);
            if ('' !== $normalized) {
                $variants[] = $normalized;
            }
        }

        return array_values(array_unique($variants));
    }

    public static function normalize(string $name): string
    {
        $name = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')?->transliterate($name) ?: $name;
        $tokens = preg_split('/[^a-z0-9]+/', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_diff($tokens, self::LEGAL_FORMS));
    }
}
