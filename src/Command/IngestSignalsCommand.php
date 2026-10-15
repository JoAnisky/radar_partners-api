<?php

namespace App\Command;

use App\Signal\IngestOutcome;
use App\Signal\SignalIngester;
use App\Signal\SignalSourceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:signals:ingest', description: 'Lit les sources de signaux et enregistre les nouveaux signaux retenus par le filtre de stack')]
final readonly class IngestSignalsCommand
{
    /** @param iterable<SignalSourceInterface> $sources */
    public function __construct(
        #[AutowireIterator('app.signal_source')]
        private iterable $sources,
        private SignalIngester $ingester,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Depuis cette date (ex. 2026-10-01)')] string $since = '-7 days',
        #[Option(description: 'Simule sans écrire en base')] bool $dryRun = false,
    ): int {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        $counts = array_fill_keys(array_column(IngestOutcome::cases(), 'name'), 0);
        $created = [];

        try {
            foreach ($this->sources as $source) {
                foreach ($source->fetch(new \DateTimeImmutable($since)) as $raw) {
                    $outcome = $this->ingester->ingest($raw);
                    $this->em->flush();
                    ++$counts[$outcome->name];

                    if (IngestOutcome::Created === $outcome) {
                        $created[] = [$raw->source, mb_strimwidth($raw->title, 0, 60, '…'), $raw->extracted['city'] ?? ''];
                    }
                }
            }

            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Ingestion des signaux', $counts + ['dryRun' => $dryRun]);

        if ([] !== $created) {
            $io->table(['Source', 'Titre', 'Ville'], $created);
        }

        $io->success(sprintf(
            '%d créé(s), %d déjà connu(s), %d écarté(s) par le filtre de stack%s.',
            $counts['Created'],
            $counts['Duplicate'],
            $counts['Filtered'],
            $dryRun ? ' (dry-run, rien écrit)' : '',
        ));

        return 0;
    }
}
