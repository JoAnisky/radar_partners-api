<?php

namespace App\Llm\Tool;

use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Entity\Company;
use App\Entity\Interaction;
use App\Repository\CompanyRepository;
use App\Repository\InteractionRepository;
use App\Repository\SignalRepository;
use App\Util\NoteDateExtractor;

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
        return "Donne la fiche complète d'une entreprise : informations, score expliqué, notes, contacts, dernières interactions (avec daysAgo, le nombre de jours écoulés) et offres rattachées, plus les retards déjà calculés (daysSinceLastInteraction, nextActionOverdueByDays, recontactOverdueByDays) les dates écrites dans les notes (datesInNotes, datesInCompanyNotes) avec leur position par rapport à aujourd'hui, et des alertes (alerts) à relayer à l'utilisateur. Utile avant de rédiger un message.";
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
        // « +45 1 offre… » est lu comme un nombre d'offres : on précise que ce sont des points.
        if (\is_string($detail['scoreExplanation'])) {
            $detail['scoreExplanation'] = preg_replace('/^([+-]\d+) /m', '$1 points : ', $detail['scoreExplanation']);
        }

        // Les petits modèles comparent mal les dates : on leur donne les écarts déjà calculés.
        $latest = $interactions[0] ?? null;
        $detail['daysSinceLastInteraction'] = null === $latest ? null : self::daysBetween($latest->getOccurredAt(), $now);
        $detail['nextActionOverdueByDays'] = self::overdueDays($latest?->getNextActionAt(), $now);
        $detail['recontactOverdueByDays'] = self::overdueDays($company->getRecontactAfter(), $now);
        $detail['interactions'] = array_map(
            fn (Interaction $interaction): array => [
                'daysAgo' => self::daysBetween($interaction->getOccurredAt(), $now),
                'datesInNotes' => self::datesIn($interaction->getNotes(), $now),
            ] + $this->presenter->interaction($interaction),
            $interactions,
        );
        $detail['datesInCompanyNotes'] = self::datesIn($company->getNotes(), $now);
        $detail['alerts'] = null === $latest ? [] : self::staleAppointments($latest, $now);

        return $detail;
    }

    /**
     * Rendez-vous noté dans la dernière interaction mais déjà passé, sans suite saisie depuis.
     *
     * @return list<string>
     */
    private static function staleAppointments(Interaction $latest, \DateTimeImmutable $now): array
    {
        $alerts = [];
        foreach (NoteDateExtractor::extract($latest->getNotes() ?? '') as $found) {
            $days = self::daysBetween($found['date'], $now);
            if ($found['date'] > $latest->getOccurredAt() && $days > 0) {
                $alerts[] = sprintf(
                    "ATTENTION : la date du %s notée dans la dernière interaction est PASSÉE depuis %d jour(s) et rien n'a été saisi depuis. Ne la présente pas comme à venir : dis qu'elle est passée et demande à l'utilisateur ce qui s'est passé.",
                    $found['date']->format('d/m/Y'),
                    $days,
                );
            }
        }

        return $alerts;
    }

    /**
     * Dates écrites dans un texte libre, avec leur position par rapport à aujourd'hui.
     *
     * @return list<array{date: string, when: string}>
     */
    private static function datesIn(?string $text, \DateTimeImmutable $now): array
    {
        return array_map(static function (array $found) use ($now): array {
            $days = self::daysBetween($found['date'], $now);

            return ['date' => $found['date']->format('Y-m-d'), 'when' => match (true) {
                0 === $days => "aujourd'hui",
                $days > 0 => sprintf('passée, il y a %d jour(s)', $days),
                default => sprintf('à venir, dans %d jour(s)', -$days),
            }];
        }, NoteDateExtractor::extract($text ?? ''));
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
