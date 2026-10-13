<?php

namespace App\Api;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactListQuery
{
    public function __construct(
        public ?string $q = null,
        #[Assert\Choice(choices: ['fullName', 'company', 'lastInteractionAt', 'createdAt'])]
        public string $sort = 'fullName',
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public string $direction = 'asc',
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 25,
    ) {
    }
}
