<?php

namespace App\Tests\Api;

use App\Entity\Contact;
use App\Enum\PipelineStatus;

final class CompanyApiTest extends ApiTestCase
{
    public function testListIsPaginatedAndSortedByName(): void
    {
        foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
            $this->company($name);
        }

        [$status, $body] = $this->get('/api/companies?perPage=2');

        self::assertSame(200, $status);
        self::assertSame(3, self::at($body, 'total'));
        self::assertSame(['Alpha', 'Bravo'], self::names($body));
        self::assertSame(['Charlie'], self::names($this->get('/api/companies?perPage=2&page=2')[1]));
    }

    public function testArchivedCompaniesAreHiddenByDefault(): void
    {
        $this->company('Visible');
        $this->em->persist($this->company('Archived')->archive());
        $this->em->flush();

        self::assertSame(['Visible'], self::names($this->get('/api/companies')[1]));
        self::assertSame(['Archived'], self::names($this->get('/api/companies?archived=1')[1]));
    }

    public function testFiltersByStatus(): void
    {
        $this->company('Todo');
        $this->company('Held')->setStatus(PipelineStatus::OnHold);
        $this->em->flush();

        self::assertSame(['Held'], self::names($this->get('/api/companies?status=ON_HOLD')[1]));
    }

    public function testSearchIgnoresCaseAndAccents(): void
    {
        $this->company('Acme', 'Besançon');
        $this->company('Other', 'Lure');

        self::assertSame(['Acme'], self::names($this->get('/api/companies?q=BESANCON')[1]));
        self::assertSame(['Acme'], self::names($this->get('/api/companies?q=besançon')[1]));
    }

    public function testSearchByAcronym(): void
    {
        $this->company('Business Web Agence');
        $this->company('Bureau Wallon Associés');
        $this->company('Atol CD');
        $this->company('Élan Web');
        $this->em->flush();

        self::assertSame(['Bureau Wallon Associés', 'Business Web Agence'], self::names($this->get('/api/companies?q=bwa&sort=name')[1]));
        self::assertSame(['Élan Web'], self::names($this->get('/api/companies?q=EW')[1]));
        self::assertSame([], self::names($this->get('/api/companies?q=xyz')[1]));
    }

    public function testDueFilter(): void
    {
        $this->company('Recontact due')->setRecontactAfter(new \DateTimeImmutable('-1 day'));
        $this->company('Recontact later')->setRecontactAfter(new \DateTimeImmutable('+30 days'));

        $due = $this->company('Next action due');
        $this->interaction($due, '-10 days', '-2 days');

        $followedUp = $this->company('Followed up');
        $this->interaction($followedUp, '-10 days', '-5 days');
        $this->interaction($followedUp, '-1 day');

        $this->em->flush();

        self::assertSame(['Next action due', 'Recontact due'], self::names($this->get('/api/companies?due=1')[1]));
    }

    public function testHidePartnersUnlessAStatusIsRequested(): void
    {
        $this->company('Prospect');
        $this->company('Partner')->setStatus(PipelineStatus::Partner);
        $this->em->flush();

        self::assertSame(['Partner', 'Prospect'], self::names($this->get('/api/companies')[1]));
        self::assertSame(['Prospect'], self::names($this->get('/api/companies?hidePartners=1')[1]));
        self::assertSame(['Partner'], self::names($this->get('/api/companies?hidePartners=1&status=PARTNER')[1]));
    }

    public function testSortByLastInteractionPutsEmptyOnesLast(): void
    {
        $this->interaction($this->company('Old'), '-30 days');
        $this->interaction($this->company('Recent'), '-1 day');
        $this->company('Never');

        self::assertSame(['Recent', 'Old', 'Never'], self::names($this->get('/api/companies?sort=lastInteractionAt&direction=desc')[1]));
        self::assertSame(['Old', 'Recent', 'Never'], self::names($this->get('/api/companies?sort=lastInteractionAt&direction=asc')[1]));
    }

    public function testInvalidParametersAreRejected(): void
    {
        foreach (['sort=password', 'status=NOPE', 'perPage=1000', 'page=0', 'direction=sideways'] as $query) {
            self::assertSame(422, $this->get('/api/companies?'.$query)[0], $query);
        }
    }

    public function testShowReturnsContactsAndInteractionsNewestFirst(): void
    {
        $company = $this->company('Acme');
        $this->em->persist(new Contact($company, 'Jane Doe'));
        $this->interaction($company, '-10 days');
        $this->interaction($company, '-1 day');
        $this->em->flush();

        [$status, $raw] = $this->get('/api/companies/'.$company->getId());
        /** @var array{name: string, contacts: list<array{fullName: string}>, interactions: list<array{occurredAt: string}>, lastInteractionAt: string, signals: list<mixed>} $body */
        $body = $raw;

        self::assertSame(200, $status);
        self::assertSame('Acme', self::at($body, 'name'));
        self::assertSame('Jane Doe', self::at($body, 'contacts.0.fullName'));
        self::assertCount(2, self::arr($body, 'interactions'));
        self::assertGreaterThan(self::at($body, 'interactions.1.occurredAt'), self::at($body, 'interactions.0.occurredAt'));
        self::assertSame(self::at($body, 'interactions.0.occurredAt'), self::at($body, 'lastInteractionAt'));
        self::assertSame([], self::at($body, 'signals'));
    }

    public function testShowUnknownCompanyIsNotFound(): void
    {
        self::assertSame(404, $this->get('/api/companies/999999')[0]);
    }
}
