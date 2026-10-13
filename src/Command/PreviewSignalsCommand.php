<?php

namespace App\Command;

use App\Signal\SignalSourceInterface;
use App\Signal\StackFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:signals:preview', description: 'Affiche les signaux lus dans les mails et ceux retenus par le filtre de stack (rien n\'est enregistré)')]
final readonly class PreviewSignalsCommand
{
    /** @param iterable<SignalSourceInterface> $sources */
    public function __construct(
        #[AutowireIterator('app.signal_source')]
        private iterable $sources,
        private StackFilter $stackFilter,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Depuis cette date (ex. 2026-10-01)')] string $since = '-7 days',
        #[Option(description: 'Afficher aussi les signaux écartés')] bool $all = false,
    ): int {
        $rows = [];
        $kept = $total = 0;

        foreach ($this->sources as $source) {
            foreach ($source->fetch(new \DateTimeImmutable($since)) as $signal) {
                ++$total;
                $matches = $this->stackFilter->matches($signal);
                $kept += (int) $matches;

                if ($matches || $all) {
                    $rows[] = [
                        $matches ? 'oui' : 'non',
                        $signal->source,
                        mb_strimwidth($signal->title, 0, 60, '…'),
                        $signal->extracted['city'] ?? '',
                        $signal->extracted['dailyRate'] ?? '',
                    ];
                }
            }
        }

        $io->table(['Retenu', 'Source', 'Titre', 'Ville', 'TJM'], $rows);
        $io->success(sprintf('%d signal(aux) lu(s), %d retenu(s) par le filtre de stack.', $total, $kept));

        return 0;
    }
}
