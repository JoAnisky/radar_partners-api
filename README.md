# Radar Partenaires

> Nom de projet provisoire. Document de contexte pour toute personne ou IA qui rejoint le projet.
> Conventions : [`CLAUDE.md`](CLAUDE.md). Suivi : [`AVANCEMENT.md`](AVANCEMENT.md). Détail : [`docs/domain.md`](docs/domain.md) (modèle), [`docs/pipeline.md`](docs/pipeline.md) (pipeline, scoring), [`docs/sources.md`](docs/sources.md) (sourcing), [`docs/import.md`](docs/import.md) (import du tableur).

## En une phrase

Outil de prospection pour un **développeur freelance fullstack/DevOps** (Symfony, Vue/Nuxt, WordPress, Docker/K3s) : il repère les **ESN, agences web et agences de communication** qui ont un besoin de renfort, et aide à les contacter au bon moment avec le bon angle.

## Le besoin

- **Cible** : des partenaires durables (ESN, agences web, agences de com). Les **clients finaux** (PME, commerçants…) sont exclus.
- **Local (Besançon / Bourgogne-Franche-Comté)** : approche proactive. On part d'un référentiel d'entreprises, qu'on enrichit et qu'on surveille.
- **National (remote)** : approche réactive. Une entreprise n'est créée que lorsqu'un signal la fait apparaître (offre d'emploi, mission freelance…).
- **Origine** : un tableur de prospection manuel, dont une même cellule mélangeait date, canal, contexte et résultat. Le modèle de données a été pensé pour corriger ça.
- **Démo publique** prévue (données fictives uniquement) sur jonathanlore.fr.

## Stack

| Couche | Choix |
|---|---|
| API | PHP 8.4, Symfony 8.1, Doctrine ORM, Messenger (transport Doctrine), Scheduler, HttpClient |
| Base | PostgreSQL 17 (`jsonb`, `pg_trgm` prévu pour le rapprochement de noms, recherche plein texte FR) |
| Front | Nuxt 4, TypeScript, Vue 3 (Composition API) |
| LLM | derrière une interface `LlmClientInterface` (fournisseur interchangeable) ; en dev, modèle local Ollama (GPU) pour l'assistant de chat « Amelio » : voir `docs/assistant.md` |
| Tâches planifiées | Symfony Scheduler dans un conteneur worker : ingestion quotidienne des alertes mail à 9h, rattrapée au démarrage de la machine |
| Qualité | PHPUnit, PHPStan (niveau max visé), PHP-CS-Fixer, ESLint |
| Infra | Docker Compose en dev (derrière Traefik), K3s + Traefik en prod |

## Organisation du dépôt

Deux repos git **indépendants**, côte à côte (le dossier parent n'est pas versionné) :

```
radar_partners/
├── api/     Symfony — README.md, CLAUDE.md, AVANCEMENT.md, .docker/, docker-compose.yml, Makefile
└── front/   Nuxt    — .docker/, docker-compose.yml, Makefile
```

Dans `api/src/` : `Entity/`, `Enum/`, `Repository/`, `Import/` (parseur et importer du tableur), `Command/`, `Service/`, `Util/`.

## Lancer le projet

Tout tourne dans Docker : **jamais de PHP, Node ou Composer en local**. Réseau externe Traefik `web` requis.

```bash
cd api && make up                      # API + PostgreSQL
cd front && make up                    # Nuxt
docker exec radarpartners-api php bin/console doctrine:migrations:migrate
docker exec radarpartners-api vendor/bin/phpunit
```

- URLs de dev : `radarpartners.api.dev.local` (API), `radarpartners.dev.local` (front).
- Conteneurs : `radarpartners-api`, `radarpartners-db`, `radarpartners-front`.
- Import du tableur : `docker exec radarpartners-api php bin/console app:import:prospection <fichier.xlsx> [--dry-run]` (le fichier va dans `api/var/import/`, ignoré par git).

## Modèle de domaine

- **`Company`** : identité (SIREN nullable et unique, nom, site, adresse, tél. E.164, email), `type` (ESN / agence web / agence com / client final / inconnu), `scope` (local / national), `parent`, `status` du pipeline, `recontactAfter`, `score` 0–100 + explication, `flags`, `notes`. **Soft delete** (`archivedAt`) : on ne supprime jamais une entreprise ni son historique.
- **`Contact`** : une personne par ligne (nom, rôle, un téléphone, un email, LinkedIn). Suppression possible (RGPD).
- **`Interaction`** : une ligne par tentative de contact (date, canal, origine, résultat, notes, prochaine action).
- **`Signal`** : événement daté (offre d'emploi, mission freelance, stack détectée, création d'entreprise, embauche récente), rattaché à une entreprise une fois résolue ; `fingerprint` unique pour dédupliquer entre sources ; `extracted` en `jsonb`.

Statuts du pipeline : `TO_CONTACT` → `CONTACTED` → `FOLLOW_UP` → `MEETING` → `PARTNER`, plus `ON_HOLD` et `EXCLUDED`. Règles métier : « ne travaille pas avec des freelances » → `EXCLUDED` ; « pas de besoin » ou « vient d'embaucher » → `ON_HOLD` avec date de relance ; un nouveau signal d'offre sur une entreprise `ON_HOLD` la fait remonter.

## Pipeline de traitement (cible)

```
SignalSource (adapter) → RawSignal
  → IngestSignal     déduplication par fingerprint
  → ResolveCompany   rapprochement SIREN / nom (API Recherche d'entreprises)
  → AnalyzeSignal    1) règles PHP éliminatoires  2) LLM (extraction + angle d'approche)
  → ScoreCompany     score explicable à partir de tous les signaux
```

Principes : une source, un enricher ou une règle de scoring = une classe taguée automatiquement (ajouter l'une ne modifie pas le pipeline) ; chaque contribution au score porte un libellé ; le LLM n'est appelé que sur les signaux qui passent les règles éliminatoires.

## Où on en est

Voir [`AVANCEMENT.md`](AVANCEMENT.md). Fait : Docker, entités + migrations, import du tableur, connexion IMAP testée (boîte Gmail dédiée). En cours : sourcing (alertes Free-Work et LinkedIn en attente d'échantillons). À venir : lecteur IMAP et parseurs, résolution d'entreprise et scoring, dashboard Nuxt.

## Sources de signaux : contraintes

Détail, pistes et décisions : [`docs/sources.md`](docs/sources.md).

- **Interdit** : scraper LinkedIn ou toute plateforme dont les CGU l'interdisent. Vérifier les CGU avant d'ajouter une source.
- API France Travail (Offres d'emploi) : accès non garanti, ne pas en dépendre.
- **Piste, pas avant l'IMAP** : [Apify](https://apify.com) (scrapers « Actors » appelés par API, payés à l'usage). Utile d'abord pour la détection de stack sur les sites d'entreprises déjà connues (`Enricher`), éventuellement pour des offres Welcome to the Jungle après vérification des CGU. Actors communautaires, donc fiabilité variable ; la responsabilité juridique de l'usage revient à l'utilisateur.
- PHP 8.4 n'a pas d'extension `imap` : lecture IMAP via une librairie PHP pure.

## Conventions

- Code, noms de classes et commits **en anglais** ; échanges et documentation **en français**.
- Commentaires courts, uniquement quand le code ne suffit pas ; privilégier le simple à l'élégant.
- Enums PHP natives pour tous les statuts ; DTO `readonly` entre les couches.
- Un test unitaire par parseur de source et par règle de scoring.
- Lancer tests, PHPStan et PHP-CS-Fixer avant de déclarer une tâche terminée.
- **Pas de commit sans validation explicite** ; commits courts, découpés par catégorie de modification.

## Données personnelles et sécurité

- Les contacts sont des données B2B : conserver l'origine de chaque donnée, permettre la suppression d'un contact.
- **Aucune donnée réelle** dans le dépôt, les fixtures, les tests ou les logs ; jamais de secret committé (`.env.local`, identifiants IMAP, clés API).
- Mode démo (`APP_DEMO_MODE`) : lecture seule, appels LLM simulés ou plafonnés.
