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
        return 'Liste les entreprises de la base, classées par score décroissant. Sans paramètre, renvoie les mieux classées : à utiliser pour les demandes générales (« les plus intéressantes », « les ESN », « les agences »), car le type d\'entreprise ne se filtre pas. Les partenaires sont exclus sauf si on demande le statut PARTNER.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Uniquement un nom, une ville ou un SIREN ; un sigle (BWA) retrouve « Business Web Agence ». Ne pas y mettre un type d\'entreprise (« agence », « ESN ») ni plusieurs mots-clés (facultatif)'],
                'status' => ['type' => 'string', 'enum' => array_column(PipelineStatus::cases(), 'value'), 'description' => 'Statut dans le pipeline, à n\'indiquer que si l\'utilisateur le demande (facultatif)'],
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
