<?php

namespace App\Api;

use App\Entity\Contact;
use App\Entity\Interaction;
use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Modification partielle d'une interaction : seuls les champs présents dans le JSON sont touchés. */
final readonly class InteractionUpdater
{
    private const FIELDS = ['channel', 'occurredAt', 'outcome', 'contactId', 'origin', 'notes', 'nextActionAt'];

    /**
     * @param array<mixed> $payload
     *
     * @return bool vrai si la date ou le résultat a changé (le statut de l'entreprise peut alors évoluer)
     */
    public function update(Interaction $interaction, array $payload): bool
    {
        $unknown = array_diff(array_map('strval', array_keys($payload)), self::FIELDS);
        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(sprintf('Champ inconnu : %s', implode(', ', $unknown)));
        }

        if (\array_key_exists('channel', $payload)) {
            $interaction->setChannel(Payload::enum(InteractionChannel::class, $payload['channel'], 'channel'));
        }

        if (\array_key_exists('occurredAt', $payload)) {
            $interaction->setOccurredAt(Payload::date($payload['occurredAt'], 'occurredAt'));
        }

        if (\array_key_exists('outcome', $payload)) {
            $interaction->setOutcome(Payload::enum(InteractionOutcome::class, $payload['outcome'], 'outcome'));
        }

        if (\array_key_exists('contactId', $payload)) {
            $interaction->setContact($this->contact($interaction, Payload::nullableInt($payload['contactId'], 'contactId')));
        }

        if (\array_key_exists('origin', $payload)) {
            $interaction->setOrigin(Payload::nullableString($payload['origin'], 'origin', 255));
        }

        if (\array_key_exists('notes', $payload)) {
            $interaction->setNotes(Payload::nullableString($payload['notes'], 'notes', 10000));
        }

        if (\array_key_exists('nextActionAt', $payload)) {
            $interaction->setNextActionAt(Payload::nullableDate($payload['nextActionAt'], 'nextActionAt'));
        }

        return \array_key_exists('occurredAt', $payload) || \array_key_exists('outcome', $payload);
    }

    private function contact(Interaction $interaction, ?int $contactId): ?Contact
    {
        if (null === $contactId) {
            return null;
        }

        return $interaction->getCompany()->getContacts()->findFirst(static fn (int $key, Contact $c): bool => $c->getId() === $contactId)
            ?? throw new UnprocessableEntityHttpException("Ce contact n'appartient pas à cette entreprise.");
    }
}
