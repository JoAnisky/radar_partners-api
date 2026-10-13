<?php

namespace App\Tests\Api;

use App\Entity\Company;
use App\Enum\Scope;
use App\Enum\SignalType;

final class ScoringApiTest extends ApiTestCase
{
    /** Une entreprise locale gagne 10 points : les tests de règles isolées partent d'une entreprise nationale. */
    private function nationalCompany(string $name): Company
    {
        $company = $this->company($name)->setScope(Scope::National);
        $this->em->flush();

        return $company;
    }

    /** @param array<string, mixed> $body */
    private static function intAt(array $body, string $path): int
    {
        $value = self::at($body, $path);
        self::assertIsInt($value, $path);

        return $value;
    }

    private function score(int $companyId): int
    {
        $score = self::at($this->get('/api/companies/'.$companyId)[1], 'score');
        self::assertIsInt($score);

        return $score;
    }

    private function explanation(int $companyId): string
    {
        return self::str($this->get('/api/companies/'.$companyId)[1], 'scoreExplanation');
    }

    public function testNewCompanyIsScored(): void
    {
        [, $body] = $this->request('POST', '/api/companies', ['name' => 'Acme', 'scope' => 'LOCAL', 'type' => 'WEB_AGENCY']);

        self::assertSame(30, self::at($body, 'score'));
        self::assertStringContainsString('+20 Agence web', self::str($body, 'scoreExplanation'));
        self::assertStringContainsString('Total : 30', self::str($body, 'scoreExplanation'));
    }

    public function testChangingTheTypeToEndClientEliminatesTheCompany(): void
    {
        $id = self::intAt($this->request('POST', '/api/companies', ['name' => 'Acme', 'type' => 'ESN'])[1], 'id');
        self::assertSame(20, $this->score($id));

        [, $body] = $this->request('PATCH', '/api/companies/'.$id, ['type' => 'END_CLIENT']);

        self::assertSame(0, self::at($body, 'score'));
        self::assertStringContainsString('Éliminée : Client final', self::str($body, 'scoreExplanation'));
    }

    public function testInteractionUpdatesTheScore(): void
    {
        $id = (int) $this->nationalCompany('Acme')->getId();

        [, $created] = $this->request('POST', sprintf('/api/companies/%d/interactions', $id), ['channel' => 'PHONE', 'outcome' => 'POSITIVE']);
        self::assertSame(25, self::at($created, 'company.score'));

        $interactionId = self::intAt($created, 'interaction.id');
        [, $edited] = $this->request('PATCH', '/api/interactions/'.$interactionId, ['outcome' => 'NO_ANSWER']);
        self::assertSame(0, self::at($edited, 'company.score'));
    }

    public function testExcludedByOutcomeIsEliminated(): void
    {
        $id = (int) $this->company('Acme')->getId();

        [, $body] = $this->request('POST', sprintf('/api/companies/%d/interactions', $id), ['channel' => 'PHONE', 'outcome' => 'NO_FREELANCE_POLICY']);

        self::assertSame(0, self::at($body, 'company.score'));
        self::assertStringContainsString('Éliminée : Exclue du suivi', $this->explanation($id));
    }

    public function testAttachingAnOfferRaisesTheScoreOfBothCompaniesWhenMoved(): void
    {
        $first = $this->nationalCompany('First');
        $second = $this->nationalCompany('Second');
        $signal = $this->signal('Dev Symfony', SignalType::FreelanceMission);

        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => $first->getId()]);
        self::assertSame(55, $this->score((int) $first->getId()));

        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => $second->getId()]);
        self::assertSame(0, $this->score((int) $first->getId()));
        self::assertSame(55, $this->score((int) $second->getId()));

        $this->request('PATCH', '/api/signals/'.$signal->getId(), ['companyId' => null]);
        self::assertSame(0, $this->score((int) $second->getId()));
    }

    public function testContactsAffectTheScore(): void
    {
        $id = (int) $this->nationalCompany('Acme')->getId();

        [, $contact] = $this->request('POST', sprintf('/api/companies/%d/contacts', $id), ['fullName' => 'Jane', 'email' => 'jane@acme.test']);
        self::assertSame(5, $this->score($id));

        $this->request('DELETE', '/api/contacts/'.self::intAt($contact, 'id'));
        $this->em->clear();
        self::assertSame(0, $this->score($id));
    }

    public function testListCanBeSortedByScore(): void
    {
        $this->request('POST', '/api/companies', ['name' => 'Low']);
        $this->request('POST', '/api/companies', ['name' => 'High', 'scope' => 'LOCAL', 'type' => 'ESN']);
        $this->request('POST', '/api/companies', ['name' => 'Mid', 'scope' => 'LOCAL']);

        self::assertSame(['High', 'Mid', 'Low'], self::names($this->get('/api/companies?sort=score&direction=desc')[1]));
    }
}
