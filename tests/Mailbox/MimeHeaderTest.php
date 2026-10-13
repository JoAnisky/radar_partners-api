<?php

namespace App\Tests\Mailbox;

use App\Mailbox\MimeHeader;
use PHPUnit\Framework\TestCase;

final class MimeHeaderTest extends TestCase
{
    public function testDecodesQuotedPrintable(): void
    {
        self::assertSame('Fwd: 13 offres matchant avec vos critères', MimeHeader::decode('=?UTF-8?Q?Fwd=3A_13_offres_matchant_avec_vos_crit=C3=A8res?='));
    }

    public function testDecodesSeveralBase64Words(): void
    {
        $encoded = '=?UTF-8?B?QWxlcnRlIMOpdMOp?= =?UTF-8?B?IGNyw6nDqWU=?=';

        self::assertSame('Alerte été créée', MimeHeader::decode($encoded));
    }

    public function testKeepsPlainTextAndHandlesEmpty(): void
    {
        self::assertSame('Plain subject', MimeHeader::decode('Plain subject'));
        self::assertSame('', MimeHeader::decode(null));
        self::assertSame('', MimeHeader::decode(''));
    }
}
