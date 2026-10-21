<?php

namespace App\Signal\HelloWork;

use App\Mailbox\ImapMailbox;
use App\Signal\SignalSourceInterface;

final readonly class HelloWorkEmailSource implements SignalSourceInterface
{
    public function __construct(private ImapMailbox $mailbox, private HelloWorkAlertParser $parser)
    {
    }

    public function getName(): string
    {
        return HelloWorkAlertParser::SOURCE;
    }

    public function fetch(\DateTimeImmutable $since): iterable
    {
        foreach ($this->mailbox->fetch('INBOX', $since) as $email) {
            foreach (HelloWorkAlertParser::SENDERS as $sender) {
                if ($email->isFrom($sender)) {
                    yield from $this->parser->parse($email);
                    break;
                }
            }
        }
    }
}
