<?php

namespace App\Tests\Api;

use App\Entity\Contact;

final class ContactApiTest extends ApiTestCase
{
    private function contact(string $name = 'Jane Doe', string $companyName = 'Acme'): Contact
    {
        $contact = new Contact($this->company($companyName), $name);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    public function testCreatesContactWithNormalizedFields(): void
    {
        $company = $this->company('Acme');

        [$status, $body] = $this->request('POST', sprintf('/api/companies/%d/contacts', $company->getId()), [
            'fullName' => '  Jane Doe ',
            'role' => 'CTO',
            'phone' => '06 12 34 56 78',
            'email' => 'Jane@Acme.TEST',
            'linkedinUrl' => 'linkedin.com/in/jane',
            'notes' => 'Rencontrée à un apéro',
        ]);

        self::assertSame(201, $status);
        self::assertSame('Jane Doe', self::at($body, 'fullName'));
        self::assertSame('CTO', self::at($body, 'role'));
        self::assertSame('+33612345678', self::at($body, 'phone'));
        self::assertSame('jane@acme.test', self::at($body, 'email'));
        self::assertSame('https://linkedin.com/in/jane', self::at($body, 'linkedinUrl'));

        $detail = $this->get('/api/companies/'.$company->getId())[1];
        self::assertSame('Jane Doe', self::at($detail, 'contacts.0.fullName'));
    }

    public function testCreateWithOnlyAName(): void
    {
        $company = $this->company('Acme');

        [$status, $body] = $this->request('POST', sprintf('/api/companies/%d/contacts', $company->getId()), ['fullName' => 'Jane']);

        self::assertSame(201, $status);
        self::assertNull(self::at($body, 'phone'));
    }

    public function testCreateRejectsInvalidInputAndLeavesNoContact(): void
    {
        $company = $this->company('Acme');
        $uri = sprintf('/api/companies/%d/contacts', $company->getId());

        foreach ([
            [],
            ['fullName' => '   '],
            ['fullName' => null],
            ['fullName' => 'Jane', 'phone' => '12'],
            ['fullName' => 'Jane', 'email' => 'nope'],
            ['fullName' => 'Jane', 'unknown' => 1],
            ['fullName' => 42],
        ] as $payload) {
            self::assertSame(422, $this->request('POST', $uri, $payload)[0], (string) json_encode($payload));
        }

        $this->em->clear();
        self::assertSame([], self::arr($this->get('/api/companies/'.$company->getId())[1], 'contacts'));
    }

    public function testCreateForUnknownCompanyIsNotFound(): void
    {
        self::assertSame(404, $this->request('POST', '/api/companies/999999/contacts', ['fullName' => 'Jane'])[0]);
    }

    public function testPatchEditsAndClearsFields(): void
    {
        $contact = $this->contact();
        $contact->setRole('CTO')->setEmail('jane@acme.test');
        $this->em->flush();
        $uri = '/api/contacts/'.$contact->getId();

        [$status, $body] = $this->request('PATCH', $uri, ['fullName' => 'Jane Smith', 'role' => null, 'phone' => '03 81 00 00 00']);

        self::assertSame(200, $status);
        self::assertSame('Jane Smith', self::at($body, 'fullName'));
        self::assertNull(self::at($body, 'role'));
        self::assertSame('+33381000000', self::at($body, 'phone'));
        self::assertSame('jane@acme.test', self::at($body, 'email'));
    }

    public function testPatchRejectsInvalidPayloads(): void
    {
        $uri = '/api/contacts/'.$this->contact()->getId();

        foreach ([['fullName' => ''], ['fullName' => null], ['phone' => 'abc'], ['email' => 'nope'], ['unknown' => 1], ['role' => []]] as $payload) {
            self::assertSame(422, $this->request('PATCH', $uri, $payload)[0], (string) json_encode($payload));
        }
    }

    public function testPatchUnknownContactIsNotFound(): void
    {
        self::assertSame(404, $this->request('PATCH', '/api/contacts/999999', ['role' => 'x'])[0]);
    }

    public function testDeleteRemovesTheContactButKeepsItsInteractions(): void
    {
        $contact = $this->contact();
        $company = $contact->getCompany();
        $this->interaction($company, '2026-10-01')->setContact($contact);
        $this->em->flush();

        [$status] = $this->request('DELETE', '/api/contacts/'.$contact->getId());
        self::assertSame(204, $status);

        $this->em->clear();
        $detail = $this->get('/api/companies/'.$company->getId())[1];
        self::assertSame([], self::arr($detail, 'contacts'));
        self::assertCount(1, self::arr($detail, 'interactions'));
        self::assertNull(self::at($detail, 'interactions.0.contactId'));
    }

    public function testDeleteUnknownContactIsNotFound(): void
    {
        self::assertSame(404, $this->request('DELETE', '/api/contacts/999999')[0]);
    }

    private function contactOf(string $companyName, string $name, ?string $role = null): Contact
    {
        $contact = (new Contact($this->company($companyName), $name))->setRole($role);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private static function contactNames(array $body): array
    {
        /** @var list<array{fullName: string}> $items */
        $items = $body['items'];

        return array_map(static fn (array $item): string => $item['fullName'], $items);
    }

    public function testListsContactsWithTheirCompanySortedByName(): void
    {
        $this->contactOf('Beta', 'Zoé Martin', 'CTO');
        $this->contactOf('Alpha', 'Anna Durand');

        [$status, $body] = $this->get('/api/contacts');

        self::assertSame(200, $status);
        self::assertSame(2, $body['total']);
        self::assertSame(['Anna Durand', 'Zoé Martin'], self::contactNames($body));
        self::assertSame('Alpha', self::at($body, 'items.0.company.name'));
        self::assertSame('TO_CONTACT', self::at($body, 'items.0.company.status'));
        self::assertNull(self::at($body, 'items.0.lastInteractionAt'));
    }

    public function testSearchCoversNameRoleEmailAndCompanyIgnoringAccents(): void
    {
        $this->contactOf('Studio Lumière', 'Anna Durand', 'Directrice technique');
        $other = $this->contactOf('Beta', 'Zoé Martin');
        $other->setEmail('zoe@beta.test');
        $this->em->flush();

        self::assertSame(['Anna Durand'], self::contactNames($this->get('/api/contacts?q=lumiere')[1]));
        self::assertSame(['Anna Durand'], self::contactNames($this->get('/api/contacts?q=TECHNIQUE')[1]));
        self::assertSame(['Zoé Martin'], self::contactNames($this->get('/api/contacts?q=zoe')[1]));
        self::assertSame(['Zoé Martin'], self::contactNames($this->get('/api/contacts?q=beta.test')[1]));
    }

    public function testContactsOfArchivedCompaniesAreHidden(): void
    {
        $this->contactOf('Visible', 'Anna');
        $hidden = $this->contactOf('Hidden', 'Zoé');
        $hidden->getCompany()->archive();
        $this->em->flush();

        self::assertSame(['Anna'], self::contactNames($this->get('/api/contacts')[1]));
    }

    public function testLastInteractionDateAndSortPutsEmptyOnesLast(): void
    {
        $old = $this->contactOf('A', 'Old');
        $recent = $this->contactOf('B', 'Recent');
        $this->contactOf('C', 'Never');
        $this->interaction($old->getCompany(), '2026-09-01')->setContact($old);
        $this->interaction($recent->getCompany(), '2026-10-01')->setContact($recent);
        $this->interaction($recent->getCompany(), '2026-10-05');
        $this->em->flush();

        [, $body] = $this->get('/api/contacts?sort=lastInteractionAt&direction=desc');

        self::assertSame(['Recent', 'Old', 'Never'], self::contactNames($body));
        self::assertStringStartsWith('2026-10-01', self::str($body, 'items.0.lastInteractionAt'));
        self::assertSame(['Old', 'Recent', 'Never'], self::contactNames($this->get('/api/contacts?sort=lastInteractionAt&direction=asc')[1]));
    }

    public function testSortByCompanyAndPagination(): void
    {
        $this->contactOf('Charlie Corp', 'Anna');
        $this->contactOf('Alpha Corp', 'Zoé');
        $this->contactOf('Bravo Corp', 'Mia');

        self::assertSame(['Zoé', 'Mia', 'Anna'], self::contactNames($this->get('/api/contacts?sort=company')[1]));
        self::assertSame(['Anna'], self::contactNames($this->get('/api/contacts?sort=company&perPage=2&page=2')[1]));
    }

    public function testListRejectsInvalidParameters(): void
    {
        foreach (['sort=password', 'direction=sideways', 'perPage=0', 'perPage=101', 'page=0'] as $query) {
            self::assertSame(422, $this->get('/api/contacts?'.$query)[0], $query);
        }
    }
}
