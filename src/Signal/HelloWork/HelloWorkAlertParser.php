<?php

namespace App\Signal\HelloWork;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\RawSignal;
use Dom\Element;
use Dom\HTMLDocument;

/** Mails d'offres HelloWork : par carte, un lien tracké `/emplois/{id}.html` portant le titre, une ligne entreprise, puis des étiquettes (lieu, contrat, salaire). */
final class HelloWorkAlertParser
{
    public const SOURCE = 'hellowork';
    /** Alerte, offre recommandée, recommandations du profil. Les autres mails HelloWork (« recruteurs ont consulté votre cv »…) n'ont pas d'offre. */
    public const SENDERS = ['alerte@emails.hellowork.com', 'recommandation@emails.hellowork.com', 'notification@emails.hellowork.com'];

    private const OFFER_PATH = '#hellowork\.com/fr-fr/emplois/(\d+)\.html#';
    private const CONTRACTS = ['CDI', 'CDD', 'Freelance', 'Intérim', 'Stage', 'Alternance', 'Apprentissage', 'Franchise'];

    /** @return list<RawSignal> */
    public function parse(RawEmail $email): array
    {
        if (null === $email->html) {
            return [];
        }

        $document = HTMLDocument::createFromString($email->html, \LIBXML_NOERROR);
        $signals = [];
        $seen = [];

        foreach ($document->querySelectorAll('a[href]') as $link) {
            $title = null === $link->querySelector('img') ? $this->clean((string) $link->textContent) : '';
            $id = $this->offerId((string) $link->getAttribute('href'));
            if ('' === $title || null === $id || isset($seen[$id]) || 1 === preg_match('/^Voir l.offre$/u', $title)) {
                continue;
            }

            $seen[$id] = true;
            $signals[] = $this->buildSignal($link, $id, $title, $email);
        }

        return $signals;
    }

    private function buildSignal(Element $link, string $id, string $title, RawEmail $email): RawSignal
    {
        $company = $this->clean((string) $link->closest('tr')?->nextElementSibling?->textContent);
        $company = $this->clean((string) preg_replace('/Super recruteur$/u', '', $company));

        $extracted = ['city' => null, 'department' => null, 'contract' => null, 'salary' => null, 'dailyRate' => null];
        $tags = [];
        foreach ($link->closest('table')?->querySelectorAll('a[href="#"]') ?? [] as $tag) {
            $tags[] = $text = $this->clean((string) $tag->textContent);
            if (str_contains($text, '€')) {
                $extracted[str_contains($text, '/ jour') ? 'dailyRate' : 'salary'] = $text;
            } elseif (\in_array($text, self::CONTRACTS, true)) {
                $extracted['contract'] = $text;
            } elseif (null === $extracted['city'] && 1 === preg_match('/^(.+?) - (\w+)$/u', $text, $m)) {
                [, $extracted['city'], $extracted['department']] = $m;
            }
        }

        return new RawSignal(
            'Freelance' === $extracted['contract'] || 1 === preg_match('/freelance|indépendant/iu', $title) ? SignalType::FreelanceMission : SignalType::JobPosting,
            self::SOURCE,
            hash('sha256', self::SOURCE.'|'.$id),
            $title,
            'https://www.hellowork.com/fr-fr/emplois/'.$id.'.html',
            '' === $company ? null : $company,
            $title."\n".implode(' · ', $tags),
            $extracted,
            null,
            $email->receivedAt,
        );
    }

    private function offerId(string $href): ?string
    {
        $url = $this->decodeTrackedLink($href) ?? $href;

        return 1 === preg_match(self::OFFER_PATH, $url, $m) ? $m[1] : null;
    }

    /** Le dernier segment d'un lien tracké est « mail + séparateur + URL réelle » en base64 URL-safe. */
    private function decodeTrackedLink(string $href): ?string
    {
        $path = parse_url($href, \PHP_URL_PATH);
        $segment = \is_string($path) ? basename($path) : '';
        $decoded = base64_decode(strtr($segment, '-_', '+/').str_repeat('=', (4 - \strlen($segment) % 4) % 4), true);

        return \is_string($decoded) && str_contains($decoded, 'http') ? $decoded : null;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
