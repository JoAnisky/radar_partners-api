<?php

namespace App\Api;

use App\Enum\PipelineStatus;
use App\Enum\Scope;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CompanyListQuery
{
    public function __construct(
        public ?string $q = null,
        public ?PipelineStatus $status = null,
        public ?Scope $scope = null,
        /** Entreprises à relancer : date de relance dépassée ou prochaine action échue. */
        public bool $due = false,
        public bool $archived = false,
        /** Exclut les partenaires, sauf si un statut précis est demandé. */
        public bool $hidePartners = false,
        #[Assert\Choice(choices: ['name', 'score', 'recontactAfter', 'lastInteractionAt', 'createdAt'])]
        public string $sort = 'name',
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'asc',
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 25,
    ) {
    }
}
