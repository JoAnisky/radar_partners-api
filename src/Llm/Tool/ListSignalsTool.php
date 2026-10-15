<?php

namespace App\Llm\Tool;

use App\Api\SignalListQuery;
use App\Entity\Signal;
use App\Repository\SignalRepository;

final readonly class ListSignalsTool implements ChatToolInterface
{
    private const MAX_LIMIT = 20;

    public function __construct(private SignalRepository $signals)
    {
    }

    public function getName(): string
    {
        return 'list_signals';
    }

    public function getDescription(): string
    {
        return "Liste les dernières offres détectées (missions freelance, offres d'emploi), les plus récentes d'abord. toReview=true signale une offre dont le titre ne nomme aucune technologie.";
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'view' => ['type' => 'string', 'enum' => ['todo', 'archived'], 'description' => 'todo : offres à traiter (défaut) ; archived : offres écartées'],
                'limit' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats (10 par défaut, 20 au plus)'],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $archived = 'archived' === ($arguments['view'] ?? null);
        $limit = max(1, min(self::MAX_LIMIT, is_numeric($arguments['limit'] ?? null) ? (int) $arguments['limit'] : 10));

        ['items' => $items, 'total' => $total] = $this->signals->search(new SignalListQuery(unresolved: !$archived, dismissed: $archived, perPage: $limit));

        return [
            'total' => $total,
            'signals' => array_map(static function (Signal $signal): array {
                $extracted = $signal->getExtracted();

                return [
                    'title' => $signal->getTitle(),
                    'type' => $signal->getType()->value,
                    'source' => $signal->getSource(),
                    'url' => $signal->getUrl(),
                    'detectedAt' => $signal->getDetectedAt()->format(\DATE_ATOM),
                    'companyName' => $signal->getCompany()?->getName() ?? $extracted['companyName'] ?? null,
                    'location' => $extracted['location'] ?? $extracted['city'] ?? null,
                    'remote' => $extracted['remote'] ?? false,
                    'dailyRate' => $extracted['dailyRate'] ?? null,
                    'toReview' => $extracted['toReview'] ?? false,
                ];
            }, $items),
        ];
    }
}
