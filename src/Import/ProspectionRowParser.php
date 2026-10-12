<?php

namespace App\Import;

use App\Enum\InteractionChannel;
use App\Enum\InteractionOutcome;
use App\Util\PhoneNormalizer;

final class ProspectionRowParser
{
    private const DATE_TOKEN = '#\d{1,2}/\d{1,2}/\d{2,4}|\d{1,2}/\d{2,4}|\b(?:19|20)\d{2}\b#';
    private const HYPERLINK = '/^=HYPERLINK\(\s*"([^"]*)"\s*,\s*"([^"]*)"\s*\)$/i';
    private const PHONE_ONLY = '/^[\d\s.+()-]{8,}$/';
    private const NAME_AND_PHONE = '/^(.*?)\s*\(([\d\s.+-]{8,})\)$/u';
    private const ADDRESS = '/^(.*?)[,\s]+(\d{5})\s+(.+)$/su';

    private const CHANNELS = [
        ['PHONE', '/\b(tel|telephone|appel)\b/'],
        ['EMAIL', '/\b(e-?mail|mail)\b/'],
        ['IN_PERSON', '/\b(physique|apero|sur place)\b/'],
        ['LINKEDIN', '/linkedin/'],
        ['WEB_FORM', '/formulaire/'],
    ];

    // Ordre = priorité
    private const OUTCOMES = [
        ['NO_FREELANCE_POLICY', '/ne travaill\w* pas avec des freelances?|pas de freelances?/'],
        ['MEETING_SCHEDULED', '/\brdv\b/'],
        ['JUST_HIRED', '/embauch/'],
        ['NO_NEED', '/pas de besoins?/'],
        ['VOICEMAIL', '/repondeur|messagerie/'],
        ['NO_ANSWER', '/pas de rep|sans reponse/'],
        ['NOBODY_ON_SITE', '/\bpersonne\b/'],
    ];

    private const FLAGS = [
        ['IN_DIFFICULTY', '/difficulte/'],
        ['MAYBE_INACTIVE', '/encore actif/'],
        ['BROKEN_CONTACT_FORM', '/formulaire.*(ne fonctionn(?:e|ent) pas|marche pas)/'],
    ];

    /**
     * @param array{name: ?string, url: ?string, address: ?string, phone: ?string, email: ?string, contact: ?string, contacted: ?string, response: ?string, followUp: ?string, note: ?string} $cells
     */
    public function parse(array $cells): ?ProspectionRow
    {
        $warnings = [];
        $notes = [];

        [$name, $website] = $this->parseName($cells['name'], $cells['url']);
        if (null === $name) {
            return null;
        }

        [$address, $postalCode, $city] = $this->parseAddress($cells['address'], $warnings);
        $phone = $this->phone($cells['phone'], $warnings);
        $email = $this->email($cells['email'], $warnings);
        [$contactName, $contactPhone] = $this->parseContact($cells['contact'], $warnings);

        if (null !== $contactName && null === $contactPhone && !str_contains($contactName, ' ')) {
            $warnings[] = sprintf('Contact "%s" : prénom seul', $contactName);
        }

        $response = $this->clean($cells['response']);
        $note = $this->clean($cells['note']);

        $segments = [
            ...$this->segments($cells['contacted'], 'Date/Type de contact', $warnings, $notes),
            ...$this->segments($cells['followUp'], 'Relance', $warnings, $notes),
        ];
        usort($segments, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        $interactions = $this->buildInteractions($segments, $response, $note, $warnings, $notes);

        if (null !== $response && [] === $interactions) {
            $warnings[] = sprintf('Réponse sans interaction : "%s"', $response);
            $notes[] = 'Réponse : '.$response;
        }

        $flags = [];
        foreach (self::FLAGS as [$flag, $pattern]) {
            if (null !== $note && 1 === preg_match($pattern, self::fold($note))) {
                $flags[] = $flag;
            }
        }

        $allNotes = array_filter([$note, ...$notes]);

        return new ProspectionRow(
            $name,
            $website,
            $address,
            $postalCode,
            $city,
            $phone,
            $email,
            $contactName,
            $contactPhone,
            $interactions,
            [] === $allNotes ? null : implode("\n", $allNotes),
            $flags,
            $warnings,
        );
    }

    /** @return array{?string, ?string} */
    private function parseName(?string $name, ?string $url): array
    {
        $name = $this->clean($name);
        if (null !== $name && 1 === preg_match(self::HYPERLINK, $name, $m)) {
            return [$this->clean($m[2]), $this->clean($m[1])];
        }

        return [$name, $this->clean($url)];
    }

    /**
     * @param list<string> $warnings
     *
     * @return array{?string, ?string, ?string}
     */
    private function parseAddress(?string $raw, array &$warnings): array
    {
        $raw = $this->clean($raw);
        if (null === $raw) {
            return [null, null, null];
        }

        if (1 !== preg_match(self::ADDRESS, $raw, $m)) {
            $warnings[] = sprintf('Adresse non décomposée : "%s"', $raw);

            return [$raw, null, null];
        }

        return [trim($m[1]), $m[2], trim($m[3])];
    }

    /** @param list<string> $warnings */
    private function phone(?string $raw, array &$warnings): ?string
    {
        $raw = $this->clean($raw);
        if (null === $raw) {
            return null;
        }

        try {
            return PhoneNormalizer::toE164($raw);
        } catch (\InvalidArgumentException) {
            $warnings[] = sprintf('Téléphone invalide : "%s"', $raw);

            return null;
        }
    }

    /** @param list<string> $warnings */
    private function email(?string $raw, array &$warnings): ?string
    {
        $raw = $this->clean($raw);
        if (null === $raw) {
            return null;
        }

        $raw = strtolower($raw);
        if (false === filter_var($raw, \FILTER_VALIDATE_EMAIL)) {
            $warnings[] = sprintf('Email invalide : "%s"', $raw);

            return null;
        }

        return $raw;
    }

    /**
     * @param list<string> $warnings
     *
     * @return array{?string, ?string} nom, téléphone
     */
    private function parseContact(?string $raw, array &$warnings): array
    {
        $raw = $this->clean($raw);
        if (null === $raw) {
            return [null, null];
        }

        if (1 === preg_match(self::PHONE_ONLY, $raw)) {
            return [null, $this->phone($raw, $warnings)];
        }

        if (1 === preg_match(self::NAME_AND_PHONE, $raw, $m)) {
            return [trim($m[1]), $this->phone($m[2], $warnings)];
        }

        return [$raw, null];
    }

    /**
     * @param list<string> $warnings
     * @param list<string> $notes
     *
     * @return list<array{date: \DateTimeImmutable, text: string}>
     */
    private function segments(?string $raw, string $label, array &$warnings, array &$notes): array
    {
        $raw = $this->clean($raw);
        if (null === $raw) {
            return [];
        }

        preg_match_all(self::DATE_TOKEN, $raw, $matches, \PREG_OFFSET_CAPTURE);
        $tokens = $matches[0];
        if ([] === $tokens) {
            $warnings[] = sprintf('%s sans date : "%s"', $label, $raw);
            $notes[] = sprintf('%s : %s', $label, $raw);

            return [];
        }

        $segments = [];
        foreach ($tokens as $i => [$token, $offset]) {
            $from = 0 === $i ? 0 : $offset + \strlen($token);
            $to = $tokens[$i + 1][1] ?? \strlen($raw);
            $text = substr($raw, $from, $to - $from);
            if (0 === $i) {
                $text = substr_replace($text, '', $offset, \strlen($token));
            }
            $text = trim((string) preg_replace('/\s+/u', ' ', $text), " \t-;,");

            $date = $this->parseDate($token, $warnings);
            if (null === $date) {
                $warnings[] = sprintf('%s : date ambiguë "%s"', $label, $token);
                $notes[] = sprintf('%s : %s %s', $label, $token, $text);
                continue;
            }

            $segments[] = ['date' => $date, 'text' => $text];
        }

        return $segments;
    }

    /** @param list<string> $warnings */
    private function parseDate(string $token, array &$warnings): ?\DateTimeImmutable
    {
        $parts = explode('/', $token);

        if (3 === \count($parts)) {
            [$d, $m, $y] = array_map('intval', $parts);
            $y = $y < 100 ? 2000 + $y : $y;

            return checkdate($m, $d, $y) ? new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d)) : null;
        }

        if (2 === \count($parts)) {
            [$m, $y] = array_map('intval', $parts);
            if ($m < 1 || $m > 12 || (2 === \strlen($parts[1]) && $y <= 12)) {
                return null;
            }

            return new \DateTimeImmutable(sprintf('%04d-%02d-01', $y < 100 ? 2000 + $y : $y, $m));
        }

        $warnings[] = sprintf('Date approximative (année seule) : %s', $token);

        return new \DateTimeImmutable($token.'-01-01');
    }

    /**
     * @param list<array{date: \DateTimeImmutable, text: string}> $segments
     * @param list<string>                                        $warnings
     * @param list<string>                                        $notes
     *
     * @return list<ProspectionInteraction>
     */
    private function buildInteractions(array $segments, ?string $response, ?string $note, array &$warnings, array &$notes): array
    {
        if ([] === $segments) {
            return [];
        }

        $latest = max(array_column($segments, 'date'));
        $interactions = [];

        foreach ($segments as ['date' => $date, 'text' => $text]) {
            $folded = self::fold($text);
            $channels = array_values(array_filter(
                self::CHANNELS,
                static fn (array $c): bool => 1 === preg_match($c[1], $folded),
            ));

            if ([] === $channels) {
                $warnings[] = sprintf('Canal non reconnu (%s) : "%s"', $date->format('d/m/Y'), $text);
                $notes[] = sprintf('%s : %s', $date->format('d/m/Y'), $text);
                continue;
            }

            $isLatest = $date == $latest;
            $outcomeText = $isLatest ? implode(' ', [$text, $response, $note]) : $text;
            $outcome = $this->outcome($outcomeText);
            $interactionNotes = $isLatest && null !== $response
                ? trim($text.' — Réponse : '.$response, ' —')
                : $text;

            if (InteractionOutcome::MeetingScheduled === $outcome) {
                $warnings[] = sprintf('RDV à vérifier (%s) : "%s"', $date->format('d/m/Y'), $response ?? $text);
            }

            foreach ($channels as [$channel]) {
                $interactions[] = new ProspectionInteraction(
                    $date,
                    InteractionChannel::from($channel),
                    $outcome,
                    $this->origin($text),
                    '' === $interactionNotes ? null : $interactionNotes,
                );
            }
        }

        return $interactions;
    }

    private function outcome(string $text): InteractionOutcome
    {
        $folded = self::fold($text);
        foreach (self::OUTCOMES as [$outcome, $pattern]) {
            if (1 === preg_match($pattern, $folded)) {
                return InteractionOutcome::from($outcome);
            }
        }

        return InteractionOutcome::Other;
    }

    private function origin(string $text): ?string
    {
        if (1 === preg_match('/\breco\w*\s+[^)\];,]+/iu', $text, $m)) {
            return trim($m[0]);
        }

        if (1 === preg_match('/ap[ée]ro[^()]*(\([^)]*\))?/iu', $text, $m)) {
            return trim((string) preg_replace('/\s+/u', ' ', $m[0]));
        }

        return null;
    }

    private function clean(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    private static function fold(string $text): string
    {
        $text = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')?->transliterate($text);

        return $text ?: '';
    }
}
