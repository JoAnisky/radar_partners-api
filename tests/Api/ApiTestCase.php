<?php

namespace App\Tests\Api;

use App\Entity\Company;
use App\Entity\Interaction;
use App\Entity\Signal;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Enum\SignalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/** Tests d'API sur la base `radar_test`, chaque test dans une transaction annulée. */
abstract class ApiTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

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

    protected function company(string $name, ?string $city = null): Company
    {
        $company = (new Company($name))->setCity($city);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    protected function interaction(Company $company, string $date, ?string $nextAction = null, InteractionOutcome $outcome = InteractionOutcome::NoAnswer): Interaction
    {
        $interaction = (new Interaction($company, InteractionChannel::Phone, new \DateTimeImmutable($date)))
            ->setOutcome($outcome)
            ->setNextActionAt(null === $nextAction ? null : new \DateTimeImmutable($nextAction));
        $this->em->persist($interaction);
        $this->em->flush();

        return $interaction;
    }

    protected function signal(string $title, SignalType $type = SignalType::FreelanceMission, string $detectedAt = 'now'): Signal
    {
        $signal = (new Signal($type, 'test', hash('sha256', $title)))
            ->setTitle($title)
            ->setDetectedAt(new \DateTimeImmutable($detectedAt));
        $this->em->persist($signal);
        $this->em->flush();

        return $signal;
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array{int, array<string, mixed>}
     */
    protected function request(string $method, string $uri, ?array $json = null): array
    {
        \assert(null !== self::$kernel);
        $response = self::$kernel->handle(Request::create(
            $uri,
            $method,
            content: null === $json ? null : (string) json_encode($json),
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
        ));
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true) ?: [];

        return [$response->getStatusCode(), $body];
    }

    /** @return array{int, array<string, mixed>} */
    protected function get(string $uri): array
    {
        return $this->request('GET', $uri);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    protected static function names(array $body): array
    {
        /** @var list<array{name: string}> $items */
        $items = $body['items'];

        return array_map(static fn (array $item): string => $item['name'], $items);
    }

    /** @param array<mixed> $data */
    protected static function at(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            self::assertIsArray($data, $path);
            self::assertArrayHasKey($key, $data, $path);
            $data = $data[$key];
        }

        return $data;
    }

    /** @param array<mixed> $data */
    protected static function str(array $data, string $path): string
    {
        $value = self::at($data, $path);
        self::assertIsString($value, $path);

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    protected static function arr(array $data, string $path): array
    {
        $value = self::at($data, $path);
        self::assertIsArray($value, $path);

        return $value;
    }
}
