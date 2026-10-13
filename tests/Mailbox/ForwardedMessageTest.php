<?php

namespace App\Tests\Mailbox;

use App\Mailbox\ForwardedMessage;
use App\Mailbox\RawEmail;
use PHPUnit\Framework\TestCase;

final class ForwardedMessageTest extends TestCase
{
    public function testExtractsOriginalSenderFromGmailBanner(): void
    {
        $text = "---------- Forwarded message ---------\nDe : Acme Jobs <Jobs@Acme.test>\nDate: lun. 12 oct.\nSubject: Alerte\n\nCorps";

        self::assertSame('jobs@acme.test', ForwardedMessage::originalSender($text));
    }

    public function testSupportsEnglishHeadersAndFrenchBanner(): void
    {
        self::assertSame('a@b.test', ForwardedMessage::originalSender("---------- Message transféré ---------\nFrom: a@b.test\n"));
    }

    public function testReturnsNullWhenNotForwarded(): void
    {
        self::assertNull(ForwardedMessage::originalSender("Bonjour\nDe : quelqu'un <x@y.test>"));
        self::assertNull(ForwardedMessage::originalSender(null));
    }

    public function testIsFromMatchesDirectOrForwardedSender(): void
    {
        $direct = new RawEmail(1, 'id1', 'jobs@acme.test', null, null, 'S', null, null, null);
        $forwarded = new RawEmail(2, 'id2', 'me@example.test', null, 'jobs@acme.test', 'S', null, null, null);
        $other = new RawEmail(3, 'id3', 'me@example.test', null, null, 'S', null, null, null);

        self::assertTrue($direct->isFrom('JOBS@acme.test'));
        self::assertTrue($forwarded->isFrom('jobs@acme.test'));
        self::assertFalse($other->isFrom('jobs@acme.test'));
    }
}
