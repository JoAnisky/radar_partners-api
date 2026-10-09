<?php

namespace App\Entity;

use App\Entity\Trait\Timestampable;
use App\Enum\CompanyType;
use App\Enum\PipelineStatus;
use App\Enum\Scope;
use App\Repository\CompanyRepository;
use App\Util\PhoneNormalizer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Company
{
    use Timestampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 9, unique: true, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?string $website = null;

    #[ORM\Column(nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $nafCode = null;

    #[ORM\Column(nullable: true)]
    private ?string $headcountRange = null;

    #[ORM\Column(length: 20, enumType: CompanyType::class)]
    private CompanyType $type = CompanyType::Unknown;

    #[ORM\Column(length: 20, enumType: Scope::class)]
    private Scope $scope = Scope::Local;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(length: 20, enumType: PipelineStatus::class)]
    private PipelineStatus $status = PipelineStatus::ToContact;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recontactAfter = null;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $scoreExplanation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $flags = [];

    /** @var Collection<int, Contact> */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'company')]
    private Collection $contacts;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->contacts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = PhoneNormalizer::toE164($phone);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNafCode(): ?string
    {
        return $this->nafCode;
    }

    public function setNafCode(?string $nafCode): static
    {
        $this->nafCode = $nafCode;

        return $this;
    }

    public function getHeadcountRange(): ?string
    {
        return $this->headcountRange;
    }

    public function setHeadcountRange(?string $headcountRange): static
    {
        $this->headcountRange = $headcountRange;

        return $this;
    }

    public function getType(): CompanyType
    {
        return $this->type;
    }

    public function setType(CompanyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }

    public function setScope(Scope $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getStatus(): PipelineStatus
    {
        return $this->status;
    }

    public function setStatus(PipelineStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRecontactAfter(): ?\DateTimeImmutable
    {
        return $this->recontactAfter;
    }

    public function setRecontactAfter(?\DateTimeImmutable $recontactAfter): static
    {
        $this->recontactAfter = $recontactAfter;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = max(0, min(100, $score));

        return $this;
    }

    public function getScoreExplanation(): ?string
    {
        return $this->scoreExplanation;
    }

    public function setScoreExplanation(?string $scoreExplanation): static
    {
        $this->scoreExplanation = $scoreExplanation;

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

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function archive(): static
    {
        $this->archivedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function unarchive(): static
    {
        $this->archivedAt = null;

        return $this;
    }

    /** @return list<string> */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /** @param list<string> $flags */
    public function setFlags(array $flags): static
    {
        $this->flags = array_values(array_unique($flags));

        return $this;
    }

    /** @return Collection<int, Contact> */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
        }

        return $this;
    }
}
