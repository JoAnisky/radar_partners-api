<?php

namespace App\Signal\LinkedIn;

use App\Mailbox\ImapMailbox;
use App\Signal\SignalSourceInterface;

final readonly class LinkedInEmailSource implements SignalSourceInterface
{
    public function __construct(private ImapMailbox $mailbox, private LinkedInAlertParser $parser)
    {
    }

    public function getName(): string
    {
        return LinkedInAlertParser::SOURCE;
    }

    public function fetch(\DateTimeImmutable $since): iterable
    {
        foreach ($this->mailbox->fetch('INBOX', $since) as $email) {
            if ($email->isFrom(LinkedInAlertParser::SENDER)) {
                yield from $this->parser->parse($email);
            }
        }
    }
}
