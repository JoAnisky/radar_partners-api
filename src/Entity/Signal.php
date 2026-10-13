<?php

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\SignalType;
use App\Repository\SignalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignalRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Signal
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Company $company = null;

    #[ORM\Column(length: 30, enumType: SignalType::class)]
    private SignalType $type;

    #[ORM\Column(length: 50)]
    private string $source;

    #[ORM\Column(length: 64, unique: true)]
    private string $fingerprint;

    #[ORM\Column(nullable: true)]
    private ?string $url = null;

    #[ORM\Column(nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawContent = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $extracted = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $detectedAt;

    public function __construct(SignalType $type, string $source, string $fingerprint)
    {
        $this->type = $type;
        $this->source = $source;
        $this->fingerprint = $fingerprint;
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getType(): SignalType
    {
        return $this->type;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }

    public function setRawContent(?string $rawContent): static
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getExtracted(): array
    {
        return $this->extracted;
    }

    /** @param array<string, mixed> $extracted */
    public function setExtracted(array $extracted): static
    {
        $this->extracted = $extracted;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTimeImmutable $detectedAt): static
    {
        $this->detectedAt = $detectedAt;

        return $this;
    }
}
