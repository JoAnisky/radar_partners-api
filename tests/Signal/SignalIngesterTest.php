<?php

namespace App\Tests\Signal;

use App\Entity\Signal;
use App\Enum\SignalType;
use App\Repository\SignalRepository;
use App\Signal\IngestOutcome;
use App\Signal\RawSignal;
use App\Signal\SignalIngester;
use App\Signal\StackFilter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SignalIngesterTest extends TestCase
{
    private function raw(string $title = 'Développeur Symfony', ?string $company = null): RawSignal
    {
        return new RawSignal(
            SignalType::FreelanceMission,
            'free-work',
            'fp-1',
            $title,
            'https://example.test/offer-1',
            $company,
            "Développeur Symfony\n1 mois - Paris",
            ['city' => 'Paris'],
            null,
            new \DateTimeImmutable('2026-10-13 08:40'),
        );
    }

    private function ingester(?Signal $existing, ?Signal &$persisted = null, int $expectedPersists = 0): SignalIngester
    {
        $repository = $this->createStub(SignalRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly($expectedPersists))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted = $entity instanceof Signal ? $entity : null;
            },
        );

        return new SignalIngester($em, $repository, new StackFilter(['symfony', 'php']));
    }

    public function testFilteredSignalIsNotStored(): void
    {
        self::assertSame(IngestOutcome::Filtered, $this->ingester(null)->ingest($this->raw('Développeur Java')));
    }

    public function testKnownFingerprintIsADuplicate(): void
    {
        $existing = new Signal(SignalType::FreelanceMission, 'free-work', 'fp-1');

        self::assertSame(IngestOutcome::Duplicate, $this->ingester($existing)->ingest($this->raw()));
    }

    public function testNewSignalIsPersistedWithItsData(): void
    {
        $persisted = null;

        $outcome = $this->ingester(null, $persisted, 1)->ingest($this->raw());

        self::assertSame(IngestOutcome::Created, $outcome);
        self::assertNotNull($persisted);
        self::assertSame('fp-1', $persisted->getFingerprint());
        self::assertSame('free-work', $persisted->getSource());
        self::assertSame('Développeur Symfony', $persisted->getTitle());
        self::assertSame('https://example.test/offer-1', $persisted->getUrl());
        self::assertSame(['city' => 'Paris'], $persisted->getExtracted());
        self::assertNull($persisted->getCompany());
        self::assertEquals(new \DateTimeImmutable('2026-10-13 08:40'), $persisted->getDetectedAt());
    }

    public function testCompanyNameIsKeptInExtractedData(): void
    {
        $persisted = null;

        $this->ingester(null, $persisted, 1)->ingest($this->raw(company: 'Acme'));

        self::assertSame('Acme', $persisted?->getExtracted()['companyName']);
    }

    public function testRemoteMentionIsStoredInExtractedData(): void
    {
        $persisted = null;

        $this->ingester(null, $persisted, 1)->ingest($this->raw('Développeur Symfony Remote'));

        self::assertTrue($persisted?->getExtracted()['remote']);
    }

    public function testNoRemoteMentionLeavesTheFlagOut(): void
    {
        $persisted = null;

        $this->ingester(null, $persisted, 1)->ingest($this->raw());

        self::assertArrayNotHasKey('remote', $persisted?->getExtracted() ?? []);
    }
}
