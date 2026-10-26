<?php

namespace App\Tests\Llm;

use App\Demo\DemoSeeder;
use App\Entity\Company;
use App\Llm\ChatContext;
use App\Llm\ChatService;
use App\Llm\Demo\DemoLlmClient;
use App\Llm\LlmMessage;
use App\Llm\Tool\ChatToolInterface;
use App\Llm\Tool\GetCompanyTool;
use App\Llm\Tool\ListSignalsTool;
use App\Llm\Tool\SearchCompaniesTool;
use App\Tests\Api\ApiTestCase;

final class DemoLlmClientTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $seeder = self::getContainer()->get(DemoSeeder::class);
        \assert($seeder instanceof DemoSeeder);
        $seeder->seed();
    }

    private function ask(string $question, ?ChatContext $context = null): \App\Llm\ChatResult
    {
        $container = self::getContainer();
        $client = $container->get(DemoLlmClient::class);
        \assert($client instanceof DemoLlmClient);
        $tools = array_map(static function (string $class) use ($container): ChatToolInterface {
            $tool = $container->get($class);
            \assert($tool instanceof ChatToolInterface);

            return $tool;
        }, [SearchCompaniesTool::class, GetCompanyTool::class, ListSignalsTool::class]);

        return (new ChatService($client, $tools, \dirname(__DIR__, 2).'/config/assistant'))
            ->answer([new LlmMessage(LlmMessage::USER, $question)], new \DateTimeImmutable(), $context);
    }

    /** @return list<string> */
    private static function toolNames(\App\Llm\ChatResult $result): array
    {
        return array_map(static fn ($call): string => $call->name, $result->toolCalls);
    }

    public function testPrioritiesListsTheBestCompaniesToContact(): void
    {
        $result = $this->ask('Quelles entreprises contacter en priorité ?');

        self::assertSame(['search_companies'], self::toolNames($result));
        self::assertStringContainsString('Silex Digital', $result->reply);
        self::assertSame(5, substr_count($result->reply, "\n- "));
    }

    public function testOverdueListsCompaniesToFollowUp(): void
    {
        $result = $this->ask('Quelles relances sont en retard ?');

        self::assertSame(['search_companies'], self::toolNames($result));
        self::assertStringContainsString('Nuage Comtois', $result->reply);
    }

    public function testSignalsListsRecentOffers(): void
    {
        $result = $this->ask('Quelles sont les dernières offres à traiter ?');

        self::assertSame(['list_signals'], self::toolNames($result));
        self::assertStringContainsString('à vérifier', $result->reply);
    }

    public function testSummaryOfANamedCompany(): void
    {
        $result = $this->ask('Résume la fiche de Silex Digital.');

        self::assertSame(['get_company'], self::toolNames($result));
        self::assertStringContainsString('**Silex Digital**', $result->reply);
        self::assertStringContainsString('Offre détectée', $result->reply);
    }

    public function testDraftUsesTheOfferAndTheContact(): void
    {
        $result = $this->ask('Rédige un message d\'approche pour Silex Digital.');

        self::assertSame(['get_company'], self::toolNames($result));
        self::assertStringContainsString('Bonjour Marion', $result->reply);
        self::assertStringContainsString('Objet :', $result->reply);
        self::assertStringContainsString('Symfony', $result->reply);
    }

    public function testDraftFormatFollowsTheRequest(): void
    {
        $linkedin = $this->ask('Rédige un message LinkedIn pour Silex Digital.');

        self::assertStringContainsString('message LinkedIn', $linkedin->reply);
        self::assertStringNotContainsString('Objet :', $linkedin->reply);
    }

    public function testCompanyOpenInThePageIsUsedWhenNoneIsNamed(): void
    {
        $company = $this->em->getRepository(Company::class)->findOneBy(['name' => 'Cobaltis Numérique']);
        \assert($company instanceof Company);

        $result = $this->ask('Résume cette fiche.', new ChatContext($company->getId(), $company->getName(), 'Fiche'));

        self::assertStringContainsString('**Cobaltis Numérique**', $result->reply);
    }

    public function testAskingForACompanyWithoutNamingOneAsksWhich(): void
    {
        $result = $this->ask('Rédige un mail d\'approche.');

        self::assertSame([], self::toolNames($result));
        self::assertStringContainsString('De quelle entreprise', $result->reply);
    }

    public function testUnknownQuestionExplainsWhatTheDemoCanDo(): void
    {
        $result = $this->ask('Quel temps fait-il ?');

        self::assertSame([], self::toolNames($result));
        self::assertStringContainsString('assistant de démonstration', $result->reply);
    }
}
