<?php

namespace App\Command;

use App\Import\ProspectionImporter;
use App\Import\ProspectionRowParser;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:prospection', description: 'Importe le tableur de prospection')]
final readonly class ImportProspectionCommand
{
    public function __construct(
        private ProspectionRowParser $parser,
        private ProspectionImporter $importer,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Fichier .xlsx')] string $file,
        #[Option(description: 'Simule sans écrire en base')] bool $dryRun = false,
    ): int {
        if (!is_file($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return 1;
        }

        $sheet = IOFactory::load($file)->getActiveSheet();
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        $created = $skipped = 0;
        $report = [];

        try {
            for ($r = 2; $r <= $sheet->getHighestDataRow(); ++$r) {
                $name = $sheet->getCell("A$r");
                $row = $this->parser->parse([
                    'name' => $this->text($name->getValue()),
                    'url' => $name->hasHyperlink() ? $name->getHyperlink()->getUrl() : null,
                    'address' => $this->text($sheet->getCell("B$r")->getValue()),
                    'phone' => $this->text($sheet->getCell("C$r")->getValue()),
                    'email' => $this->text($sheet->getCell("D$r")->getValue()),
                    'contact' => $this->text($sheet->getCell("E$r")->getValue()),
                    'contacted' => $this->text($sheet->getCell("F$r")->getValue()),
                    'response' => $this->text($sheet->getCell("G$r")->getValue()),
                    'followUp' => $this->text($sheet->getCell("H$r")->getValue()),
                    'note' => $this->text($sheet->getCell("I$r")->getValue()),
                ]);

                if (null === $row) {
                    continue;
                }

                if (null === $this->importer->import($row)) {
                    ++$skipped;
                    $report[] = [$r, $row->name, 'Déjà existante, ignorée'];
                    continue;
                }

                $this->em->flush();
                ++$created;
                foreach ($row->warnings as $warning) {
                    $report[] = [$r, $row->name, $warning];
                }
            }

            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        if ([] !== $report) {
            $io->section('Lignes à vérifier');
            $io->table(['Ligne', 'Entreprise', 'Remarque'], $report);
        }

        $io->success(sprintf('%d créée(s), %d ignorée(s)%s.', $created, $skipped, $dryRun ? ' (dry-run, rien écrit)' : ''));

        return 0;
    }

    private function text(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
