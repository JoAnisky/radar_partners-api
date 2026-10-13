<?php

namespace App\Command;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Scoring\CompanyScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:score:recompute', description: 'Recalcule le score de toutes les entreprises non archivées')]
final readonly class RecomputeScoresCommand
{
    public function __construct(
        private CompanyScorer $scorer,
        private CompanyRepository $companies,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: "Nombre d'entreprises les mieux classées à afficher")] int $top = 10,
        #[Option(description: 'Simule sans écrire en base')] bool $dryRun = false,
    ): int {
        /** @var list<Company> $companies */
        $companies = $this->companies->findBy(['archivedAt' => null]);
        $now = new \DateTimeImmutable();
        $changed = 0;

        foreach ($companies as $company) {
            $before = $company->getScore();
            $this->scorer->rescore($company, $now);
            $changed += (int) ($before !== $company->getScore());
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        usort($companies, static fn (Company $a, Company $b): int => [$b->getScore(), $a->getName()] <=> [$a->getScore(), $b->getName()]);

        $io->table(
            ['Score', 'Entreprise', 'Statut', 'Pourquoi'],
            array_map(
                static fn (Company $c): array => [$c->getScore(), mb_strimwidth($c->getName(), 0, 32, '…'), $c->getStatus()->value, str_replace("\n", ' | ', (string) $c->getScoreExplanation())],
                \array_slice($companies, 0, max(0, $top)),
            ),
        );

        $buckets = array_fill_keys(['0', '1-24', '25-49', '50-74', '75-100'], 0);
        foreach ($companies as $company) {
            $score = $company->getScore();
            ++$buckets[0 === $score ? '0' : ($score < 25 ? '1-24' : ($score < 50 ? '25-49' : ($score < 75 ? '50-74' : '75-100')))];
        }

        $io->writeln('Répartition : '.implode('  ', array_map(static fn (int|string $range, int $n): string => sprintf('%s : %d', $range, $n), array_keys($buckets), $buckets)));
        $io->success(sprintf('%d entreprise(s) scorée(s), %d score(s) modifié(s)%s.', \count($companies), $changed, $dryRun ? ' (dry-run, rien écrit)' : ''));

        return 0;
    }
}
