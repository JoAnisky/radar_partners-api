<?php

namespace App\Util;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneNormalizer
{
    public static function toE164(?string $phone, string $region = 'FR'): ?string
    {
        if (null === $phone || '' === trim($phone)) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse($phone, $region);
        } catch (NumberParseException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid phone number "%s".', $phone), 0, $e);
        }

        if (!$util->isValidNumber($number)) {
            throw new \InvalidArgumentException(sprintf('Invalid phone number "%s".', $phone));
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }
}
