<?php

namespace App\Tests\Signal\HelloWork;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\HelloWork\HelloWorkAlertParser;
use PHPUnit\Framework\TestCase;

final class HelloWorkAlertParserTest extends TestCase
{
    /** @param list<string> $tags */
    private static function offer(string $id, string $title, string $company, array $tags, string $badge = ''): string
    {
        $url = 'https://emails.hellowork.com/clic/abc/3/def/'.rtrim(strtr(base64_encode('user@example.test🪢https://www.hellowork.com/fr-fr/emplois/'.$id.'.html?utm_source=jobalert'), '+/', '-_'), '=');
        $links = implode('', array_map(static fn (string $tag): string => '<a href="#">'.$tag.'</a>', $tags));

        return sprintf(
            '<table><tr><td><a href="%1$s"> %2$s </a></td></tr><tr><td>%3$s</td>%4$s</tr><tr><td>%5$s</td></tr><tr><td><a href="%1$s">Voir l’offre</a></td></tr></table>',
            $url,
            $title,
            $company,
            $badge,
            $links,
        );
    }

    private static function email(string $items, string $from = 'alerte@emails.hellowork.com'): RawEmail
    {
        return new RawEmail(1, 'id', $from, null, null, 'Votre alerte', new \DateTimeImmutable('2026-10-21 06:03'), null, '<html><body>'.$items.'</body></html>');
    }

    public function testParsesOffer(): void
    {
        $signals = (new HelloWorkAlertParser())->parse(self::email(
            self::offer('100', 'Développeur Symfony H/F', 'Acme SAS', ['Besançon - 25', 'CDI', ' 40 000 - 50 000 € / an ']),
        ));

        self::assertCount(1, $signals);
        $signal = $signals[0];
        self::assertSame(SignalType::JobPosting, $signal->type);
        self::assertSame('hellowork', $signal->source);
        self::assertSame('Développeur Symfony H/F', $signal->title);
        self::assertSame('https://www.hellowork.com/fr-fr/emplois/100.html', $signal->url);
        self::assertSame('Acme SAS', $signal->companyName);
        self::assertSame('Besançon', $signal->extracted['city']);
        self::assertSame('25', $signal->extracted['department']);
        self::assertSame('CDI', $signal->extracted['contract']);
        self::assertSame('40 000 - 50 000 € / an', $signal->extracted['salary']);
        self::assertNull($signal->extracted['dailyRate']);
        self::assertEquals(new \DateTimeImmutable('2026-10-21 06:03'), $signal->observedAt);
    }

    public function testFreelanceOfferHasDailyRate(): void
    {
        $signal = (new HelloWorkAlertParser())->parse(self::email(
            self::offer('101', 'Développeur PHP H/F', 'Acme', ['Lyon - 69', 'Freelance', '200 - 460 € / jour']),
        ))[0];

        self::assertSame(SignalType::FreelanceMission, $signal->type);
        self::assertSame('200 - 460 € / jour', $signal->extracted['dailyRate']);
        self::assertNull($signal->extracted['salary']);
    }

    public function testIgnoresSuperRecruiterBadge(): void
    {
        $signal = (new HelloWorkAlertParser())->parse(self::email(
            self::offer('102', 'Dev PHP H/F', 'Acme', ['Paris 10e - 75', 'CDI'], '<td>Super recruteur</td>'),
        ))[0];

        self::assertSame('Acme', $signal->companyName);
    }

    public function testFingerprintIsStableAndDeduplicatesWithinMail(): void
    {
        $parser = new HelloWorkAlertParser();
        $items = self::offer('103', 'Dev PHP H/F', 'Acme', ['Lyon - 69']).self::offer('103', 'Dev PHP H/F', 'Acme', ['Lyon - 69']);

        $signals = $parser->parse(self::email($items));

        self::assertCount(1, $signals);
        self::assertSame($signals[0]->fingerprint, $parser->parse(self::email($items))[0]->fingerprint);
    }

    public function testMailWithoutOfferGivesNothing(): void
    {
        $email = self::email('<a href="https://www.hellowork.com/fr-fr/candidat/mon-profil.html">Vérifiez vos informations</a>', 'emploi@emails.hellowork.com');

        self::assertSame([], (new HelloWorkAlertParser())->parse($email));
    }
}
