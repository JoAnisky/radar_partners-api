<?php

namespace App\Api;

use App\Enum\CompanyType;
use App\Enum\Scope;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CompanyInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $name,
        public Scope $scope = Scope::National,
        public CompanyType $type = CompanyType::Unknown,
        #[Assert\Length(max: 255)]
        public ?string $website = null,
        #[Assert\Length(max: 255)]
        public ?string $city = null,
        #[Assert\Length(max: 10000)]
        public ?string $notes = null,
    ) {
    }
}
