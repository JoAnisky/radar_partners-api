<?php

namespace App\Demo;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use App\Entity\Signal;
use App\Scoring\CompanyScorer;
use App\Service\PipelineStatusResolver;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/** Remplace tout le contenu de la base par le jeu de données fictif. */
final readonly class DemoSeeder
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $db,
        private CompanyScorer $scorer,
        private PipelineStatusResolver $resolver,
    ) {
    }

    /** @return array{companies: int, contacts: int, interactions: int, signals: int} */
    public function seed(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $this->purge();

        $counts = ['companies' => 0, 'contacts' => 0, 'interactions' => 0, 'signals' => 0];
        $phone = 0;

        foreach (DemoDataset::companies() as $index => $row) {
            $company = (new Company($row['name']))
                ->setType($row['type'])
                ->setCity($row['city'])
                ->setPostalCode($row['postalCode'])
                ->setHeadcountRange($row['size'])
                ->setNafCode($row['naf'] ?? null)
                ->setFlags($row['flags'] ?? [])
                ->setNotes($row['notes'] ?? null)
                ->setWebsite(sprintf('https://www.%s.example', self::slug($row['name'])));
            if (isset($row['scope'])) {
                $company->setScope($row['scope']);
            }
            $this->em->persist($company);
            ++$counts['companies'];

            $contacts = [];
            foreach ($row['contacts'] ?? [] as [$fullName, $role]) {
                $contact = (new Contact($company, $fullName))
                    ->setRole($role)
                    ->setEmail(sprintf('%s@%s.example', self::slug($fullName, '.'), self::slug($row['name'])))
                    ->setPhone(sprintf('09 75 18 %02d %02d', intdiv(++$phone, 100) + 10, $phone % 100));
                $this->em->persist($contact);
                $contacts[] = $contact;
                ++$counts['contacts'];
            }

            $history = [];
            foreach ($row['interactions'] ?? [] as $item) {
                [$days, $channel, $outcome, $contactIndex, $origin, $notes] = $item;
                $next = $item[6] ?? null;
                $interaction = (new Interaction($company, $channel, self::daysAgo($now, $days)))
                    ->setOutcome($outcome)
                    ->setOrigin($origin)
                    ->setNotes($notes)
                    ->setContact(null === $contactIndex ? null : $contacts[$contactIndex])
                    ->setNextActionAt(null === $next ? null : $now->modify(sprintf('%+d days', $next))->setTime(9, 0));
                $this->em->persist($interaction);
                $history[] = $interaction;
                ++$counts['interactions'];
            }
            $this->resolver->apply($company, $history);
            if (isset($row['status'])) {
                $company->setStatus($row['status']);
            }

            foreach ($row['signals'] ?? [] as $i => $item) {
                [$days, $title, $source, $type] = $item;
                $extracted = $item[4] ?? [];
                $this->em->persist($this->signal($now, $days, $title, $source, $type, $extracted, sprintf('%s-%d', $row['name'], $i))->setCompany($company));
                ++$counts['signals'];
            }
        }

        foreach (DemoDataset::unresolvedSignals() as $i => $row) {
            [$days, $title, $source, $type, $extracted] = $row;
            $signal = $this->signal($now, $days, $title, $source, $type, $extracted, sprintf('unresolved-%d', $i));
            if ($row[5] ?? false) {
                $signal->setDismissedAt($now);
            }
            $this->em->persist($signal);
            ++$counts['signals'];
        }

        $this->em->flush();
        $this->backdateCreation($now);
        $this->rescore($now);

        return $counts;
    }

    private function purge(): void
    {
        foreach ([Signal::class, Interaction::class, Contact::class, Company::class] as $class) {
            $this->em->createQuery(sprintf('DELETE FROM %s e', $class))->execute();
        }
        $this->em->clear();
    }

    /** @param array<string, mixed> $extracted */
    private function signal(\DateTimeImmutable $now, int $days, string $title, string $source, \App\Enum\SignalType $type, array $extracted, string $key): Signal
    {
        $detectedAt = self::daysAgo($now, $days);

        return (new Signal($type, $source, sha1('demo-'.$key.$title)))
            ->setTitle($title)
            ->setUrl(sprintf('https://%s.example/offres/%s', $source, substr(sha1($key), 0, 8)))
            ->setExtracted($extracted)
            ->setPublishedAt($detectedAt)
            ->setDetectedAt($detectedAt);
    }

    /** Étale les créations d'entreprises sur les semaines passées (sinon toutes tombent aujourd'hui). */
    private function backdateCreation(\DateTimeImmutable $now): void
    {
        $ids = $this->db->fetchFirstColumn('SELECT id FROM company ORDER BY id');
        foreach ($ids as $i => $id) {
            $date = $now->modify(sprintf('-%d days', 5 + ($i * 13) % 80))->format('Y-m-d H:i:s');
            $this->db->executeStatement('UPDATE company SET created_at = :d, updated_at = :d WHERE id = :id', ['d' => $date, 'id' => $id]);
        }
    }

    private function rescore(\DateTimeImmutable $now): void
    {
        foreach ($this->em->getRepository(Company::class)->findAll() as $company) {
            $this->scorer->rescore($company, $now);
        }
        $this->em->flush();
    }

    private static function daysAgo(\DateTimeImmutable $now, int $days): \DateTimeImmutable
    {
        return $now->modify(sprintf('-%d days', $days))->setTime(10, 0);
    }

    private static function slug(string $text, string $separator = ''): string
    {
        $ascii = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $text));

        return trim((string) preg_replace('/[^a-z0-9]+/', $separator, $ascii), $separator);
    }
}
