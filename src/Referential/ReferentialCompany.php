<?php

namespace App\Referential;

final readonly class ReferentialCompany
{
    /** Codes INSEE de tranche d'effectif ; « NN » (non employeur) n'a pas de libellé. */
    private const HEADCOUNT = [
        '00' => '0', '01' => '1-2', '02' => '3-5', '03' => '6-9', '11' => '10-19', '12' => '20-49',
        '21' => '50-99', '22' => '100-199', '31' => '200-249', '32' => '250-499', '41' => '500-999',
        '42' => '1000-1999', '51' => '2000-4999', '52' => '5000-9999', '53' => '10000+',
    ];

    /** @param list<ReferentialEstablishment> $establishments établissements correspondant aux filtres */
    public function __construct(
        public string $siren,
        public string $name,
        public ?string $nafCode,
        public ?string $headcountRange,
        public ?ReferentialEstablishment $headOffice,
        public array $establishments,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromApi(array $data): ?self
    {
        $siren = ReferentialEstablishment::string($data['siren'] ?? null);
        $name = ReferentialEstablishment::string($data['nom_complet'] ?? null);
        if (null === $siren || null === $name) {
            return null;
        }

        $headcount = ReferentialEstablishment::string($data['tranche_effectif_salarie'] ?? null);
        $siege = \is_array($data['siege'] ?? null) ? ReferentialEstablishment::fromApi($data['siege']) : null;

        $establishments = [];
        foreach (\is_array($data['matching_etablissements'] ?? null) ? $data['matching_etablissements'] : [] as $item) {
            $establishment = \is_array($item) ? ReferentialEstablishment::fromApi($item) : null;
            if (null !== $establishment) {
                $establishments[] = $establishment;
            }
        }

        return new self(
            $siren,
            $name,
            ReferentialEstablishment::string($data['activite_principale'] ?? null),
            null === $headcount ? null : (self::HEADCOUNT[$headcount] ?? null),
            $siege,
            $establishments,
        );
    }
}
