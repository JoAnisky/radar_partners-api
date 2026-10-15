<?php

namespace App\Tests\Api;

use App\Enum\PipelineStatus;
use App\Enum\SignalType;
use App\Llm\Tool\GetCompanyTool;
use App\Llm\Tool\ListSignalsTool;
use App\Llm\Tool\SearchCompaniesTool;

final class ChatToolsTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function runTool(string $tool, array $arguments = []): array
    {
        $service = self::getContainer()->get($tool);
        \assert($service instanceof SearchCompaniesTool || $service instanceof GetCompanyTool || $service instanceof ListSignalsTool);

        return $service->execute($arguments);
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(array $result, string $key): array
    {
        $rows = $result[$key] ?? null;
        \assert(\is_array($rows));

        /** @var list<array<string, mixed>> $list */
        $list = array_values($rows);

        return $list;
    }

    public function testSearchHidesPartnersAndFiltersByQuery(): void
    {
        $this->company('Acme Studio', 'Besançon');
        $this->company('Partner Co')->setStatus(PipelineStatus::Partner);
        $this->company('Autre');
        $this->em->flush();

        $all = $this->runTool(SearchCompaniesTool::class);
        $names = array_column(self::rows($all, 'companies'), 'name');
        sort($names);
        self::assertSame(['Acme Studio', 'Autre'], $names);

        $filtered = $this->runTool(SearchCompaniesTool::class, ['query' => 'besancon']);
        self::assertSame(['Acme Studio'], array_column(self::rows($filtered, 'companies'), 'name'));
    }

    public function testSearchCapsTheLimit(): void
    {
        foreach (range(1, 25) as $i) {
            $this->company('Company '.$i);
        }

        self::assertCount(20, self::rows($this->runTool(SearchCompaniesTool::class, ['limit' => 500]), 'companies'));
    }

    public function testGetCompanyByNameOrId(): void
    {
        $company = $this->company('Acme Studio');
        $this->interaction($company, '-2 days');

        $byName = $this->runTool(GetCompanyTool::class, ['name' => 'acme']);
        self::assertSame('Acme Studio', $byName['name']);
        self::assertCount(1, self::rows($byName, 'interactions'));
        self::assertSame(2, $byName['daysSinceLastInteraction']);
        self::assertNull($byName['nextActionOverdueByDays']);
        self::assertSame(2, self::rows($byName, 'interactions')[0]['daysAgo']);
        self::assertSame($byName['id'], $this->runTool(GetCompanyTool::class, ['id' => $company->getId()])['id']);
        self::assertArrayHasKey('error', $this->runTool(GetCompanyTool::class, ['name' => 'inconnue']));
        self::assertArrayHasKey('error', $this->runTool(GetCompanyTool::class));
    }

    public function testGetCompanyReportsOverdueDates(): void
    {
        $company = $this->company('Acme Studio')->setRecontactAfter(new \DateTimeImmutable('-4 days'));
        $this->interaction($company, '-10 days', '-3 days');
        $this->interaction($this->company('Futur'), '-1 day', '+5 days');

        $overdue = $this->runTool(GetCompanyTool::class, ['name' => 'Acme Studio']);
        self::assertSame(10, $overdue['daysSinceLastInteraction']);
        self::assertSame(3, $overdue['nextActionOverdueByDays']);
        self::assertSame(4, $overdue['recontactOverdueByDays']);

        self::assertNull($this->runTool(GetCompanyTool::class, ['name' => 'Futur'])['nextActionOverdueByDays']);
    }

    public function testListSignalsByView(): void
    {
        $this->signal('Dev PHP');
        $this->signal('Ancienne', SignalType::JobPosting)->setDismissedAt(new \DateTimeImmutable());
        $this->em->flush();

        self::assertSame(['Dev PHP'], array_column(self::rows($this->runTool(ListSignalsTool::class), 'signals'), 'title'));
        self::assertSame(['Ancienne'], array_column(self::rows($this->runTool(ListSignalsTool::class, ['view' => 'archived']), 'signals'), 'title'));
    }

    public function testChatEndpointValidatesMessages(): void
    {
        foreach ([[], ['messages' => []], ['messages' => [['role' => 'system', 'content' => 'x']]], ['messages' => [['role' => 'user', 'content' => ' ']]], ['messages' => [['role' => 'assistant', 'content' => 'x']]]] as $payload) {
            self::assertSame(422, $this->request('POST', '/api/chat', $payload)[0], (string) json_encode($payload));
        }
    }
}
