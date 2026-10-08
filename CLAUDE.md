# CLAUDE.md — Radar Partenaires

> Nom de projet provisoire. Suivi de l'avancement dans `AVANCEMENT.md`.

## Objectif

Outil de prospection pour un développeur freelance fullstack/DevOps (Symfony, Vue/Nuxt, WordPress, Docker/K3s).
Il repère les **ESN, agences web et agences de communication** qui ont un besoin de renfort, et aide à les contacter au bon moment avec le bon angle.

Clients **exclus** : les clients finaux (PME, commerçants…). L'outil cherche des partenaires durables, pas des projets ponctuels.

Deux périmètres :
- **Local (Besançon / Bourgogne-Franche-Comté)** : approche proactive. On part d'un référentiel d'entreprises, qu'on enrichit et qu'on surveille.
- **National (remote)** : approche réactive. Une entreprise n'est créée que lorsqu'un signal la fait apparaître (offre d'emploi, mission freelance…).

Une version démo (données fictives) sera exposée sur jonathanlore.fr.

## Stack

- **API** : PHP 8.4+, Symfony 8.1 (dernière stable, montée de version à chaque mineure), Doctrine ORM, PostgreSQL, Messenger (transport Doctrine pour le MVP), Scheduler, HttpClient
- PostgreSQL pour : `jsonb` (champ `Signal.extracted`), `pg_trgm` (rapprochement de noms d'entreprises), recherche plein texte en français
- **Front** : Nuxt 4, TypeScript, Vue 3 (Composition API)
- **Infra** : Docker Compose en dev, K3s + Traefik en prod
- **LLM** : derrière une interface `LlmClientInterface` (fournisseur interchangeable)
- **Qualité** : PHPUnit, PHPStan (niveau max visé), PHP-CS-Fixer, ESLint

Deux repos indépendants, côte à côte (le dossier parent n'est pas versionné) :

```
radar_partners/
├── api/     repo Symfony (ce fichier et AVANCEMENT.md vivent ici), .docker/, docker-compose.yml, Makefile
└── front/   repo Nuxt, .docker/, docker-compose.yml, Makefile
```

Dev : tout tourne dans Docker (aucun PHP/Node local), derrière Traefik (réseau externe `web`).
`make up` / `make bash` dans chaque repo. URLs : `radarpartners.api.dev.local` (API), `radarpartners.dev.local` (front).
Conteneurs : `radarpartners-api`, `radarpartners-db`, `radarpartners-front` (le front joint l'API via `http://radarpartners-api` sur le réseau `web`).
Commandes PHP/Composer toujours dans le conteneur : `docker exec radarpartners-api composer ...` ou `php bin/console ...`.
Commits : un repo par projet, chacun avec ses propres commits.

Pièges connus :
- Les recettes Flex (ex. Doctrine) ajoutent des blocs à `docker-compose.yml` et créent `compose.override.yaml` : les retirer, notre compose est à nous.
- PHP 8.4 : pas d'extension `imap` ; utiliser une lib IMAP en PHP pur.
- URL de l'API : `DEFAULT_URI` dans `.env`. CORS à configurer pour le front.

## Modèle de domaine

Le modèle découle des limites du tableur de prospection actuel, où une même cellule mélangeait date, canal, contexte et résultat.

### `Company`
- `siren` (nullable, unique) : clé de rapprochement avec l'API Recherche d'entreprises
- `name`, `website`, `address`, `postalCode`, `city`
- `nafCode`, `headcountRange`
- `type` : enum `CompanyType` → `ESN`, `WEB_AGENCY`, `COM_AGENCY`, `END_CLIENT`, `UNKNOWN`
- `scope` : enum `Scope` → `LOCAL`, `NATIONAL`
- `parent` : `?Company` (filiales et agences d'un groupe, ex. bureau local d'un siège ailleurs)
- `status` : enum `PipelineStatus` → `TO_CONTACT`, `CONTACTED`, `FOLLOW_UP`, `MEETING`, `PARTNER`, `ON_HOLD`, `EXCLUDED`
- `recontactAfter` : `?DateTimeImmutable`
- `score` (int 0–100), `scoreExplanation` (texte)
- `flags` : liste libre (ex. `IN_DIFFICULTY`, `MAYBE_INACTIVE`, `BROKEN_CONTACT_FORM`)

### `Contact`
- `company`, `fullName`, `role`, `phone`, `email`, `linkedinUrl`
- Un contact par personne, jamais un nom et un numéro dans le même champ.

### `Interaction` (historique, une ligne par tentative)
- `company`, `contact` (nullable), `occurredAt`
- `channel` : enum → `PHONE`, `EMAIL`, `IN_PERSON`, `LINKEDIN`, `WEB_FORM`
- `origin` : texte court (événement, recommandation de X, réseau…)
- `outcome` : enum `InteractionOutcome` → `NO_ANSWER`, `VOICEMAIL`, `NOBODY_ON_SITE`, `NO_NEED`, `NO_FREELANCE_POLICY`, `JUST_HIRED`, `MEETING_SCHEDULED`, `POSITIVE`, `OTHER`
- `notes`, `nextActionAt`

### `Signal` (événement daté rattaché à une entreprise)
- `company` (nullable tant que non résolue)
- `type` : enum `SignalType` → `JOB_POSTING`, `FREELANCE_MISSION`, `STACK_DETECTED`, `COMPANY_CREATED`, `RECENT_HIRE`
- `source` (nom de l'adapter), `url`, `title`, `rawContent`
- `extracted` (JSON : stack, modalité remote, TJM, localisation…)
- `publishedAt`, `detectedAt`
- `fingerprint` (unique) : déduplication entre sources

### Règles métier issues du terrain
- `NO_FREELANCE_POLICY` → `status = EXCLUDED`.
- `JUST_HIRED` / `NO_NEED` → `ON_HOLD` + `recontactAfter` (délai par défaut configurable, à ajuster).
- `MEETING_SCHEDULED` → `MEETING`.
- Le statut se déduit de la dernière interaction significative, mais reste modifiable à la main.
- Un signal `JOB_POSTING` sur une entreprise `ON_HOLD` la fait remonter (le besoin a changé).

## Pipeline

```
SignalSource (adapter) → RawSignal (DTO)
  → [Messenger] IngestSignal      dédup par fingerprint, persistance
  → [Messenger] ResolveCompany    rapprochement SIREN / nom via API Recherche d'entreprises
  → [Messenger] AnalyzeSignal     1) règles PHP (éliminatoires)  2) LLM (extraction + angle d'approche)
  → [Messenger] ScoreCompany      recalcul du score à partir de tous ses signaux
```

### Interfaces clés

```php
interface SignalSourceInterface
{
    public function getName(): string;

    /** @return iterable<RawSignal> */
    public function fetch(\DateTimeImmutable $since): iterable;
}

interface EnricherInterface
{
    public function supports(Company $company): bool;
    public function enrich(Company $company): void;
}

interface ScoringRuleInterface
{
    public function evaluate(Company $company): ScoreContribution;
}
```

- Une source = une classe qui implémente `SignalSourceInterface`, taguée automatiquement (`#[AutoconfigureTag]`). Ajouter une source ne modifie pas le pipeline.
- Même principe pour les enrichers et les règles de scoring.
- Chaque `ScoreContribution` porte un libellé : le score doit toujours être explicable.

### Scoring (première version)

| Dimension | Règle |
|---|---|
| Type | ESN / agence web / agence com → OK ; `END_CLIENT` → éliminatoire |
| Stack | Symfony, WordPress, Vue/Nuxt, DevOps → fort |
| Modalité | Full remote ou local → OK ; présentiel hors région → éliminatoire |
| Urgence | Offre ouverte > 30 j ou republiée → bonus |
| Taille | 5–50 salariés → bonus |
| Relation | Recommandation ou contact physique positif → bonus |

Le LLM n'est appelé **que** sur les signaux qui passent les règles éliminatoires.

## Sources

- **MVP** : alertes email (LinkedIn, Free-Work) lues en IMAP sur une boîte dédiée. Les alertes LinkedIn arrivent sur l'adresse principale du compte et y sont redirigées par une règle de transfert. La source filtre sur l'expéditeur.
- Ensuite : API Recherche d'entreprises (référentiel local par codes NAF), détection de stack sur les sites.
- **API France Travail (Offres d'emploi)** : suspendue temporairement (non exposée), accès sur demande via francetravail.io. Ne pas en dépendre ; adapter à prévoir seulement si l'accès est obtenu.
- **Interdit** : scraper LinkedIn ou toute plateforme dont les CGU l'interdisent. Vérifier les CGU avant d'ajouter une source.

## Import du tableur existant

Commande `app:import:prospection <fichier.xlsx>` (PhpSpreadsheet) :
- une ligne → une `Company` (`scope = LOCAL`) ;
- « Personne à contacter » → `Contact` (extraire un éventuel téléphone entre parenthèses) ;
- « Date/Type de contact » et « Relance » → une ou plusieurs `Interaction` (dates au format `jj/mm/aa`, `jj/mm/aaaa` ou `mm/aa`) ;
- « Réponse » / « Note » → `outcome` + `notes`, avec un mapping de mots-clés (« pas de besoin », « ne travaillent pas avec des freelances », « viennent d'embaucher »…) ; ce qui n'est pas reconnu va en `OTHER` ;
- cellules `=HYPERLINK(url, nom)` → `website` + `name` ;
- produire un rapport des lignes ambiguës plutôt que de deviner.

## Démo publique

- Fixtures Faker uniquement, jamais de données réelles.
- Variable `APP_DEMO_MODE` : lecture seule, appels LLM simulés ou plafonnés.

## Données personnelles

Les contacts sont des données B2B. Conserver l'origine de chaque donnée et permettre la suppression d'un contact. Aucune donnée réelle dans le dépôt, les fixtures ou les logs.

## Conventions

- Code, noms de classes et commits **en anglais** ; échanges et documentation en français.
- Commentaires courts, uniquement quand le code ne suffit pas.
- Enums PHP natives pour tous les statuts.
- DTO `readonly` pour les données qui transitent entre couches.
- Un test unitaire par règle de scoring et par parser de source.
- **Ne jamais committer sans validation explicite.**
- En cas d'objectif flou, poser la question avant de coder.

## MVP (1 à 2 semaines)

1. Docker Compose (PHP, PostgreSQL, Nuxt)
2. Entités + migrations
3. Import du tableur
4. Source IMAP (alertes email)
5. Résolution d'entreprise + scoring en deux étapes
6. Dashboard Nuxt : liste triée par score, fiche entreprise, saisie d'interaction
