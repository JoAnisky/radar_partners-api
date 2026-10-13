<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Interaction;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PipelineStatusResolver
{
    public function __construct(
        #[Autowire('%app.recontact_delay%')]
        private string $recontactDelay,
    ) {
    }

    /**
     * Statut et date de relance déduits de tout l'historique : la dernière interaction significative l'emporte.
     *
     * @param list<Interaction> $interactions
     *
     * @return array{status: PipelineStatus, recontactAfter: ?\DateTimeImmutable}
     */
    public function derive(array $interactions): array
    {
        usort($interactions, static fn (Interaction $a, Interaction $b): int => $b->getOccurredAt() <=> $a->getOccurredAt());

        foreach ($interactions as $interaction) {
            $status = self::statusFor($interaction->getOutcome());
            if (null !== $status) {
                return [
                    'status' => $status,
                    'recontactAfter' => PipelineStatus::OnHold === $status ? $this->onHoldUntil($interaction->getOccurredAt()) : null,
                ];
            }
        }

        return ['status' => [] === $interactions ? PipelineStatus::ToContact : PipelineStatus::Contacted, 'recontactAfter' => null];
    }

    /**
     * Recalcule le statut à partir de tout l'historique (import initial).
     *
     * @param list<Interaction> $interactions
     */
    public function apply(Company $company, array $interactions): void
    {
        $derived = $this->derive($interactions);
        $company->setStatus($derived['status']);
        $company->setRecontactAfter($derived['recontactAfter']);
    }

    /**
     * Après modification d'une interaction : le statut suit la nouvelle déduction seulement s'il suivait
     * déjà l'ancienne ; un statut posé à la main est conservé.
     *
     * @param array{status: PipelineStatus, recontactAfter: ?\DateTimeImmutable} $before
     * @param array{status: PipelineStatus, recontactAfter: ?\DateTimeImmutable} $after
     */
    public function followEdit(Company $company, array $before, array $after): void
    {
        if ($before != $after && $company->getStatus() === $before['status']) {
            $company->setStatus($after['status']);
            $company->setRecontactAfter($after['recontactAfter']);
        }
    }

    /**
     * Prend en compte une interaction saisie à la main : seule une interaction significative et la plus récente
     * change le statut, sinon un statut posé manuellement est conservé.
     *
     * @param list<Interaction> $previous interactions déjà enregistrées
     */
    public function register(Company $company, Interaction $new, array $previous): void
    {
        $status = self::statusFor($new->getOutcome());
        $isLatest = array_all($previous, static fn (Interaction $i): bool => $i->getOccurredAt() <= $new->getOccurredAt());

        if (null !== $status && $isLatest) {
            $this->setStatus($company, $status, $new->getOccurredAt());

            return;
        }

        if (PipelineStatus::ToContact === $company->getStatus()) {
            $company->setStatus(PipelineStatus::Contacted);
        }
    }

    public function onHoldUntil(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $from->add(new \DateInterval($this->recontactDelay));
    }

    private function setStatus(Company $company, PipelineStatus $status, \DateTimeImmutable $from): void
    {
        $company->setStatus($status);
        $company->setRecontactAfter(PipelineStatus::OnHold === $status ? $this->onHoldUntil($from) : null);
    }

    private static function statusFor(InteractionOutcome $outcome): ?PipelineStatus
    {
        return match ($outcome) {
            InteractionOutcome::NoFreelancePolicy => PipelineStatus::Excluded,
            InteractionOutcome::MeetingScheduled => PipelineStatus::Meeting,
            InteractionOutcome::JustHired, InteractionOutcome::NoNeed => PipelineStatus::OnHold,
            InteractionOutcome::Positive => PipelineStatus::FollowUp,
            default => null,
        };
    }
}
