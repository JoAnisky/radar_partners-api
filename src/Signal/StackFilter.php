<?php

namespace App\Signal;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Classe un signal d'après son titre : stack visée, autre techno connue (écarté), ou aucune techno nommée (à vérifier). */
final readonly class StackFilter
{
    /**
     * @param list<string> $keywords
     * @param list<string> $otherKeywords
     */
    public function __construct(
        #[Autowire('%app.stack_keywords%')]
        private array $keywords,
        #[Autowire('%app.other_stack_keywords%')]
        private array $otherKeywords = [],
    ) {
    }

    public function classify(RawSignal $signal): StackMatch
    {
        $title = ' '.self::words($signal->title).' ';

        return match (true) {
            self::containsAny($title, $this->keywords) => StackMatch::Match,
            self::containsAny($title, $this->otherKeywords) => StackMatch::Excluded,
            default => StackMatch::Review,
        };
    }

    /** @param list<string> $keywords */
    private static function containsAny(string $title, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($title, ' '.self::words($keyword).' ')) {
                return true;
            }
        }

        return false;
    }

    private static function words(string $text): string
    {
        $text = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')?->transliterate($text) ?: $text;

        return implode(' ', preg_split('/[^a-z0-9]+/', $text, -1, \PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
