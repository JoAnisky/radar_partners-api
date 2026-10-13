<?php

namespace App\Signal;

/** Repère une mention explicite de télétravail dans le titre ou le contenu d'une offre. */
final class RemoteDetector
{
    public static function isRemote(string $text): bool
    {
        $folded = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')?->transliterate($text) ?: $text;

        return 1 === preg_match('/\b(remote|teletravail|distanciel|home office)\b/', $folded);
    }
}
