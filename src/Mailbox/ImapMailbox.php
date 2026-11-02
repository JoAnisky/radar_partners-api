<?php

namespace App\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/** Boîte dédiée : les mails restent non lus ; les mails traités sont rangés dans le libellé « Alertes emploi ». */
final class ImapMailbox
{
    public const PROCESSED_FOLDER = 'Alertes emploi';

    private ?Client $client = null;

    /** @var array<int, Message> mails lus, par UID */
    private array $messages = [];

    /** @var list<int> */
    private array $processed = [];

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
                $email = $this->toRawEmail($message);
                $this->messages[$email->uid] = $message;

                yield $email;
            }
        }
    }

    public function markProcessed(RawEmail $email): void
    {
        $this->processed[$email->uid] = $email->uid;
    }

    /** À appeler une fois les signaux enregistrés. Retourne le nombre de mails déplacés. */
    public function moveProcessed(): int
    {
        $moved = 0;
        foreach ($this->processed as $uid) {
            if (null !== ($this->messages[$uid] ?? null)?->move(self::PROCESSED_FOLDER)) {
                ++$moved;
            }
        }
        $this->processed = [];

        return $moved;
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
