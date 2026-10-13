<?php

namespace App\Command;

use App\Mailbox\ImapMailbox;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mailbox:list', description: 'Liste les mails de la boîte dédiée (sans les marquer lus)')]
final readonly class ListMailboxCommand
{
    public function __construct(private ImapMailbox $mailbox)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Dossier IMAP')] string $folder = 'INBOX',
        #[Option(description: 'Depuis cette date (ex. 2026-10-01)')] ?string $since = null,
    ): int {
        $rows = [];
        foreach ($this->mailbox->fetch($folder, null === $since ? null : new \DateTimeImmutable($since)) as $email) {
            $rows[] = [
                $email->uid,
                $email->receivedAt?->format('Y-m-d H:i'),
                $email->originalFromAddress ?? $email->fromAddress,
                mb_strimwidth($email->subject, 0, 70, '…'),
                null === $email->text ? '-' : 'texte',
                null === $email->html ? '-' : 'html',
            ];
        }

        $io->table(['UID', 'Date', 'Expéditeur (d\'origine)', 'Objet', '', ''], $rows);
        $io->success(sprintf('%d mail(s).', \count($rows)));

        return 0;
    }
}
