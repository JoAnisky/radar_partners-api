<?php

namespace App\Mailbox;

final class ForwardedMessage
{
    /** Adresse de l'expéditeur d'origine d'un mail transféré à la main (ligne « De : » sous le bandeau de transfert). */
    public static function originalSender(?string $text): ?string
    {
        $banner = '/^-{3,}\s*(?:Forwarded message|Message transféré)[^\n]*\n(?:[ \t]*\n)*(?:De|From)[ \t]*:[ \t]*(.+)$/mi';
        if (null === $text || 1 !== preg_match($banner, $text, $line)) {
            return null;
        }

        return 1 === preg_match('/[\w.+-]+@[\w.-]+\.\w+/u', $line[1], $address) ? strtolower($address[0]) : null;
    }
}
