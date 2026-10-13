<?php

namespace App\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/** Lecture seule de la boîte dédiée : les mails restent non lus. */
final class ImapMailbox
{
    private ?Client $client = null;

    public function __construct(
        #[Autowire(env: 'IMAP_HOST')] private readonly string $host,
        #[Autowire(env: 'int:IMAP_PORT')] private readonly int $port,
        #[Autowire(env: 'IMAP_USER')] private readonly string $user,
        #[Autowire(env: 'IMAP_PASSWORD')] private readonly string $password,
    ) {
    }

    /** @return \Generator<RawEmail> */
    public function fetch(string $folder = 'INBOX', ?\DateTimeImmutable $since = null): \Generator
    {
        $query = $this->client()->getFolder($folder)?->messages()->all()->leaveUnread();
        if (null === $query) {
            throw new \RuntimeException(sprintf('Dossier IMAP introuvable : %s', $folder));
        }

        if (null !== $since) {
            $query->since($since->format('d-M-Y'));
        }

        foreach ($query->get() as $message) {
            if ($message instanceof Message) {
                yield $this->toRawEmail($message);
            }
        }
    }

    private function client(): Client
    {
        if (null === $this->client) {
            $this->client = (new ClientManager())->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => 'ssl',
                'validate_cert' => true,
                'username' => $this->user,
                'password' => $this->password,
                'protocol' => 'imap',
            ]);
            $this->client->connect();
        }

        return $this->client;
    }

    private function toRawEmail(Message $message): RawEmail
    {
        $from = $message->getFrom()[0] ?? null;
        $from = $from instanceof Address ? $from : null;
        $date = $message->getDate()->first();
        $text = $message->hasTextBody() ? (string) $message->getTextBody() : null;
        $html = $message->hasHTMLBody() ? (string) $message->getHTMLBody() : null;

        return new RawEmail(
            (int) $message->getUid(),
            (string) $message->getMessageId(),
            $from?->mail,
            null === $from?->personal ? null : MimeHeader::decode($from->personal),
            ForwardedMessage::originalSender($text),
            MimeHeader::decode((string) $message->getSubject()),
            $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : null,
            $text,
            $html,
        );
    }
}
