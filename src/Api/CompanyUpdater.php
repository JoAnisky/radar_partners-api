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
            $status = self::enum(PipelineStatus::class, $payload['status'], 'status');
            $changed = $status !== $company->getStatus();
            $company->setStatus($status);
            if ($changed && !\array_key_exists('recontactAfter', $payload)) {
                $company->setRecontactAfter(PipelineStatus::OnHold === $status ? $this->statusResolver->onHoldUntil($now) : null);
            }
        }

        if (\array_key_exists('recontactAfter', $payload)) {
            $company->setRecontactAfter(self::nullableDate($payload['recontactAfter']));
        }

        if (\array_key_exists('type', $payload)) {
            $company->setType(self::enum(CompanyType::class, $payload['type'], 'type'));
        }

        if (\array_key_exists('scope', $payload)) {
            $company->setScope(self::enum(Scope::class, $payload['scope'], 'scope'));
        }

        if (\array_key_exists('notes', $payload)) {
            $company->setNotes(self::nullableString($payload['notes'], 'notes', 10000));
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

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private static function enum(string $enum, mixed $value, string $field): \BackedEnum
    {
        $case = \is_string($value) || \is_int($value) ? $enum::tryFrom($value) : null;

        return $case ?? throw new UnprocessableEntityHttpException(sprintf('Valeur invalide pour %s.', $field));
    }

    private static function nullableString(mixed $value, string $field, int $max): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || mb_strlen($value) > $max) {
            throw new UnprocessableEntityHttpException(sprintf('%s doit être un texte de %d caractères maximum.', $field, $max));
        }

        return '' === trim($value) ? null : $value;
    }

    private static function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            return \is_string($value) ? new \DateTimeImmutable($value) : throw new \InvalidArgumentException();
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('recontactAfter doit être une date (ex. 2026-12-01) ou null.');
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
