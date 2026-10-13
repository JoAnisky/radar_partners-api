<?php

namespace App\Stats;

use App\Api\CompanyListQuery;
use App\Api\CompanyPresenter;
use App\Api\SignalListQuery;
use App\Entity\Company;
use App\Enum\PipelineStatus;
use App\Repository\CompanyRepository;
use App\Repository\SignalRepository;
use Doctrine\DBAL\Connection;

/** Chiffres de l'accueil : indicateurs, séries hebdomadaires sur 12 semaines, pipeline, avancement et listes. */
final readonly class StatsProvider
{
    private const WEEKS = 12;
    private const LIST_SIZE = 5;

    public function __construct(
        private Connection $db,
        private CompanyRepository $companies,
        private SignalRepository $signals,
        private CompanyPresenter $presenter,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(\DateTimeImmutable $now): array
    {
        $weeks = $this->weeks($now);
        $from = $weeks[0]->format('Y-m-d');

        $interactions = $this->weekly('interaction', 'occurred_at', $weeks, $from);
        $signals = $this->weekly('signal', 'detected_at', $weeks, $from);
        $newCompanies = $this->weekly('company', 'created_at', $weeks, $from, 'archived_at IS NULL');
        $before = $this->count('SELECT COUNT(*) FROM company WHERE archived_at IS NULL AND created_at < :from', ['from' => $from]);

        $interactions30 = $this->period('interaction', 'occurred_at', $now, 30);
        $interactionsPrevious30 = $this->period('interaction', 'occurred_at', $now, 60, 30);
        $signals30 = $this->period('signal', 'detected_at', $now, 30);
        $signalsPrevious30 = $this->period('signal', 'detected_at', $now, 60, 30);

        $due = $this->companies->search(new CompanyListQuery(due: true, sort: 'recontactAfter', perPage: self::LIST_SIZE), $now);
        $priorities = $this->companies->search(new CompanyListQuery(status: PipelineStatus::ToContact, sort: 'score', direction: 'desc', perPage: self::LIST_SIZE), $now);
        $recentSignals = $this->signals->search(new SignalListQuery(perPage: self::LIST_SIZE));
        $unresolved = $this->signals->search(new SignalListQuery(unresolved: true, perPage: 1));

        return [
            'weeks' => array_map(static fn (\DateTimeImmutable $week): string => $week->format('Y-m-d'), $weeks),
            'kpis' => [
                'companies' => [
                    'total' => $this->count('SELECT COUNT(*) FROM company WHERE archived_at IS NULL'),
                    'createdLast7Days' => $this->count('SELECT COUNT(*) FROM company WHERE archived_at IS NULL AND created_at >= :since', ['since' => $now->modify('-7 days')->format('Y-m-d H:i:s')]),
                    'series' => $this->cumulative($newCompanies, $before),
                ],
                'due' => ['total' => $due['total']],
                'interactions' => [
                    'last30Days' => $interactions30,
                    'previous30Days' => $interactionsPrevious30,
                    'deltaPercent' => self::delta($interactions30, $interactionsPrevious30),
                    'series' => $interactions,
                ],
                'signals' => [
                    'last30Days' => $signals30,
                    'previous30Days' => $signalsPrevious30,
                    'deltaPercent' => self::delta($signals30, $signalsPrevious30),
                    'unresolved' => $unresolved['total'],
                    'series' => $signals,
                ],
            ],
            'activity' => ['interactions' => $interactions, 'signals' => $signals, 'companies' => $newCompanies],
            'pipeline' => $this->pipeline(),
            'progress' => [
                'total' => $this->count('SELECT COUNT(*) FROM company WHERE archived_at IS NULL'),
                'contacted' => $this->count('SELECT COUNT(DISTINCT i.company_id) FROM interaction i JOIN company c ON c.id = i.company_id WHERE c.archived_at IS NULL'),
                'reachable' => $this->count('SELECT COUNT(DISTINCT ct.company_id) FROM contact ct JOIN company c ON c.id = ct.company_id WHERE c.archived_at IS NULL AND (ct.phone IS NOT NULL OR ct.email IS NOT NULL)'),
                'typed' => $this->count("SELECT COUNT(*) FROM company WHERE archived_at IS NULL AND type <> 'UNKNOWN'"),
            ],
            'lists' => [
                'due' => $this->summaries($due['items']),
                'priorities' => $this->summaries($priorities['items']),
                'recentSignals' => array_map($this->presenter->signal(...), $recentSignals['items']),
            ],
        ];
    }

    /** @return list<\DateTimeImmutable> lundis des 12 dernières semaines, la semaine en cours en dernier */
    private function weeks(\DateTimeImmutable $now): array
    {
        $current = $now->modify('monday this week')->setTime(0, 0);

        return array_map(
            static fn (int $i): \DateTimeImmutable => $current->modify(sprintf('-%d weeks', self::WEEKS - 1 - $i)),
            range(0, self::WEEKS - 1),
        );
    }

    /**
     * @param list<\DateTimeImmutable> $weeks
     *
     * @return list<int>
     */
    private function weekly(string $table, string $column, array $weeks, string $from, string $where = '1 = 1'): array
    {
        /** @var array<string, int|string> $rows */
        $rows = $this->db->fetchAllKeyValue(sprintf(
            "SELECT to_char(date_trunc('week', %2\$s), 'YYYY-MM-DD') AS week, COUNT(*) AS n FROM %1\$s WHERE %2\$s >= :from AND %3\$s GROUP BY 1",
            $table,
            $column,
            $where,
        ), ['from' => $from]);

        return array_map(static fn (\DateTimeImmutable $week): int => (int) ($rows[$week->format('Y-m-d')] ?? 0), $weeks);
    }

    /** Nombre de lignes dont la date est entre « il y a $to jours » et « il y a $skip jours ». */
    private function period(string $table, string $column, \DateTimeImmutable $now, int $to, int $skip = 0): int
    {
        return $this->count(
            sprintf('SELECT COUNT(*) FROM %1$s WHERE %2$s >= :start AND %2$s < :end', $table, $column),
            [
                'start' => $now->modify(sprintf('-%d days', $to))->format('Y-m-d H:i:s'),
                'end' => (0 === $skip ? $now->modify('+1 second') : $now->modify(sprintf('-%d days', $skip)))->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * @param list<int> $perWeek
     *
     * @return list<int>
     */
    private function cumulative(array $perWeek, int $before): array
    {
        $total = $before;

        return array_map(static function (int $n) use (&$total): int {
            $total += $n;

            return $total;
        }, $perWeek);
    }

    /** @return list<array{status: string, count: int}> */
    private function pipeline(): array
    {
        /** @var array<string, int|string> $rows */
        $rows = $this->db->fetchAllKeyValue('SELECT status, COUNT(*) FROM company WHERE archived_at IS NULL GROUP BY status');

        return array_map(
            static fn (PipelineStatus $status): array => ['status' => $status->value, 'count' => (int) ($rows[$status->value] ?? 0)],
            PipelineStatus::cases(),
        );
    }

    /**
     * @param list<Company> $companies
     *
     * @return list<array<string, mixed>>
     */
    private function summaries(array $companies): array
    {
        $last = $this->companies->lastInteractionDates($companies);

        return array_map(fn (Company $c): array => $this->presenter->summary($c, $last[$c->getId()] ?? null), $companies);
    }

    /** @param array<string, mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        $value = $this->db->fetchOne($sql, $params);

        return \is_numeric($value) ? (int) $value : 0;
    }

    private static function delta(int $current, int $previous): ?float
    {
        return 0 === $previous ? null : round(($current - $previous) / $previous * 100, 1);
    }
}
