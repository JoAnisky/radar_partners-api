<?php

namespace App\Api;

use App\Entity\Contact;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Modification partielle d'un contact : seuls les champs présents dans le JSON sont touchés. */
final readonly class ContactUpdater
{
    private const FIELDS = ['fullName', 'role', 'phone', 'email', 'linkedinUrl', 'notes'];

    /** @param array<mixed> $payload */
    public static function requiredName(array $payload): string
    {
        return Payload::nullableString($payload['fullName'] ?? null, 'fullName', 255)
            ?? throw new UnprocessableEntityHttpException('Le nom du contact est obligatoire.');
    }

    /** @param array<mixed> $payload */
    public function update(Contact $contact, array $payload): void
    {
        $unknown = array_diff(array_map('strval', array_keys($payload)), self::FIELDS);
        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(sprintf('Champ inconnu : %s', implode(', ', $unknown)));
        }

        if (\array_key_exists('fullName', $payload)) {
            $contact->setFullName(self::requiredName($payload));
        }

        if (\array_key_exists('role', $payload)) {
            $contact->setRole(Payload::nullableString($payload['role'], 'role', 255));
        }

        if (\array_key_exists('phone', $payload)) {
            try {
                $contact->setPhone(Payload::nullableString($payload['phone'], 'phone', 30));
            } catch (\InvalidArgumentException) {
                throw new UnprocessableEntityHttpException('Numéro de téléphone invalide.');
            }
        }

        if (\array_key_exists('email', $payload)) {
            $email = Payload::nullableString($payload['email'], 'email', 255);
            if (null !== $email && false === filter_var($email = strtolower($email), \FILTER_VALIDATE_EMAIL)) {
                throw new UnprocessableEntityHttpException('Adresse email invalide.');
            }
            $contact->setEmail($email);
        }

        if (\array_key_exists('linkedinUrl', $payload)) {
            $url = Payload::nullableString($payload['linkedinUrl'], 'linkedinUrl', 255);
            $contact->setLinkedinUrl(null === $url || 1 === preg_match('#^https?://#i', $url) ? $url : 'https://'.$url);
        }

        if (\array_key_exists('notes', $payload)) {
            $contact->setNotes(Payload::nullableString($payload['notes'], 'notes', 10000));
        }
    }
}
