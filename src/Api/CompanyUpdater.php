<?php

namespace App\Api;

use App\Entity\Company;
use App\Enum\CompanyType;
use App\Enum\PipelineStatus;
use App\Enum\Scope;
use App\Service\PipelineStatusResolver;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Modification partielle d'une entreprise : seuls les champs présents dans le JSON sont touchés. */
final readonly class CompanyUpdater
{
    private const FIELDS = ['status', 'type', 'scope', 'notes', 'recontactAfter', 'flags', 'archived'];

    public function __construct(private PipelineStatusResolver $statusResolver)
    {
    }

    /** @param array<mixed> $payload */
    public function update(Company $company, array $payload, \DateTimeImmutable $now): void
    {
        $unknown = array_diff(array_map('strval', array_keys($payload)), self::FIELDS);
        if ([] !== $unknown) {
            throw new UnprocessableEntityHttpException(sprintf('Champ inconnu : %s', implode(', ', $unknown)));
        }

        if (\array_key_exists('status', $payload)) {
            $status = Payload::enum(PipelineStatus::class, $payload['status'], 'status');
            $changed = $status !== $company->getStatus();
            $company->setStatus($status);
            if ($changed && !\array_key_exists('recontactAfter', $payload)) {
                $company->setRecontactAfter(PipelineStatus::OnHold === $status ? $this->statusResolver->onHoldUntil($now) : null);
            }
        }

        if (\array_key_exists('recontactAfter', $payload)) {
            $company->setRecontactAfter(Payload::nullableDate($payload['recontactAfter'], 'recontactAfter'));
        }

        if (\array_key_exists('type', $payload)) {
            $company->setType(Payload::enum(CompanyType::class, $payload['type'], 'type'));
        }

        if (\array_key_exists('scope', $payload)) {
            $company->setScope(Payload::enum(Scope::class, $payload['scope'], 'scope'));
        }

        if (\array_key_exists('notes', $payload)) {
            $company->setNotes(Payload::nullableString($payload['notes'], 'notes', 10000));
        }

        if (\array_key_exists('flags', $payload)) {
            $company->setFlags(self::flags($payload['flags']));
        }

        if (\array_key_exists('archived', $payload)) {
            if (!\is_bool($payload['archived'])) {
                throw new UnprocessableEntityHttpException('archived doit être un booléen.');
            }
            $payload['archived'] ? $company->archive() : $company->unarchive();
        }
    }

    /** @return list<string> */
    private static function flags(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new UnprocessableEntityHttpException('flags doit être une liste.');
        }

        $flags = [];
        foreach ($value as $flag) {
            if (!\is_string($flag) || 1 !== preg_match('/^[A-Z0-9_]{1,40}$/', $flag)) {
                throw new UnprocessableEntityHttpException('Chaque flag doit être en majuscules (A-Z, 0-9, _), 40 caractères maximum.');
            }
            $flags[] = $flag;
        }

        return $flags;
    }
}
