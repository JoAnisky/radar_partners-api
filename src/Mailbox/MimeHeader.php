<?php

namespace App\Mailbox;

final class MimeHeader
{
    public static function decode(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        $decoded = iconv_mime_decode($value, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return trim(false === $decoded ? $value : $decoded);
    }
}
