<?php

namespace App\Api;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Entity\Signal;

final class CompanyPresenter
{
    /** @return array<string, mixed> */
    public function summary(Company $company, ?\DateTimeImmutable $lastInteractionAt = null): array
    {
        return [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'siren' => $company->getSiren(),
            'website' => $company->getWebsite(),
            'city' => $company->getCity(),
            'postalCode' => $company->getPostalCode(),
            'phone' => $company->getPhone(),
            'nafCode' => $company->getNafCode(),
            'headcountRange' => $company->getHeadcountRange(),
            'type' => $company->getType()->value,
            'scope' => $company->getScope()->value,
            'status' => $company->getStatus()->value,
            'score' => $company->getScore(),
            'flags' => $company->getFlags(),
            'recontactAfter' => self::date($company->getRecontactAfter()),
            'lastInteractionAt' => self::date($lastInteractionAt),
            'archived' => $company->isArchived(),
        ];
    }

    /**
     * @param list<Interaction> $interactions
     * @param list<Signal>      $signals
     *
     * @return array<string, mixed>
     */
    public function detail(Company $company, array $interactions, array $signals): array
    {
        return $this->summary($company, [] === $interactions ? null : $interactions[0]->getOccurredAt()) + [
            'address' => $company->getAddress(),
            'email' => $company->getEmail(),
            'parentId' => $company->getParent()?->getId(),
            'scoreExplanation' => $company->getScoreExplanation(),
            'notes' => $company->getNotes(),
            'createdAt' => self::date($company->getCreatedAt()),
            'contacts' => array_map($this->contact(...), $company->getContacts()->toArray()),
            'interactions' => array_map($this->interaction(...), $interactions),
            'signals' => array_map($this->signal(...), $signals),
        ];
    }

    /** @return array<string, mixed> */
    private function contact(Contact $contact): array
    {
        return [
            'id' => $contact->getId(),
            'fullName' => $contact->getFullName(),
            'role' => $contact->getRole(),
            'phone' => $contact->getPhone(),
            'email' => $contact->getEmail(),
            'linkedinUrl' => $contact->getLinkedinUrl(),
            'notes' => $contact->getNotes(),
        ];
    }

    /** @return array<string, mixed> */
    public function interaction(Interaction $interaction): array
    {
        return [
            'id' => $interaction->getId(),
            'contactId' => $interaction->getContact()?->getId(),
            'occurredAt' => self::date($interaction->getOccurredAt()),
            'channel' => $interaction->getChannel()->value,
            'origin' => $interaction->getOrigin(),
            'outcome' => $interaction->getOutcome()->value,
            'notes' => $interaction->getNotes(),
            'nextActionAt' => self::date($interaction->getNextActionAt()),
        ];
    }

    /** @return array<string, mixed> */
    public function signal(Signal $signal): array
    {
        return [
            'id' => $signal->getId(),
            'companyId' => $signal->getCompany()?->getId(),
            'companyName' => $signal->getCompany()?->getName(),
            'type' => $signal->getType()->value,
            'source' => $signal->getSource(),
            'title' => $signal->getTitle(),
            'url' => $signal->getUrl(),
            'detectedAt' => self::date($signal->getDetectedAt()),
            'publishedAt' => self::date($signal->getPublishedAt()),
            'extracted' => $signal->getExtracted(),
        ];
    }

    private static function date(?\DateTimeInterface $date): ?string
    {
        return $date?->format(\DATE_ATOM);
    }
}
