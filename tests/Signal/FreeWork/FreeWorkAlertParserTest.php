<?php

namespace App\Tests\Signal\FreeWork;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\FreeWork\FreeWorkAlertParser;
use PHPUnit\Framework\TestCase;

final class FreeWorkAlertParserTest extends TestCase
{
    private static function tracked(string $url): string
    {
        return 'https://tracker.test/lnk/AAAA/3/xyz/'.rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    private static function offer(string $path, string $title, string $contracts, string $details, string $query = '?utm_source=mail'): string
    {
        return sprintf(
            '<li><a href="%s"><b>%s - <span>%s</span></b><br>   %s </a></li>',
            self::tracked('https://www.free-work.com'.$path.$query),
            $title,
            $contracts,
            $details,
        );
    }

    private static function email(string $items, bool $withHtml = true): RawEmail
    {
        $html = '<html><body><ul>'.$items.'</ul></body></html>';

        return new RawEmail(1, 'id', 'jobs@free-work.com', null, null, '3 offres', new \DateTimeImmutable('2026-10-13 08:40'), null, $withHtml ? $html : null);
    }

    public function testParsesFreelanceOffer(): void
    {
        $email = self::email(self::offer(
            '/fr/tech-it/job-mission/developpeur-php-symfony/developpeur-symfony-senior-3',
            'Développeur Symfony Senior (H/F)',
            'Freelance',
            '12 mois - 450-500 € - Besançon, Bourgogne-Franche-Comté',
        ));

        $signals = (new FreeWorkAlertParser())->parse($email);

        self::assertCount(1, $signals);
        $signal = $signals[0];
        self::assertSame(SignalType::FreelanceMission, $signal->type);
        self::assertSame('free-work', $signal->source);
        self::assertSame('Développeur Symfony Senior (H/F)', $signal->title);
        self::assertSame('https://www.free-work.com/fr/tech-it/job-mission/developpeur-php-symfony/developpeur-symfony-senior-3', $signal->url);
        self::assertNull($signal->companyName);
        self::assertEquals(new \DateTimeImmutable('2026-10-13 08:40'), $signal->observedAt);
        self::assertSame(['Freelance'], $signal->extracted['contracts']);
        self::assertSame('developpeur-php-symfony', $signal->extracted['category']);
        self::assertSame(12, $signal->extracted['durationMonths']);
        self::assertSame('450-500 €', $signal->extracted['dailyRate']);
        self::assertNull($signal->extracted['salary']);
        self::assertSame('Besançon', $signal->extracted['city']);
        self::assertSame('Bourgogne-Franche-Comté', $signal->extracted['region']);
    }

    public function testParsesSalaryAndMixedContracts(): void
    {
        $email = self::email(self::offer(
            '/fr/tech-it/job-mission/developpeur-php-symfony/dev-laravel-1',
            'Développeur Laravel',
            "CDI,\n   Freelance",
            '6 mois - 40k-45k € - 400 € - Paris, Île-de-France',
        ));

        $signal = (new FreeWorkAlertParser())->parse($email)[0];

        self::assertSame(SignalType::FreelanceMission, $signal->type);
        self::assertSame(['CDI', 'Freelance'], $signal->extracted['contracts']);
        self::assertSame('40k-45k €', $signal->extracted['salary']);
        self::assertSame('400 €', $signal->extracted['dailyRate']);
        self::assertSame('Développeur Laravel', $signal->title);
    }

    public function testCdiOnlyIsAJobPostingAndMissingDetailsAreNull(): void
    {
        $email = self::email(self::offer(
            '/fr/tech-it/job-mission/developpeur-php-symfony/dev-wordpress-9',
            'Développeur WordPress',
            'CDI',
            '0 mois - Lyon, Auvergne-Rhône-Alpes',
        ));

        $signal = (new FreeWorkAlertParser())->parse($email)[0];

        self::assertSame(SignalType::JobPosting, $signal->type);
        self::assertNull($signal->extracted['durationMonths']);
        self::assertNull($signal->extracted['dailyRate']);
        self::assertSame('Lyon', $signal->extracted['city']);
    }

    public function testFingerprintIgnoresTrackingQueryAndDiffersPerOffer(): void
    {
        $path = '/fr/tech-it/job-mission/developpeur-php-symfony/dev-a-1';
        $a1 = (new FreeWorkAlertParser())->parse(self::email(self::offer($path, 'A', 'Freelance', '1 mois - Paris, Île-de-France', '?utm=1')))[0];
        $a2 = (new FreeWorkAlertParser())->parse(self::email(self::offer($path, 'A', 'Freelance', '1 mois - Paris, Île-de-France', '?utm=2')))[0];
        $b = (new FreeWorkAlertParser())->parse(self::email(self::offer('/fr/tech-it/job-mission/developpeur-php-symfony/dev-b-2', 'B', 'Freelance', '1 mois - Paris, Île-de-France')))[0];

        self::assertSame($a1->fingerprint, $a2->fingerprint);
        self::assertNotSame($a1->fingerprint, $b->fingerprint);
        self::assertSame(64, \strlen($a1->fingerprint));
    }

    public function testIgnoresNonOfferLinksAndMissingHtml(): void
    {
        $items = '<li><a href="'.self::tracked('https://www.free-work.com/fr/tech-it/jobs?sort=date').'"><b>Voir toutes les offres</b></a></li>'
            .'<li><a href="https://example.test/page"><b>Autre lien</b></a></li>'
            .self::offer('/fr/tech-it/job-mission/devops/ingenieur-devops-4', 'Ingénieur DevOps', 'Freelance', '3 mois - Lille, Hauts-de-France');

        $parser = new FreeWorkAlertParser();

        self::assertCount(1, $parser->parse(self::email($items)));
        self::assertSame([], $parser->parse(self::email($items, false)));
    }
}
