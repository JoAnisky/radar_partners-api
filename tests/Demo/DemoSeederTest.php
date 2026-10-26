<?php

namespace App\Tests\Demo;

use App\Demo\DemoSeeder;
use App\Entity\Company;
use App\Enum\PipelineStatus;
use App\Tests\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSeederTest extends ApiTestCase
{
    private function seeder(): DemoSeeder
    {
        $seeder = self::getContainer()->get(DemoSeeder::class);
        \assert($seeder instanceof DemoSeeder);

        return $seeder;
    }

    public function testSeedCoversEveryStatusAndFeedsTheDashboard(): void
    {
        $counts = $this->seeder()->seed();

        self::assertSame(40, $counts['companies']);

        $statuses = array_unique(array_map(
            static fn (Company $c): PipelineStatus => $c->getStatus(),
            $this->em->getRepository(Company::class)->findAll(),
        ), \SORT_REGULAR);
        self::assertCount(\count(PipelineStatus::cases()), $statuses);

        [$status, $body] = $this->get('/api/stats');
        self::assertSame(200, $status);

        /** @var array{kpis: array{due: array{total: int}, signals: array{unresolved: int}}, lists: array{priorities: list<mixed>}} $stats */
        $stats = $body;
        self::assertGreaterThan(0, $stats['kpis']['due']['total']);
        self::assertGreaterThan(0, $stats['kpis']['signals']['unresolved']);
        self::assertNotEmpty($stats['lists']['priorities']);
    }

    public function testSeedIsIdempotent(): void
    {
        $this->seeder()->seed();
        $counts = $this->seeder()->seed();

        self::assertSame($counts['companies'], $this->em->getRepository(Company::class)->count([]));
    }

    public function testCommandRefusesToRunOutsideDemoMode(): void
    {
        $this->company('Entreprise réelle');

        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:demo:seed'));
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame(1, $this->em->getRepository(Company::class)->count([]));
    }
}
