<?php

namespace App\Tests\Signal;

use App\Signal\RemoteDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RemoteDetectorTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function texts(): iterable
    {
        yield 'remote en titre' => ['Full Stack Engineer (React / Python) Azure, AI Projects Remote (h/f)', true];
        yield 'full remote' => ['Développeur Symfony - Full remote', true];
        yield 'télétravail accentué' => ['Dev PHP, télétravail 3 jours', true];
        yield 'teletravail sans accent' => ['dev php teletravail', true];
        yield 'distanciel' => ['Mission en distanciel', true];
        yield 'sans mention' => ['Développeur Symfony senior - Paris', false];
        yield 'mot contenant remote' => ['Promoteur immobilier remoteness', false];
    }

    #[DataProvider('texts')]
    public function testDetection(string $text, bool $expected): void
    {
        self::assertSame($expected, RemoteDetector::isRemote($text));
    }
}
