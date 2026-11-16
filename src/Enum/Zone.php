<?php

namespace App\Enum;

/** Zone géographique de prospection, plus fine que `Scope`. */
enum Zone: string
{
    case FrancheComte = 'FRANCHE_COMTE';
    case Bourgogne = 'BOURGOGNE';
    case SwissRomande = 'SUISSE_ROMANDE';
    case Other = 'OTHER';

    private const FRANCHE_COMTE_DEPARTMENTS = ['25', '39', '70', '90'];
    private const BOURGOGNE_DEPARTMENTS = ['21', '58', '71', '89'];

    /** Code postal français à 5 chiffres ; null si absent ou non français. */
    public static function fromPostalCode(?string $postalCode): ?self
    {
        if (null === $postalCode || 1 !== preg_match('/^\d{5}$/', $postalCode)) {
            return null;
        }

        $department = substr($postalCode, 0, 2);

        return match (true) {
            \in_array($department, self::FRANCHE_COMTE_DEPARTMENTS, true) => self::FrancheComte,
            \in_array($department, self::BOURGOGNE_DEPARTMENTS, true) => self::Bourgogne,
            default => self::Other,
        };
    }
}
