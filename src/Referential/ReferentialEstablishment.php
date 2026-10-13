<?php

namespace App\Referential;

final readonly class ReferentialEstablishment
{
    public function __construct(
        public string $siret,
        public ?string $address,
        public ?string $postalCode,
        public ?string $city,
        public bool $isHeadOffice,
        public bool $isActive,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromApi(array $data): ?self
    {
        $siret = self::string($data['siret'] ?? null);
        if (null === $siret) {
            return null;
        }

        $postalCode = self::string($data['code_postal'] ?? null);
        $city = self::string($data['libelle_commune'] ?? null);

        return new self(
            $siret,
            self::street(self::string($data['adresse'] ?? null), $postalCode, $city),
            $postalCode,
            $city,
            true === ($data['est_siege'] ?? false),
            'A' === ($data['etat_administratif'] ?? null),
        );
    }

    public static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /** L'API renvoie l'adresse complète : on retire « code postal commune » final. */
    public static function street(?string $address, ?string $postalCode, ?string $city): ?string
    {
        if (null === $address) {
            return null;
        }

        $suffix = trim(($postalCode ?? '').' '.($city ?? ''));
        if ('' !== $suffix && str_ends_with($address, $suffix)) {
            $address = trim(substr($address, 0, -\strlen($suffix)));
        }

        return '' === $address ? null : $address;
    }
}
