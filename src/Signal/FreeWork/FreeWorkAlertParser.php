<?php

namespace App\Signal\FreeWork;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\RawSignal;
use Dom\Element;
use Dom\HTMLDocument;

/** Alerte Free-Work (« N offres matchant avec vos critères ») : une offre par `<li><a><b>titre - <span>contrat</span></b><br>détails</a></li>`. */
final class FreeWorkAlertParser
{
    public const SOURCE = 'free-work';
    public const SENDER = 'jobs@free-work.com';

    private const OFFER_PATH = '#^/fr/tech-it/(job-[a-z-]+)/([^/]+)/([^/?]+)#';

    /** @return list<RawSignal> */
    public function parse(RawEmail $email): array
    {
        if (null === $email->html) {
            return [];
        }

        $document = HTMLDocument::createFromString($email->html, \LIBXML_NOERROR);
        $signals = [];

        foreach ($document->querySelectorAll('li > a[href]') as $link) {
            $signal = $this->parseOffer($link, $email);
            if (null !== $signal) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    private function parseOffer(Element $link, RawEmail $email): ?RawSignal
    {
        $offer = $this->offerUrl((string) $link->getAttribute('href'));
        $bold = $link->querySelector('b');
        if (null === $offer || null === $bold) {
            return null;
        }

        $contractText = $this->clean((string) $bold->querySelector('span')?->textContent);
        $title = $this->clean((string) preg_replace('/\s*-\s*'.preg_quote($contractText, '/').'\s*$/u', '', $this->clean((string) $bold->textContent)));
        $details = $this->clean(str_replace((string) $bold->textContent, '', (string) $link->textContent));
        $contracts = array_values(array_filter(array_map('trim', explode(',', $contractText))));

        return new RawSignal(
            \in_array('Freelance', $contracts, true) ? SignalType::FreelanceMission : SignalType::JobPosting,
            self::SOURCE,
            hash('sha256', self::SOURCE.'|'.$offer['path']),
            $title,
            'https://www.free-work.com'.$offer['path'],
            null,
            $title."\n".$details,
            ['contracts' => $contracts, 'category' => $offer['category']] + $this->parseDetails($details),
            null,
            $email->receivedAt,
        );
    }

    /** @return array<string, mixed> */
    private function parseDetails(string $details): array
    {
        $segments = array_map('trim', explode(' - ', $details));
        $parsed = ['durationMonths' => null, 'salary' => null, 'dailyRate' => null, 'city' => null, 'region' => null];

        if (1 === preg_match('/^(\d+) mois$/', $segments[0], $m)) {
            $parsed['durationMonths'] = (int) $m[1] ?: null;
            array_shift($segments);
        }

        $location = array_pop($segments);
        if (null !== $location && '' !== $location) {
            [$city, $region] = array_pad(explode(', ', $location, 2), 2, null);
            $parsed['city'] = $city;
            $parsed['region'] = $region;
        }

        foreach ($segments as $segment) {
            $key = 1 === preg_match('/\dk/i', $segment) ? 'salary' : 'dailyRate';
            $parsed[$key] = $segment;
        }

        return $parsed;
    }

    /** @return array{path: string, category: string}|null */
    private function offerUrl(string $href): ?array
    {
        $url = $this->decodeTrackedLink($href) ?? $href;
        $host = parse_url($url, \PHP_URL_HOST);
        $path = parse_url($url, \PHP_URL_PATH);

        if (!\is_string($host) || !str_ends_with($host, 'free-work.com') || !\is_string($path) || 1 !== preg_match(self::OFFER_PATH, $path, $m)) {
            return null;
        }

        return ['path' => '/fr/tech-it/'.$m[1].'/'.$m[2].'/'.$m[3], 'category' => $m[2]];
    }

    /** Le dernier segment d'un lien tracké est l'URL réelle en base64 URL-safe. */
    private function decodeTrackedLink(string $href): ?string
    {
        $path = parse_url($href, \PHP_URL_PATH);
        $segment = \is_string($path) ? basename($path) : '';
        $decoded = base64_decode(strtr($segment, '-_', '+/').str_repeat('=', (4 - \strlen($segment) % 4) % 4), true);

        return \is_string($decoded) && str_starts_with($decoded, 'http') ? $decoded : null;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
