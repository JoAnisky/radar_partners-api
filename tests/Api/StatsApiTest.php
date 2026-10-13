<?php

namespace App\Tests\Api;

use App\Entity\Company;
use App\Entity\Contact;
use App\Enum\CompanyType;
use App\Enum\PipelineStatus;
use App\Enum\SignalType;

final class StatsApiTest extends ApiTestCase
{
    private function createdAt(Company $company, string $date): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE company SET created_at = :date WHERE id = :id',
            ['date' => (new \DateTimeImmutable($date))->format('Y-m-d H:i:s'), 'id' => $company->getId()],
        );
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        [$status, $body] = $this->get('/api/stats');
        self::assertSame(200, $status);

        return $body;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<int>
     */
    private static function ints(array $body, string $path): array
    {
        /** @var list<int> $values */
        $values = self::arr($body, $path);

        return $values;
    }

    public function testEmptyDatabase(): void
    {
        $stats = $this->stats();

        self::assertCount(12, self::arr($stats, 'weeks'));
        self::assertSame(0, self::at($stats, 'kpis.companies.total'));
        self::assertSame(array_fill(0, 12, 0), self::ints($stats, 'kpis.companies.series'));
        self::assertSame(array_fill(0, 12, 0), self::ints($stats, 'activity.interactions'));
        self::assertNull(self::at($stats, 'kpis.interactions.deltaPercent'));
        self::assertNull(self::at($stats, 'kpis.signals.deltaPercent'));
        self::assertSame([], self::arr($stats, 'lists.due'));
        self::assertSame([], self::arr($stats, 'lists.priorities'));
        self::assertSame([], self::arr($stats, 'lists.recentSignals'));
        self::assertSame(['total' => 0, 'contacted' => 0, 'reachable' => 0, 'typed' => 0], self::arr($stats, 'progress'));
    }

    public function testWeeksAreTwelveAscendingMondaysEndingThisWeek(): void
    {
        $weeks = array_map(static fn (mixed $week): string => \is_string($week) ? $week : '', self::arr($this->stats(), 'weeks'));

        self::assertCount(12, $weeks);
        foreach ($weeks as $i => $week) {
            self::assertSame('Monday', (new \DateTimeImmutable($week))->format('l'));
            if ($i > 0) {
                self::assertSame(7, (int) (new \DateTimeImmutable($weeks[$i - 1]))->diff(new \DateTimeImmutable($week))->days);
            }
        }
        self::assertSame((new \DateTimeImmutable('monday this week'))->format('Y-m-d'), $weeks[11]);
    }

    public function testComputesIndicatorsSeriesPipelineAndLists(): void
    {
        $prospect = $this->company('Prospect')->setType(CompanyType::Esn);
        $contacted = $this->company('Contacted')->setStatus(PipelineStatus::Contacted);
        $onHold = $this->company('Late')->setStatus(PipelineStatus::OnHold)->setRecontactAfter(new \DateTimeImmutable('-2 days'));
        $this->company('Meeting')->setStatus(PipelineStatus::Meeting);
        $this->company('Archived')->archive();
        $this->em->persist(new Contact($contacted, 'Jane'));
        $this->em->flush();
        $contact = $contacted->getContacts()->first();
        \assert($contact instanceof Contact);
        $contact->setEmail('jane@example.test');
        $this->createdAt($contacted, '-3 weeks');

        $this->interaction($contacted, '-3 days');
        $this->interaction($contacted, '-40 days');
        $this->interaction($onHold, '-5 days');
        $this->signal('Offre récente', SignalType::FreelanceMission, '-2 days');
        $this->signal('Offre ancienne', SignalType::JobPosting, '-35 days')->setCompany($prospect);
        $this->em->flush();
        $this->em->clear();

        $stats = $this->stats();

        // Indicateurs
        self::assertSame(4, self::at($stats, 'kpis.companies.total'));
        self::assertSame(3, self::at($stats, 'kpis.companies.createdLast7Days'));
        $cumulative = self::ints($stats, 'kpis.companies.series');
        self::assertSame(0, $cumulative[0]);
        self::assertSame(4, $cumulative[11]);
        $sorted = $cumulative;
        sort($sorted);
        self::assertSame($sorted, $cumulative);
        self::assertSame(1, self::at($stats, 'kpis.due.total'));
        self::assertSame(2, self::at($stats, 'kpis.interactions.last30Days'));
        self::assertSame(1, self::at($stats, 'kpis.interactions.previous30Days'));
        self::assertEqualsWithDelta(100.0, self::at($stats, 'kpis.interactions.deltaPercent'), 0.01);
        self::assertSame(3, array_sum(self::ints($stats, 'kpis.interactions.series')));
        self::assertSame(1, self::at($stats, 'kpis.signals.last30Days'));
        self::assertSame(1, self::at($stats, 'kpis.signals.previous30Days'));
        self::assertEqualsWithDelta(0.0, self::at($stats, 'kpis.signals.deltaPercent'), 0.01);
        self::assertSame(1, self::at($stats, 'kpis.signals.unresolved'));
        self::assertSame(2, array_sum(self::ints($stats, 'kpis.signals.series')));

        // Activité par semaine : les nouvelles entreprises sont réparties sur deux semaines
        self::assertSame(4, array_sum(self::ints($stats, 'activity.companies')));
        self::assertSame(3, self::ints($stats, 'activity.companies')[11]);

        // Pipeline : tous les statuts, dans l'ordre de l'énumération
        /** @var list<array{status: string, count: int}> $pipeline */
        $pipeline = self::arr($stats, 'pipeline');
        self::assertSame(array_map(static fn (PipelineStatus $s): string => $s->value, PipelineStatus::cases()), array_column($pipeline, 'status'));
        self::assertSame(['TO_CONTACT' => 1, 'CONTACTED' => 1, 'FOLLOW_UP' => 0, 'MEETING' => 1, 'PARTNER' => 0, 'ON_HOLD' => 1, 'EXCLUDED' => 0], array_column($pipeline, 'count', 'status'));

        // Avancement
        self::assertSame(['total' => 4, 'contacted' => 2, 'reachable' => 1, 'typed' => 1], self::arr($stats, 'progress'));

        // Listes
        self::assertSame('Late', self::at($stats, 'lists.due.0.name'));
        self::assertSame(['Prospect'], array_column(self::arr($stats, 'lists.priorities'), 'name'));
        self::assertSame(['Offre récente', 'Offre ancienne'], array_column(self::arr($stats, 'lists.recentSignals'), 'title'));
        self::assertNull(self::at($stats, 'lists.recentSignals.0.companyId'));
        self::assertSame($prospect->getId(), self::at($stats, 'lists.recentSignals.1.companyId'));
    }

    public function testListsAreLimitedToFiveEntries(): void
    {
        foreach (range(1, 7) as $i) {
            $this->company('Prospect '.$i);
            $this->signal('Offre '.$i, SignalType::FreelanceMission, sprintf('-%d hours', $i));
        }

        $stats = $this->stats();

        self::assertCount(5, self::arr($stats, 'lists.priorities'));
        self::assertCount(5, self::arr($stats, 'lists.recentSignals'));
        self::assertSame(7, self::at($stats, 'kpis.companies.total'));
    }
}
