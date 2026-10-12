<?php

namespace App\Tests\Import;

use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Import\ProspectionRow;
use App\Import\ProspectionRowParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProspectionRowParserTest extends TestCase
{
    /** @param array<string, ?string> $overrides */
    private function parse(array $overrides = []): ProspectionRow
    {
        $row = (new ProspectionRowParser())->parse($overrides + [
            'name' => 'Acme Web',
            'url' => null,
            'address' => null,
            'phone' => null,
            'email' => null,
            'contact' => null,
            'contacted' => null,
            'response' => null,
            'followUp' => null,
            'note' => null,
        ]);
        self::assertNotNull($row);

        return $row;
    }

    public function testEmptyNameIsSkipped(): void
    {
        self::assertNull((new ProspectionRowParser())->parse([
            'name' => ' ', 'url' => null, 'address' => null, 'phone' => null, 'email' => null,
            'contact' => null, 'contacted' => null, 'response' => null, 'followUp' => null, 'note' => null,
        ]));
    }

    public function testHyperlinkFormulaGivesNameAndWebsite(): void
    {
        $row = $this->parse(['name' => '=HYPERLINK("https://acme.test", "Acme Com")']);

        self::assertSame('Acme Com', $row->name);
        self::assertSame('https://acme.test', $row->website);
    }

    public function testAddressIsSplit(): void
    {
        $row = $this->parse(['address' => "12 rue des Lilas, 25000 Besançon\n"]);

        self::assertSame('12 rue des Lilas', $row->address);
        self::assertSame('25000', $row->postalCode);
        self::assertSame('Besançon', $row->city);
    }

    public function testUnparsableAddressIsKeptWithWarning(): void
    {
        $row = $this->parse(['address' => 'Quelque part']);

        self::assertSame('Quelque part', $row->address);
        self::assertNull($row->postalCode);
        self::assertCount(1, $row->warnings);
    }

    public function testContactWithPhoneInParentheses(): void
    {
        $row = $this->parse(['contact' => 'Jane DOE (06 12 34 56 78)']);

        self::assertSame('Jane DOE', $row->contactName);
        self::assertSame('+33612345678', $row->contactPhone);
        self::assertSame([], $row->warnings);
    }

    public function testPhoneOnlyContactHasNoName(): void
    {
        $row = $this->parse(['contact' => '06 12 34 56 78']);

        self::assertNull($row->contactName);
        self::assertSame('+33612345678', $row->contactPhone);
    }

    public function testFirstNameOnlyContactIsReported(): void
    {
        $row = $this->parse(['contact' => 'Jane']);

        self::assertSame('Jane', $row->contactName);
        self::assertCount(1, $row->warnings);
    }

    public function testInvalidPhoneIsReported(): void
    {
        $row = $this->parse(['phone' => '12']);

        self::assertNull($row->phone);
        self::assertCount(1, $row->warnings);
    }

    /** @return iterable<string, array{string, string}> */
    public static function dates(): iterable
    {
        yield 'jj/mm/aa' => ['09/09/26 tel', '2026-09-09'];
        yield 'jj/mm/aaaa' => ['07/07/2026 tel', '2026-07-07'];
        yield 'mm/aa' => ['07/26 tel', '2026-07-01'];
        yield 'année seule' => ['2023 tel', '2023-01-01'];
    }

    #[DataProvider('dates')]
    public function testDateFormats(string $raw, string $expected): void
    {
        $row = $this->parse(['contacted' => $raw]);

        self::assertCount(1, $row->interactions);
        self::assertSame($expected, $row->interactions[0]->occurredAt->format('Y-m-d'));
    }

    public function testAmbiguousDateIsReportedAndKeptInNotes(): void
    {
        $row = $this->parse(['contacted' => '02/10 tel']);

        self::assertSame([], $row->interactions);
        self::assertNotEmpty($row->warnings);
        self::assertStringContainsString('02/10', (string) $row->notes);
    }

    public function testSeveralChannelsOnOneDateGiveSeveralInteractions(): void
    {
        $row = $this->parse(['contacted' => '08/10/26 Tel, puis mail']);

        self::assertSame(
            [InteractionChannel::Phone, InteractionChannel::Email],
            array_map(static fn ($i) => $i->channel, $row->interactions),
        );
    }

    public function testChannelBeforeDateAndSeveralDates(): void
    {
        $row = $this->parse(['contacted' => '2023 physique; 08/10/26 tel', 'followUp' => 'Linkedin 09/10/26']);

        self::assertSame(
            [InteractionChannel::InPerson, InteractionChannel::Phone, InteractionChannel::Linkedin],
            array_map(static fn ($i) => $i->channel, $row->interactions),
        );
    }

    public function testSegmentWithoutChannelIsReported(): void
    {
        $row = $this->parse(['contacted' => '08/10/26 bizarre']);

        self::assertSame([], $row->interactions);
        self::assertCount(1, $row->warnings);
    }

    public function testOriginIsExtracted(): void
    {
        $row = $this->parse(['contacted' => '09/09/26 (Tel suite reco Julien)']);

        self::assertSame('reco Julien', $row->interactions[0]->origin);
    }

    /** @return iterable<string, array{string, InteractionOutcome}> */
    public static function outcomes(): iterable
    {
        yield 'no freelance' => ['Ne travaillent pas avec des freelances', InteractionOutcome::NoFreelancePolicy];
        yield 'meeting' => ['RDV le 17.09.2026 à 11h', InteractionOutcome::MeetingScheduled];
        yield 'just hired' => ['viennent d\'embaucher un dev', InteractionOutcome::JustHired];
        yield 'no need' => ['Pas de besoin dev externe', InteractionOutcome::NoNeed];
        yield 'voicemail' => ['répondeur', InteractionOutcome::Voicemail];
        yield 'no answer' => ['pas de rep', InteractionOutcome::NoAnswer];
        yield 'nobody' => ['personne sur place', InteractionOutcome::NobodyOnSite];
        yield 'unknown' => ['à rappeler', InteractionOutcome::Other];
    }

    #[DataProvider('outcomes')]
    public function testResponseSetsOutcomeOfLatestInteraction(string $response, InteractionOutcome $expected): void
    {
        $row = $this->parse(['contacted' => '01/09/26 mail; 08/10/26 tel', 'response' => $response]);

        self::assertSame(InteractionOutcome::Other, $row->interactions[0]->outcome);
        self::assertSame($expected, $row->interactions[1]->outcome);
    }

    public function testOutcomePriority(): void
    {
        $row = $this->parse(['contacted' => '08/10/26 tel', 'note' => 'pas de besoin, viennent d\'embaucher']);

        self::assertSame(InteractionOutcome::JustHired, $row->interactions[0]->outcome);
    }

    public function testResponseWithoutInteractionGoesToNotes(): void
    {
        $row = $this->parse(['response' => 'Pas de besoin']);

        self::assertSame([], $row->interactions);
        self::assertStringContainsString('Pas de besoin', (string) $row->notes);
        self::assertCount(1, $row->warnings);
    }

    public function testFlagsAreDetectedFromNote(): void
    {
        $row = $this->parse(['note' => 'En difficulté. Encore actif ? Formulaire du site ne fonctionne pas']);

        self::assertEqualsCanonicalizing(['IN_DIFFICULTY', 'MAYBE_INACTIVE', 'BROKEN_CONTACT_FORM'], $row->flags);
        self::assertSame('En difficulté. Encore actif ? Formulaire du site ne fonctionne pas', $row->notes);
    }
}
