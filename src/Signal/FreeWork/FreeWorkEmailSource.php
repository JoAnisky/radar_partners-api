<?php

namespace App\Signal\FreeWork;

use App\Mailbox\ImapMailbox;
use App\Signal\SignalSourceInterface;

final readonly class FreeWorkEmailSource implements SignalSourceInterface
{
    public function __construct(private ImapMailbox $mailbox, private FreeWorkAlertParser $parser)
    {
    }

    public function getName(): string
    {
        return FreeWorkAlertParser::SOURCE;
    }

    public function fetch(\DateTimeImmutable $since): iterable
    {
        foreach ($this->mailbox->fetch('INBOX', $since) as $email) {
            if ($email->isFrom(FreeWorkAlertParser::SENDER)) {
                yield from $this->parser->parse($email);
            }
        }
    }
}
