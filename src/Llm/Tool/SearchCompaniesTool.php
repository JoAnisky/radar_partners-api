<?php

namespace App\Llm\Tool;

use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Entity\Company;
use App\Enum\PipelineStatus;
use App\Repository\CompanyRepository;

final readonly class SearchCompaniesTool implements ChatToolInterface
{
    private const MAX_LIMIT = 20;

    public function __construct(private CompanyRepository $companies, private CompanyPresenter $presenter)
    {
    }

    public function getName(): string
    {
        return 'search_companies';
    }

    public function getDescription(): string
    {
        return 'Recherche des entreprises dans la base, classées par score décroissant. Les partenaires sont exclus sauf si on demande le statut PARTNER.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Texte à chercher dans le nom, la ville ou le SIREN ; un sigle (BWA) retrouve « Business Web Agence » (facultatif)'],
                'status' => ['type' => 'string', 'enum' => array_column(PipelineStatus::cases(), 'value'), 'description' => 'Statut dans le pipeline (facultatif)'],
                'due' => ['type' => 'boolean', 'description' => 'true pour ne garder que les entreprises à relancer'],
                'limit' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats (10 par défaut, 20 au plus)'],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $query = \is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $limit = max(1, min(self::MAX_LIMIT, is_numeric($arguments['limit'] ?? null) ? (int) $arguments['limit'] : 10));

        ['items' => $items, 'total' => $total] = $this->companies->search(new CompanyListQuery(
            q: '' === $query ? null : $query,
            status: \is_string($arguments['status'] ?? null) ? PipelineStatus::tryFrom($arguments['status']) : null,
            due: true === ($arguments['due'] ?? false),
            hidePartners: true,
            sort: 'score',
            direction: 'desc',
            perPage: $limit,
        ), new \DateTimeImmutable());
        $lastDates = $this->companies->lastInteractionDates($items);

        return [
            'total' => $total,
            'companies' => array_map(fn (Company $c): array => $this->presenter->summary($c, $lastDates[$c->getId()] ?? null), $items),
        ];
    }
}
