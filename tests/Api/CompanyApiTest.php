<?php

namespace App\Tests\Api;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Enum\PipelineStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class CompanyApiTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function company(string $name, ?string $city = null): Company
    {
        $company = (new Company($name))->setCity($city);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function interaction(Company $company, string $date, ?string $nextAction = null): Interaction
    {
        $interaction = (new Interaction($company, InteractionChannel::Phone, new \DateTimeImmutable($date)))
            ->setOutcome(InteractionOutcome::NoAnswer)
            ->setNextActionAt(null === $nextAction ? null : new \DateTimeImmutable($nextAction));
        $this->em->persist($interaction);
        $this->em->flush();

        return $interaction;
    }

    /** @return array{int, array<string, mixed>} */
    private function get(string $uri): array
    {
        \assert(null !== self::$kernel);
        $response = self::$kernel->handle(Request::create($uri, server: ['HTTP_ACCEPT' => 'application/json']));
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true) ?: [];

        return [$response->getStatusCode(), $body];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private static function names(array $body): array
    {
        /** @var list<array{name: string}> $items */
        $items = $body['items'];

        return array_map(static fn (array $item): string => $item['name'], $items);
    }

    public function testListIsPaginatedAndSortedByName(): void
    {
        foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
            $this->company($name);
        }

        [$status, $body] = $this->get('/api/companies?perPage=2');

        self::assertSame(200, $status);
        self::assertSame(3, $body['total']);
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
        self::assertSame('Acme', $body['name']);
        self::assertSame('Jane Doe', $body['contacts'][0]['fullName']);
        self::assertCount(2, $body['interactions']);
        self::assertGreaterThan($body['interactions'][1]['occurredAt'], $body['interactions'][0]['occurredAt']);
        self::assertSame($body['interactions'][0]['occurredAt'], $body['lastInteractionAt']);
        self::assertSame([], $body['signals']);
    }

    public function testShowUnknownCompanyIsNotFound(): void
    {
        self::assertSame(404, $this->get('/api/companies/999999')[0]);
    }
}
