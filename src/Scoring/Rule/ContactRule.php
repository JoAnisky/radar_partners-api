<?php

namespace App\Scoring\Rule;

use App\Entity\Company;
use App\Entity\Contact;
use App\Scoring\ScoreContribution;
use App\Scoring\ScoringContext;
use App\Scoring\ScoringRuleInterface;

/** Un interlocuteur joignable directement facilite l'approche. */
final class ContactRule implements ScoringRuleInterface
{
    private const POINTS = 5;

    public function evaluate(Company $company, ScoringContext $context): array
    {
        $reachable = $company->getContacts()->exists(
            static fn (int $key, Contact $c): bool => null !== $c->getPhone() || null !== $c->getEmail(),
        );

        return $reachable ? [new ScoreContribution(self::POINTS, 'Contact direct identifié')] : [];
    }
}
