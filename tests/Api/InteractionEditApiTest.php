<?php

namespace App\Tests\Api;

use App\Entity\Contact;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;

final class InteractionEditApiTest extends ApiTestCase
{
    private function uri(?int $interactionId): string
    {
        return sprintf('/api/interactions/%s', $interactionId);
    }

    public function testEditsFieldsAndKeepsTheOthers(): void
    {
        $company = $this->company('Acme');
        $interaction = $this->interaction($company, '2026-10-01')->setNotes('Avant')->setOrigin('Apéro');
        $this->em->flush();

        [$status, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['notes' => 'Après', 'channel' => 'EMAIL']);

        self::assertSame(200, $status);
        self::assertSame('Après', self::at($body, 'interaction.notes'));
        self::assertSame('EMAIL', self::at($body, 'interaction.channel'));
        self::assertSame('Apéro', self::at($body, 'interaction.origin'));
        self::assertStringStartsWith('2026-10-01', self::str($body, 'interaction.occurredAt'));
    }

    public function testClearsOptionalFieldsWithNull(): void
    {
        $company = $this->company('Acme');
        $interaction = $this->interaction($company, '2026-10-01', '2026-10-15')->setNotes('Texte')->setOrigin('Origine');
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['notes' => null, 'origin' => '  ', 'nextActionAt' => null]);

        self::assertNull(self::at($body, 'interaction.notes'));
        self::assertNull(self::at($body, 'interaction.origin'));
        self::assertNull(self::at($body, 'interaction.nextActionAt'));
    }

    public function testStatusFollowsTheNewOutcomeWhenItWasDerived(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::Contacted);
        $interaction = $this->interaction($company, '2026-10-01');
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['outcome' => 'NO_NEED']);

        self::assertSame('ON_HOLD', self::at($body, 'company.status'));
        self::assertStringStartsWith('2027-04-01', self::str($body, 'company.recontactAfter'));
    }

    public function testStatusFallsBackWhenTheSignificantOutcomeIsRemoved(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::Excluded);
        $interaction = $this->interaction($company, '2026-10-01', null, InteractionOutcome::NoFreelancePolicy);
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['outcome' => 'NO_ANSWER']);

        self::assertSame('CONTACTED', self::at($body, 'company.status'));
        self::assertNull(self::at($body, 'company.recontactAfter'));
    }

    public function testManualStatusIsKept(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::Partner);
        $interaction = $this->interaction($company, '2026-10-01');
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['outcome' => 'NO_NEED']);

        self::assertSame('PARTNER', self::at($body, 'company.status'));
    }

    public function testEditingOtherFieldsDoesNotTouchStatusOrRecontactDate(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('2027-01-15'));
        $interaction = $this->interaction($company, '2026-10-01', null, InteractionOutcome::NoNeed);
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($interaction->getId()), ['notes' => 'Précision']);

        self::assertSame('ON_HOLD', self::at($body, 'company.status'));
        self::assertStringStartsWith('2027-01-15', self::str($body, 'company.recontactAfter'));
    }

    public function testChangingTheDateMakesAnotherInteractionTheLatest(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::OnHold);
        $significant = $this->interaction($company, '2026-09-01', null, InteractionOutcome::NoNeed);
        $this->interaction($company, '2026-09-20');
        $company->setRecontactAfter(new \DateTimeImmutable('2027-03-01'));
        $this->em->flush();

        [, $body] = $this->request('PATCH', $this->uri($significant->getId()), ['occurredAt' => '2026-10-05']);

        self::assertSame('ON_HOLD', self::at($body, 'company.status'));
        self::assertStringStartsWith('2027-04-05', self::str($body, 'company.recontactAfter'));
        self::assertStringStartsWith('2026-10-05', self::str($body, 'company.lastInteractionAt'));
    }

    public function testLinksAndUnlinksContact(): void
    {
        $company = $this->company('Acme');
        $contact = new Contact($company, 'Jane Doe');
        $this->em->persist($contact);
        $interaction = $this->interaction($company, '2026-10-01');
        $this->em->flush();

        [, $linked] = $this->request('PATCH', $this->uri($interaction->getId()), ['contactId' => $contact->getId()]);
        self::assertSame($contact->getId(), self::at($linked, 'interaction.contactId'));

        [, $unlinked] = $this->request('PATCH', $this->uri($interaction->getId()), ['contactId' => null]);
        self::assertNull(self::at($unlinked, 'interaction.contactId'));
    }

    public function testRejectsContactOfAnotherCompany(): void
    {
        $other = new Contact($this->company('Other'), 'Jane Doe');
        $this->em->persist($other);
        $interaction = $this->interaction($this->company('Acme'), '2026-10-01');
        $this->em->flush();

        self::assertSame(422, $this->request('PATCH', $this->uri($interaction->getId()), ['contactId' => $other->getId()])[0]);
    }

    public function testRejectsInvalidPayloads(): void
    {
        $uri = $this->uri($this->interaction($this->company('Acme'), '2026-10-01')->getId());

        foreach ([
            ['unknown' => 1],
            ['channel' => 'PIGEON'],
            ['outcome' => 'NOPE'],
            ['occurredAt' => null],
            ['occurredAt' => 'not a date'],
            ['contactId' => 'abc'],
            ['notes' => 42],
        ] as $payload) {
            self::assertSame(422, $this->request('PATCH', $uri, $payload)[0], (string) json_encode($payload));
        }
    }

    public function testUnknownInteractionIsNotFound(): void
    {
        self::assertSame(404, $this->request('PATCH', $this->uri(999999), ['notes' => 'x'])[0]);
    }
}
