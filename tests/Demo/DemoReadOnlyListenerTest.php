<?php

namespace App\Tests\Demo;

use App\Demo\DemoReadOnlyListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DemoReadOnlyListenerTest extends TestCase
{
    private function statusOf(bool $demoMode, string $method, string $uri): ?int
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create($uri, $method), HttpKernelInterface::MAIN_REQUEST);
        (new DemoReadOnlyListener($demoMode))($event);

        return $event->getResponse()?->getStatusCode();
    }

    /** @return iterable<string, array{string, string}> */
    public static function writes(): iterable
    {
        yield 'create company' => ['POST', '/api/companies'];
        yield 'update company' => ['PATCH', '/api/companies/1'];
        yield 'update signal' => ['PATCH', '/api/signals/1'];
        yield 'delete contact' => ['DELETE', '/api/contacts/1'];
    }

    #[DataProvider('writes')]
    public function testWritesAreForbiddenInDemoMode(string $method, string $uri): void
    {
        self::assertSame(403, $this->statusOf(true, $method, $uri));
    }

    #[DataProvider('writes')]
    public function testWritesPassOutsideDemoMode(string $method, string $uri): void
    {
        self::assertNull($this->statusOf(false, $method, $uri));
    }

    public function testReadsAndChatPassInDemoMode(): void
    {
        self::assertNull($this->statusOf(true, 'GET', '/api/companies'));
        self::assertNull($this->statusOf(true, 'OPTIONS', '/api/companies'));
        self::assertNull($this->statusOf(true, 'POST', '/api/chat'));
    }
}
