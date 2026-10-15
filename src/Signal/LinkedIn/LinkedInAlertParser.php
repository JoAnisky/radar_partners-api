<?php

namespace App\Signal\LinkedIn;

use App\Enum\SignalType;
use App\Mailbox\RawEmail;
use App\Signal\RawSignal;
use Dom\Element;
use Dom\HTMLDocument;

/** Alerte LinkedIn Jobs : un lien `/jobs/view/{id}` portant le titre, suivi d'un `<p>` « Entreprise · Lieu (mode) ». */
final class LinkedInAlertParser
{
    public const SOURCE = 'linkedin';
    public const SENDER = 'jobalerts-noreply@linkedin.com';

    /** @return list<RawSignal> */
    public function parse(RawEmail $email): array
    {
        if (null === $email->html || str_contains($email->subject, 'a été créée')) {
            return [];
        }

        $document = HTMLDocument::createFromString($email->html, \LIBXML_NOERROR);
        $signals = [];
        $seen = [];

        foreach ($document->querySelectorAll('a[href*="/jobs/view/"]') as $link) {
            $title = null === $link->querySelector('table') ? $this->clean((string) $link->textContent) : '';
            $id = $this->jobId((string) $link->getAttribute('href'));
            if ('' === $title || null === $id || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $signals[] = $this->buildSignal($link, $id, $title, $email);
        }

        return $signals;
    }

    private function buildSignal(Element $link, string $id, string $title, RawEmail $email): RawSignal
    {
        $details = $this->clean((string) $link->closest('tr')?->nextElementSibling?->querySelector('p')?->textContent);
        $separator = mb_strrpos($details, ' · ');
        $company = false === $separator ? null : mb_substr($details, 0, $separator);
        $location = false === $separator ? $details : mb_substr($details, $separator + 3);

        $extracted = ['location' => $location, 'workMode' => null];
        if (1 === preg_match('/^(.*?)\s*\(([^)]+)\)$/u', $location, $m)) {
            $extracted['location'] = $m[1];
            $extracted['workMode'] = $m[2];
        }
        if ('À distance' === $extracted['workMode']) {
            $extracted['remote'] = true;
        }

        return new RawSignal(
            1 === preg_match('/freelance|indépendant/iu', $title) ? SignalType::FreelanceMission : SignalType::JobPosting,
            self::SOURCE,
            hash('sha256', self::SOURCE.'|'.$id),
            $title,
            'https://www.linkedin.com/jobs/view/'.$id.'/',
            '' === $company ? null : $company,
            $title."\n".$details,
            $extracted,
            null,
            $email->receivedAt,
        );
    }

    private function jobId(string $href): ?string
    {
        return 1 === preg_match('#/jobs/view/(\d+)#', $href, $m) ? $m[1] : null;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
