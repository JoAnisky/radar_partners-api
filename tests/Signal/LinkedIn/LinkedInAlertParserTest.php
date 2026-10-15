<?php

namespace App\Tests\Signal\LinkedIn;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\LinkedIn\LinkedInAlertParser;
use PHPUnit\Framework\TestCase;

final class LinkedInAlertParserTest extends TestCase
{
    private static function offer(string $id, string $title, string $details): string
    {
        $url = 'https://www.linkedin.com/comm/jobs/view/'.$id.'/?trackingId=abc';

        return sprintf(
            '<table><tr><td><a href="%1$s"><img alt="logo"></a></td><td><a href="%1$s"><table><tr><td><a href="%1$s"> %2$s </a></td></tr><tr><td><p> %3$s </p></td></tr></table></a></td></tr></table>',
            $url,
            $title,
            $details,
        );
    }

    private static function email(string $items, string $subject = 'Développeur chez Acme'): RawEmail
    {
        return new RawEmail(1, 'id', 'jobalerts-noreply@linkedin.com', null, null, $subject, new \DateTimeImmutable('2026-10-15 08:49'), null, '<html><body>'.$items.'</body></html>');
    }

    public function testParsesOffer(): void
    {
        $signals = (new LinkedInAlertParser())->parse(self::email(
            self::offer('100', 'Développeur Symfony', 'Acme SAS · Besançon (Hybride)'),
        ));

        self::assertCount(1, $signals);
        $signal = $signals[0];
        self::assertSame(SignalType::JobPosting, $signal->type);
        self::assertSame('linkedin', $signal->source);
        self::assertSame('Développeur Symfony', $signal->title);
        self::assertSame('https://www.linkedin.com/jobs/view/100/', $signal->url);
        self::assertSame('Acme SAS', $signal->companyName);
        self::assertSame('Besançon', $signal->extracted['location']);
        self::assertSame('Hybride', $signal->extracted['workMode']);
        self::assertArrayNotHasKey('remote', $signal->extracted);
        self::assertEquals(new \DateTimeImmutable('2026-10-15 08:49'), $signal->observedAt);
    }

    public function testDetectsFreelanceAndRemote(): void
    {
        $signal = (new LinkedInAlertParser())->parse(self::email(
            self::offer('101', 'Développeur Freelance PHP', 'Acme · France (À distance)'),
        ))[0];

        self::assertSame(SignalType::FreelanceMission, $signal->type);
        self::assertTrue($signal->extracted['remote']);
    }

    public function testKeepsSeparatorInsideCompanyName(): void
    {
        $signal = (new LinkedInAlertParser())->parse(self::email(
            self::offer('102', 'Dev PHP', 'Acme · Carrière · Lyon'),
        ))[0];

        self::assertSame('Acme · Carrière', $signal->companyName);
        self::assertSame('Lyon', $signal->extracted['location']);
    }

    public function testFingerprintIsStableAndDeduplicatesWithinMail(): void
    {
        $parser = new LinkedInAlertParser();
        $items = self::offer('103', 'Dev PHP', 'Acme · Lyon').self::offer('103', 'Dev PHP', 'Acme · Lyon');

        $signals = $parser->parse(self::email($items));

        self::assertCount(1, $signals);
        self::assertSame($signals[0]->fingerprint, $parser->parse(self::email($items))[0]->fingerprint);
    }

    public function testIgnoresAlertCreatedMail(): void
    {
        $email = self::email(self::offer('104', 'Dev PHP', 'Acme · Lyon'), 'Fwd: Jonathan : votre alerte a été créée');

        self::assertSame([], (new LinkedInAlertParser())->parse($email));
    }
}
