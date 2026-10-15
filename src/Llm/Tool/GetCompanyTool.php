<?php

namespace App\Llm\Tool;

use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Entity\Company;
use App\Entity\Interaction;
use App\Repository\CompanyRepository;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;

final readonly class GetCompanyTool implements ChatToolInterface
{
    private const MAX_INTERACTIONS = 10;

    public function __construct(
        private CompanyRepository $companies,
        private InteractionRepository $interactions,
        private SignalRepository $signals,
        private CompanyPresenter $presenter,
    ) {
    }

    public function getName(): string
    {
        return 'get_company';
    }

    public function getDescription(): string
    {
        return "Donne la fiche complète d'une entreprise : informations, score expliqué, notes, contacts, dernières interactions (avec daysAgo, le nombre de jours écoulés) et offres rattachées, plus les retards déjà calculés (daysSinceLastInteraction, nextActionOverdueByDays, recontactOverdueByDays). Utile avant de rédiger un message.";
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Identifiant de l'entreprise (si connu)"],
                'name' => ['type' => 'string', 'description' => "Nom ou sigle de l'entreprise (si l'identifiant est inconnu)"],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $company = $this->find($arguments);
        if (null === $company) {
            return ['error' => 'Entreprise introuvable.'];
        }

        $now = new \DateTimeImmutable();
        $interactions = \array_slice($this->interactions->findByCompany($company), 0, self::MAX_INTERACTIONS);
        $detail = $this->presenter->detail($company, $interactions, $this->signals->findByCompany($company));
        unset($detail['parentId'], $detail['createdAt'], $detail['archived']);

        // Les petits modèles comparent mal les dates : on leur donne les écarts déjà calculés.
        $latest = $interactions[0] ?? null;
        $detail['daysSinceLastInteraction'] = null === $latest ? null : self::daysBetween($latest->getOccurredAt(), $now);
        $detail['nextActionOverdueByDays'] = self::overdueDays($latest?->getNextActionAt(), $now);
        $detail['recontactOverdueByDays'] = self::overdueDays($company->getRecontactAfter(), $now);
        $detail['interactions'] = array_map(
            fn (Interaction $interaction): array => ['daysAgo' => self::daysBetween($interaction->getOccurredAt(), $now)] + $this->presenter->interaction($interaction),
            $interactions,
        );

        return $detail;
    }

    private static function daysBetween(\DateTimeInterface $date, \DateTimeImmutable $now): int
    {
        $diff = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0)->diff($now->setTime(0, 0));

        return $diff->invert ? -(int) $diff->days : (int) $diff->days;
    }

    /** Jours de retard d'une échéance passée, null si elle est absente ou à venir. */
    private static function overdueDays(?\DateTimeInterface $due, \DateTimeImmutable $now): ?int
    {
        $days = null === $due ? null : self::daysBetween($due, $now);

        return null !== $days && $days > 0 ? $days : null;
    }

    /** @param array<string, mixed> $arguments */
    private function find(array $arguments): ?Company
    {
        if (isset($arguments['id']) && is_numeric($arguments['id'])) {
            return $this->companies->find((int) $arguments['id']);
        }

        $name = \is_string($arguments['name'] ?? null) ? trim($arguments['name']) : '';
        if ('' === $name) {
            return null;
        }

        return $this->companies->findOneByName($name)
            ?? $this->companies->search(new CompanyListQuery(q: $name, sort: 'score', direction: 'desc', perPage: 1), new \DateTimeImmutable())['items'][0] ?? null;
    }
}
