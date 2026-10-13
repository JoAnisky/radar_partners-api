<?php

namespace App\Tests\Api;

use App\Entity\Contact;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;

final class InteractionApiTest extends ApiTestCase
{
    private function uri(?int $companyId): string
    {
        return sprintf('/api/companies/%s/interactions', $companyId);
    }

    public function testCreatesInteractionAndMarksCompanyContacted(): void
    {
        $company = $this->company('Acme');

        [$status, $body] = $this->request('POST', $this->uri($company->getId()), [
            'channel' => 'PHONE',
            'occurredAt' => '2026-10-01T10:00:00+02:00',
            'outcome' => 'VOICEMAIL',
            'origin' => 'Apéro',
            'notes' => 'Message laissé',
            'nextActionAt' => '2026-10-08',
        ]);

        self::assertSame(201, $status);
        self::assertSame('PHONE', self::at($body, 'interaction.channel'));
        self::assertSame('VOICEMAIL', self::at($body, 'interaction.outcome'));
        self::assertSame('Message laissé', self::at($body, 'interaction.notes'));
        self::assertSame('CONTACTED', self::at($body, 'company.status'));
        self::assertStringStartsWith('2026-10-01', self::str($body, 'company.lastInteractionAt'));

        $detail = $this->get('/api/companies/'.$company->getId())[1];
        self::assertCount(1, self::arr($detail, 'interactions'));
    }

    public function testDefaultsToNowAndOtherOutcome(): void
    {
        $company = $this->company('Acme');

        [, $body] = $this->request('POST', $this->uri($company->getId()), ['channel' => 'EMAIL']);

        self::assertSame('OTHER', self::at($body, 'interaction.outcome'));
        self::assertGreaterThan(new \DateTimeImmutable('-1 minute'), new \DateTimeImmutable(self::str($body, 'interaction.occurredAt')));
    }

    public function testSignificantOutcomeUpdatesStatusAndRecontactDate(): void
    {
        $company = $this->company('Acme');

        [, $body] = $this->request('POST', $this->uri($company->getId()), [
            'channel' => 'PHONE',
            'occurredAt' => '2026-10-01',
            'outcome' => 'NO_NEED',
        ]);

        self::assertSame('ON_HOLD', self::at($body, 'company.status'));
        self::assertStringStartsWith('2027-04-01', self::str($body, 'company.recontactAfter'));
    }

    public function testNonSignificantInteractionKeepsManualStatus(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::Partner);
        $this->em->flush();

        [, $body] = $this->request('POST', $this->uri($company->getId()), ['channel' => 'PHONE', 'outcome' => 'NO_ANSWER']);

        self::assertSame('PARTNER', self::at($body, 'company.status'));
    }

    public function testBackdatedSignificantInteractionDoesNotOverrideNewerStatus(): void
    {
        $company = $this->company('Acme');
        $this->interaction($company, '2026-10-10', null, InteractionOutcome::MeetingScheduled);
        $company->setStatus(PipelineStatus::Meeting);
        $this->em->flush();

        [, $body] = $this->request('POST', $this->uri($company->getId()), [
            'channel' => 'EMAIL',
            'occurredAt' => '2026-09-01',
            'outcome' => 'NO_FREELANCE_POLICY',
        ]);

        self::assertSame('MEETING', self::at($body, 'company.status'));
        self::assertStringStartsWith('2026-10-10', self::str($body, 'company.lastInteractionAt'));
    }

    public function testLinksContactOfTheSameCompany(): void
    {
        $company = $this->company('Acme');
        $contact = new Contact($company, 'Jane Doe');
        $this->em->persist($contact);
        $this->em->flush();

        [, $body] = $this->request('POST', $this->uri($company->getId()), ['channel' => 'PHONE', 'contactId' => $contact->getId()]);

        self::assertSame($contact->getId(), self::at($body, 'interaction.contactId'));
    }

    public function testRejectsContactOfAnotherCompany(): void
    {
        $other = $this->company('Other');
        $contact = new Contact($other, 'Jane Doe');
        $this->em->persist($contact);
        $this->em->flush();
        $company = $this->company('Acme');

        self::assertSame(422, $this->request('POST', $this->uri($company->getId()), ['channel' => 'PHONE', 'contactId' => $contact->getId()])[0]);
    }

    public function testRejectsInvalidInput(): void
    {
        $uri = $this->uri($this->company('Acme')->getId());

        self::assertSame(422, $this->request('POST', $uri, [])[0]);
        self::assertSame(422, $this->request('POST', $uri, ['channel' => 'PIGEON'])[0]);
        self::assertSame(422, $this->request('POST', $uri, ['channel' => 'PHONE', 'outcome' => 'NOPE'])[0]);
        self::assertSame(422, $this->request('POST', $uri, ['channel' => 'PHONE', 'occurredAt' => 'not a date'])[0]);
    }

    public function testUnknownCompanyIsNotFound(): void
    {
        self::assertSame(404, $this->request('POST', $this->uri(999999), ['channel' => 'PHONE'])[0]);
    }
}
