<?php

namespace App\Tests\Api;

use App\Enum\PipelineStatus;

final class CompanyWriteApiTest extends ApiTestCase
{
    public function testCreateCompanyWithDefaults(): void
    {
        [$status, $body] = $this->request('POST', '/api/companies', ['name' => '  Acme Web  ', 'city' => 'Lure']);

        self::assertSame(201, $status);
        self::assertSame('Acme Web', self::at($body, 'name'));
        self::assertSame('NATIONAL', self::at($body, 'scope'));
        self::assertSame('UNKNOWN', self::at($body, 'type'));
        self::assertSame('TO_CONTACT', self::at($body, 'status'));
        self::assertSame('Lure', self::at($body, 'city'));
        self::assertSame(['Acme Web'], self::names($this->get('/api/companies')[1]));
    }

    public function testCreateRejectsExistingNameIgnoringCase(): void
    {
        $existing = $this->company('Acme Web');

        [$status, $body] = $this->request('POST', '/api/companies', ['name' => 'ACME web']);

        self::assertSame(409, $status);
        self::assertSame($existing->getId(), self::at($body, 'id'));
    }

    public function testCreateRejectsInvalidInput(): void
    {
        self::assertSame(422, $this->request('POST', '/api/companies', ['name' => '  '])[0]);
        self::assertSame(422, $this->request('POST', '/api/companies', ['name' => 'X', 'scope' => 'MOON'])[0]);
        self::assertSame(422, $this->request('POST', '/api/companies', [])[0]);
    }

    public function testPatchStatusToOnHoldSetsDefaultRecontactDate(): void
    {
        $company = $this->company('Acme');

        [$status, $body] = $this->request('PATCH', '/api/companies/'.$company->getId(), ['status' => 'ON_HOLD']);

        self::assertSame(200, $status);
        self::assertSame('ON_HOLD', self::at($body, 'status'));
        self::assertGreaterThan(new \DateTimeImmutable('+5 months'), new \DateTimeImmutable(self::str($body, 'recontactAfter')));
    }

    public function testPatchStatusAwayFromOnHoldClearsRecontactDate(): void
    {
        $company = $this->company('Acme')->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('+3 months'));
        $this->em->flush();

        [, $body] = $this->request('PATCH', '/api/companies/'.$company->getId(), ['status' => 'FOLLOW_UP']);

        self::assertSame('FOLLOW_UP', self::at($body, 'status'));
        self::assertNull(self::at($body, 'recontactAfter'));
    }

    public function testPatchKeepsExplicitRecontactDateAndOtherFields(): void
    {
        $company = $this->company('Acme');

        [, $body] = $this->request('PATCH', '/api/companies/'.$company->getId(), [
            'status' => 'ON_HOLD',
            'recontactAfter' => '2027-01-15',
            'notes' => 'À rappeler',
            'flags' => ['IN_DIFFICULTY'],
            'type' => 'WEB_AGENCY',
        ]);

        self::assertStringStartsWith('2027-01-15', self::str($body, 'recontactAfter'));
        self::assertSame('À rappeler', self::at($body, 'notes'));
        self::assertSame(['IN_DIFFICULTY'], self::at($body, 'flags'));
        self::assertSame('WEB_AGENCY', self::at($body, 'type'));
    }

    public function testPatchOnlyTouchesGivenFields(): void
    {
        $company = $this->company('Acme')->setNotes('Garder')->setStatus(PipelineStatus::Partner);
        $this->em->flush();

        [, $body] = $this->request('PATCH', '/api/companies/'.$company->getId(), ['flags' => ['MAYBE_INACTIVE']]);

        self::assertSame('Garder', self::at($body, 'notes'));
        self::assertSame('PARTNER', self::at($body, 'status'));
    }

    public function testArchiveAndUnarchive(): void
    {
        $company = $this->company('Acme');

        $this->request('PATCH', '/api/companies/'.$company->getId(), ['archived' => true]);
        self::assertSame([], self::names($this->get('/api/companies')[1]));
        self::assertSame(['Acme'], self::names($this->get('/api/companies?archived=1')[1]));

        $this->request('PATCH', '/api/companies/'.$company->getId(), ['archived' => false]);
        self::assertSame(['Acme'], self::names($this->get('/api/companies')[1]));
    }

    public function testPatchRejectsInvalidPayloads(): void
    {
        $uri = '/api/companies/'.$this->company('Acme')->getId();

        foreach ([
            ['unknownField' => 1],
            ['status' => 'NOPE'],
            ['flags' => ['lowercase']],
            ['flags' => 'IN_DIFFICULTY'],
            ['archived' => 'yes'],
            ['recontactAfter' => 'not a date'],
            ['notes' => 42],
        ] as $payload) {
            self::assertSame(422, $this->request('PATCH', $uri, $payload)[0], (string) json_encode($payload));
        }
    }

    public function testPatchUnknownCompanyIsNotFound(): void
    {
        self::assertSame(404, $this->request('PATCH', '/api/companies/999999', ['notes' => 'x'])[0]);
    }
}
