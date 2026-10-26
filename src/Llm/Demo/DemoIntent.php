<?php

namespace App\Llm\Demo;

/** Les cinq questions que l'assistant de démo sait traiter. */
enum DemoIntent
{
    case Draft;
    case Summary;
    case Overdue;
    case Signals;
    case Priorities;

    /** @param string $question question normalisée (minuscules, sans accents) */
    public static function detect(string $question): ?self
    {
        return match (true) {
            1 === preg_match('/redig|ecri[st]|mail|message|linkedin|script|approche/', $question) => self::Draft,
            1 === preg_match('/resum|fiche|ou en suis|point sur/', $question) => self::Summary,
            1 === preg_match('/relanc|retard/', $question) => self::Overdue,
            1 === preg_match('/offre|signal|mission|annonce/', $question) => self::Signals,
            1 === preg_match('/priorit|contacter|demarch|en premier|commencer/', $question) => self::Priorities,
            default => null,
        };
    }

    public function needsCompany(): bool
    {
        return self::Draft === $this || self::Summary === $this;
    }
}
