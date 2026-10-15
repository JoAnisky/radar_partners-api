<?php

namespace App\Tests;

use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\Checkpoint;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

final class ScheduleTest extends TestCase
{
    private ArrayAdapter $cache;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->clock = new MockClock('2026-10-15 08:00:00', 'Europe/Paris');
    }

    /** Un nouveau processus (redémarrage du worker) partage l'état, pas le générateur. */
    private function newProcess(): MessageGenerator
    {
        $provider = new Schedule($this->cache);
        $schedule = $provider->getSchedule();

        return new MessageGenerator($provider, 'default', $this->clock, new Checkpoint('scheduler_default', $schedule->getLock(), $schedule->getState()));
    }

    /** @return list<object> messages émis en interrogeant le générateur à chaque seconde pendant $seconds */
    private function poll(MessageGenerator $generator, int $seconds = 3): array
    {
        $messages = [];
        for ($i = 0; $i < $seconds; ++$i) {
            foreach ($generator->getMessages() as $message) {
                $messages[] = $message;
            }
            $this->clock->sleep(1);
        }

        return $messages;
    }

    public function testIngestsEveryDayAtNineParisTime(): void
    {
        $generator = $this->newProcess();
        $before = $this->poll($generator);
        self::assertSame([], $before);

        $this->clock->modify('2026-10-15 08:59:58');
        $messages = $this->poll($generator);

        self::assertCount(1, $messages);
        $message = $messages[0] ?? null;
        self::assertInstanceOf(RunCommandMessage::class, $message);
        self::assertStringContainsString('app:signals:ingest', $message->input);
    }

    public function testMissedRunsAreCaughtUpOnceAfterARestart(): void
    {
        // Le worker a ingéré jeudi à 9h, puis le PC reste éteint 4 jours (passages manqués : vendredi à lundi 9h).
        $this->clock->modify('2026-10-15 08:59:58');
        $first = $this->poll($this->newProcess(), 5);
        self::assertCount(1, $first);

        $this->clock->modify('2026-10-19 10:00:00');
        $restarted = $this->newProcess();

        self::assertCount(1, $this->poll($restarted, 5));
        self::assertSame([], $this->poll($restarted, 5), 'aucun doublon dans le même processus');
    }

    public function testNothingIsCaughtUpWhenNoRunWasMissed(): void
    {
        $this->poll($this->newProcess());

        $this->clock->modify('2026-10-15 08:30:00');

        self::assertSame([], $this->poll($this->newProcess(), 5));
    }
}
