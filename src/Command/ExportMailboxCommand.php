<?php

namespace App\Command;

use App\Mailbox\ImapMailbox;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:mailbox:export', description: 'Exporte les mails décodés (texte, HTML, métadonnées) pour écrire les parseurs')]
final readonly class ExportMailboxCommand
{
    public function __construct(private ImapMailbox $mailbox, private Filesystem $filesystem)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Dossier IMAP')] string $folder = 'INBOX',
        #[Option(description: 'Dossier de sortie (ne pas committer)')] string $dir = 'var/import/samples',
    ): int {
        $this->filesystem->mkdir($dir);
        $count = 0;

        foreach ($this->mailbox->fetch($folder) as $email) {
            $base = sprintf('%s/%s-uid%d', $dir, $email->receivedAt?->format('Ymd-His') ?? 'nodate', $email->uid);
            $this->filesystem->dumpFile($base.'.meta.json', (string) json_encode([
                'uid' => $email->uid,
                'messageId' => $email->messageId,
                'from' => $email->fromAddress,
                'fromName' => $email->fromName,
                'originalFrom' => $email->originalFromAddress,
                'subject' => $email->subject,
                'receivedAt' => $email->receivedAt?->format(\DATE_ATOM),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
            if (null !== $email->text) {
                $this->filesystem->dumpFile($base.'.txt', $email->text);
            }
            if (null !== $email->html) {
                $this->filesystem->dumpFile($base.'.html', $email->html);
            }
            ++$count;
        }

        $io->success(sprintf('%d mail(s) exporté(s) dans %s.', $count, $dir));

        return 0;
    }
}
