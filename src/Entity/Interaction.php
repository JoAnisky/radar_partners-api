<?php

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Repository\InteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InteractionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Interaction
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 20, enumType: InteractionChannel::class)]
    private InteractionChannel $channel;

    #[ORM\Column(nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 30, enumType: InteractionOutcome::class)]
    private InteractionOutcome $outcome = InteractionOutcome::Other;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextActionAt = null;

    public function __construct(Company $company, InteractionChannel $channel, \DateTimeImmutable $occurredAt)
    {
        $this->company = $company;
        $this->channel = $channel;
        $this->occurredAt = $occurredAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getChannel(): InteractionChannel
    {
        return $this->channel;
    }

    public function setChannel(InteractionChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getOutcome(): InteractionOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(InteractionOutcome $outcome): static
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getNextActionAt(): ?\DateTimeImmutable
    {
        return $this->nextActionAt;
    }

    public function setNextActionAt(?\DateTimeImmutable $nextActionAt): static
    {
        $this->nextActionAt = $nextActionAt;

        return $this;
    }
}
