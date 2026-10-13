<?php

namespace App\Api;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Lecture typée des valeurs d'un JSON de modification partielle. */
final class Payload
{
    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function enum(string $enum, mixed $value, string $field): \BackedEnum
    {
        $case = \is_string($value) || \is_int($value) ? $enum::tryFrom($value) : null;

        return $case ?? throw new UnprocessableEntityHttpException(sprintf('Valeur invalide pour %s.', $field));
    }

    public static function nullableString(mixed $value, string $field, int $max): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || mb_strlen($value) > $max) {
            throw new UnprocessableEntityHttpException(sprintf('%s doit être un texte de %d caractères maximum.', $field, $max));
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    public static function date(mixed $value, string $field): \DateTimeImmutable
    {
        try {
            return \is_string($value) ? new \DateTimeImmutable($value) : throw new \InvalidArgumentException();
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException(sprintf('%s doit être une date (ex. 2026-12-01).', $field));
        }
    }

    public static function nullableDate(mixed $value, string $field): ?\DateTimeImmutable
    {
        return null === $value ? null : self::date($value, $field);
    }

    public static function nullableInt(mixed $value, string $field): ?int
    {
        if (null === $value) {
            return null;
        }

        return \is_int($value) ? $value : throw new UnprocessableEntityHttpException(sprintf('%s doit être un entier ou null.', $field));
    }
}
