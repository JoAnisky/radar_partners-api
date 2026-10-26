<?php

namespace App\Command;

use App\Demo\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:demo:seed', description: 'Remplace toute la base par les données fictives de la démo (APP_DEMO_MODE=true requis)')]
final readonly class SeedDemoCommand
{
    public function __construct(
        private DemoSeeder $seeder,
        #[Autowire('%app.demo_mode%')]
        private bool $demoMode,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!$this->demoMode) {
            $io->error('Refusé : la commande efface toute la base, elle ne tourne qu\'avec APP_DEMO_MODE=true.');

            return 1;
        }

        $counts = $this->seeder->seed();
        $io->success(sprintf('%d entreprise(s), %d contact(s), %d interaction(s), %d signal(aux).', ...array_values($counts)));

        return 0;
    }
}
