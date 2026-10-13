<?php

namespace App\Mailbox;

final readonly class RawEmail
{
    public function __construct(
        public int $uid,
        public string $messageId,
        public ?string $fromAddress,
        public ?string $fromName,
        public ?string $originalFromAddress,
        public string $subject,
        public ?\DateTimeImmutable $receivedAt,
        public ?string $text,
        public ?string $html,
    ) {
    }

    /** Vrai si le mail vient de cette adresse, directement ou via un transfert manuel. */
    public function isFrom(string $address): bool
    {
        $address = strtolower($address);

        return strtolower((string) $this->fromAddress) === $address || $this->originalFromAddress === $address;
    }
}
