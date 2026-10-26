<?php

namespace App\Llm\Demo;

/** Textes (Markdown) de l'assistant de démo, construits à partir du résultat des outils. */
final class DemoReplies
{
    private const STATUSES = ['TO_CONTACT' => 'à contacter', 'CONTACTED' => 'contactée', 'FOLLOW_UP' => 'à suivre', 'MEETING' => 'en rendez-vous', 'PARTNER' => 'partenaire', 'ON_HOLD' => 'en attente', 'EXCLUDED' => 'exclue'];
    private const TYPES = ['ESN' => 'ESN', 'WEB_AGENCY' => 'agence web', 'COM_AGENCY' => 'agence de communication', 'END_CLIENT' => 'client final', 'UNKNOWN' => 'type inconnu'];
    private const CHANNELS = ['PHONE' => 'téléphone', 'EMAIL' => 'email', 'IN_PERSON' => 'en personne', 'LINKEDIN' => 'LinkedIn', 'WEB_FORM' => 'formulaire web'];
    private const OUTCOMES = ['NO_ANSWER' => 'pas de réponse', 'VOICEMAIL' => 'répondeur', 'NOBODY_ON_SITE' => 'personne sur place', 'NO_NEED' => 'pas de besoin', 'NO_FREELANCE_POLICY' => 'ne travaille pas avec des freelances', 'JUST_HIRED' => 'vient d\'embaucher', 'MEETING_SCHEDULED' => 'rendez-vous fixé', 'POSITIVE' => 'échange positif', 'OTHER' => 'autre'];

    public static function help(): string
    {
        return <<<'MD'
            Je suis l'assistant de démonstration : je ne réponds qu'à quelques questions types, sur des données fictives. Essayez par exemple :

            - Quelles entreprises contacter en priorité ?
            - Quelles relances sont en retard ?
            - Quelles sont les dernières offres à traiter ?
            - Résume la fiche de Silex Digital.
            - Rédige un message d'approche pour Silex Digital.
            MD;
    }

    public static function askCompany(): string
    {
        return 'De quelle entreprise parlez-vous ? Indiquez son nom, par exemple « Silex Digital », ou ouvrez sa fiche puis reposez la question.';
    }

    /**
     * @param array<string, mixed> $data     résultat de l'outil appelé
     * @param string               $question question normalisée (sert à choisir le format d'un message)
     */
    public static function answer(DemoIntent $intent, array $data, string $question): string
    {
        if (isset($data['error'])) {
            return "Je n'ai pas trouvé cette entreprise dans la base de démonstration.";
        }

        return match ($intent) {
            DemoIntent::Priorities => self::priorities($data),
            DemoIntent::Overdue => self::overdue($data),
            DemoIntent::Signals => self::signals($data),
            DemoIntent::Summary => self::summary($data),
            DemoIntent::Draft => self::draft($data, $question),
        };
    }

    /** @param array<string, mixed> $data */
    private static function priorities(array $data): string
    {
        $companies = self::rows($data, 'companies');
        if ([] === $companies) {
            return 'Aucune entreprise à contacter pour le moment.';
        }

        $lines = array_map(static fn (array $c): string => sprintf(
            '- **%s** (%s, %s) : score %d/100',
            self::str($c, 'name'),
            self::str($c, 'city'),
            self::TYPES[self::str($c, 'type')] ?? '?',
            self::int($c, 'score'),
        ), $companies);

        return sprintf("Voici les %d entreprises à contacter en priorité, classées par score :\n\n%s\n\nLe score additionne le type d'entreprise, sa taille, ses offres récentes et la proximité ; sa fiche en donne le détail.", \count($companies), implode("\n", $lines));
    }

    /** @param array<string, mixed> $data */
    private static function overdue(array $data): string
    {
        $companies = self::rows($data, 'companies');
        if ([] === $companies) {
            return 'Aucune relance en retard.';
        }

        $lines = array_map(static function (array $c): string {
            $recontact = self::str($c, 'recontactAfter');
            $when = '' !== $recontact ? 'relance prévue le '.self::date($recontact) : 'dernier échange le '.self::date(self::str($c, 'lastInteractionAt'));

            return sprintf('- **%s** (%s) : %s, %s', self::str($c, 'name'), self::str($c, 'city'), self::STATUSES[self::str($c, 'status')] ?? '?', $when);
        }, $companies);

        return sprintf("%d entreprise(s) à relancer, les mieux classées d'abord :\n\n%s", self::int($data, 'total'), implode("\n", $lines));
    }

    /** @param array<string, mixed> $data */
    private static function signals(array $data): string
    {
        $signals = self::rows($data, 'signals');
        if ([] === $signals) {
            return 'Aucune offre à traiter.';
        }

        $lines = array_map(static function (array $s): string {
            $details = array_filter([
                self::str($s, 'companyName') ?: 'entreprise à rattacher',
                self::str($s, 'source'),
                self::str($s, 'location'),
                self::str($s, 'dailyRate'),
                true === ($s['toReview'] ?? false) ? 'à vérifier' : '',
            ]);

            return sprintf('- **%s** (%s)', self::str($s, 'title'), implode(', ', $details));
        }, $signals);

        return sprintf("Les %d dernières offres à traiter sur %d :\n\n%s\n\n« À vérifier » signifie que le titre ne nomme aucune technologie.", \count($signals), self::int($data, 'total'), implode("\n", $lines));
    }

    /** @param array<string, mixed> $company */
    private static function summary(array $company): string
    {
        $bullets = [];
        foreach (self::strings($company, 'alerts') as $alert) {
            $bullets[] = $alert;
        }

        $last = self::rows($company, 'interactions')[0] ?? null;
        $bullets[] = null === $last
            ? "Aucun échange pour l'instant."
            : sprintf(
                'Dernier échange il y a %d jour(s) (%s, %s)%s.',
                self::int($last, 'daysAgo'),
                self::CHANNELS[self::str($last, 'channel')] ?? '?',
                self::OUTCOMES[self::str($last, 'outcome')] ?? '?',
                '' === self::str($last, 'notes') ? '' : ' : '.self::str($last, 'notes'),
            );

        $overdue = self::int($company, 'nextActionOverdueByDays');
        $recontact = self::int($company, 'recontactOverdueByDays');
        if ($overdue > 0) {
            $bullets[] = sprintf('La prochaine action prévue est en retard de %d jour(s).', $overdue);
        } elseif ($recontact > 0) {
            $bullets[] = sprintf('La relance est due depuis %d jour(s).', $recontact);
        }

        $signal = self::rows($company, 'signals')[0] ?? null;
        if (null !== $signal) {
            $bullets[] = sprintf('Offre détectée : « %s » (%s).', self::str($signal, 'title'), self::str($signal, 'source'));
        }

        return sprintf(
            "**%s** : %s, score %d/100.\n\n%s",
            self::str($company, 'name'),
            self::STATUSES[self::str($company, 'status')] ?? '?',
            self::int($company, 'score'),
            implode("\n", array_map(static fn (string $b): string => '- '.$b, \array_slice($bullets, 0, 3))),
        );
    }

    /** @param array<string, mixed> $company */
    private static function draft(array $company, string $question): string
    {
        $contact = self::rows($company, 'contacts')[0] ?? null;
        $first = null === $contact ? '[prénom]' : explode(' ', self::str($contact, 'fullName'))[0];
        $name = self::str($company, 'name');
        $signal = self::rows($company, 'signals')[0] ?? null;
        $last = self::rows($company, 'interactions')[0] ?? null;

        $hook = match (true) {
            null !== $signal => sprintf('J\'ai vu votre annonce « %s » et je pense pouvoir vous aider sur ce besoin.', self::str($signal, 'title')),
            null !== $last => sprintf('Suite à notre échange d\'il y a %d jour(s), je me permets de revenir vers vous.', self::int($last, 'daysAgo')),
            default => sprintf('Je me permets de vous contacter car %s mène des projets web où mon profil peut compléter votre équipe.', $name),
        };
        $pitch = 'Je suis développeur freelance fullstack et DevOps (Symfony, Vue/Nuxt, Docker/K3s) et je cherche des partenaires durables plutôt que des missions ponctuelles.';

        [$text, $kind] = match (true) {
            str_contains($question, 'linkedin') => [sprintf("Bonjour %s,\n\n%s Seriez-vous ouvert à un court échange ?\n\n[Prénom Nom]", $first, $hook), 'message LinkedIn'],
            1 === preg_match('/script|appel|telephone/', $question) => [sprintf("Bonjour %s, [Prénom Nom], développeur freelance.\n\n%s\n%s\n\nAuriez-vous deux minutes, ou préférez-vous que je vous écrive ?", $first, $hook, $pitch), 'script d\'appel'],
            default => [sprintf("Objet : Renfort Symfony / DevOps, développeur freelance\n\nBonjour %s,\n\n%s\n%s\n\nSeriez-vous disponible pour un échange de 15 minutes cette semaine ?\n\nCordialement,\n[Prénom Nom]\n[Téléphone] · [Email]", $first, $hook, $pitch), 'mail d\'approche'],
        };

        return sprintf("Voici un %s pour %s :\n\n```\n%s\n```\n\nÀ compléter avant envoi : les champs entre crochets (signature, coordonnées%s).", $kind, $name, $text, null === $contact ? ', prénom du contact' : '');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(array $data, string $key): array
    {
        $rows = $data[$key] ?? null;

        /** @var list<array<string, mixed>> $list */
        $list = \is_array($rows) ? array_values($rows) : [];

        return $list;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function strings(array $data, string $key): array
    {
        $values = $data[$key] ?? null;

        return \is_array($values) ? array_values(array_filter($values, \is_string(...))) : [];
    }

    /** @param array<string, mixed> $data */
    private static function str(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function date(string $iso): string
    {
        return 10 > \strlen($iso) ? '?' : sprintf('%s/%s/%s', substr($iso, 8, 2), substr($iso, 5, 2), substr($iso, 0, 4));
    }
}
