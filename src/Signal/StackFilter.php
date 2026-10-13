<?php

namespace App\Signal;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Retient les signaux dont le titre mentionne un mot-clé de la stack. */
final readonly class StackFilter
{
    /** @param list<string> $keywords */
    public function __construct(
        #[Autowire('%app.stack_keywords%')]
        private array $keywords,
    ) {
    }

    public function matches(RawSignal $signal): bool
    {
        $title = ' '.self::words($signal->title).' ';

        foreach ($this->keywords as $keyword) {
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
