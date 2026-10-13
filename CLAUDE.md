# CLAUDE.md — Radar Partenaires

> Nom de projet provisoire. Vue d'ensemble partageable : `README.md`. Suivi : `AVANCEMENT.md`.

## Objectif

Outil de prospection pour un développeur freelance fullstack/DevOps (Symfony, Vue/Nuxt, WordPress, Docker/K3s).
Il repère les **ESN, agences web et agences de communication** qui ont un besoin de renfort, et aide à les contacter au bon moment avec le bon angle.

Clients **exclus** : les clients finaux (PME, commerçants…). L'outil cherche des partenaires durables, pas des projets ponctuels.

Deux périmètres :
- **Local (Besançon / Bourgogne-Franche-Comté)** : approche proactive. On part d'un référentiel d'entreprises, qu'on enrichit et qu'on surveille.
- **National (remote)** : approche réactive. Une entreprise n'est créée que lorsqu'un signal la fait apparaître (offre d'emploi, mission freelance…).

Une version démo (données fictives) sera exposée sur jonathanlore.fr.

## Documentation détaillée (à lire à la demande)

| Fichier | Contenu |
|---|---|
| `docs/domain.md` | Entités (`Company`, `Contact`, `Interaction`, `Signal`), statuts, règles métier |
| `docs/pipeline.md` | Pipeline Messenger, interfaces clés, scoring |
| `docs/sources.md` | Sourcing : sources, pistes, boîte IMAP, règle de filtrage, prochaines étapes |
| `docs/api.md` | Endpoints HTTP, paramètres, décisions techniques, base de test |
| `docs/front.md` | Front Nuxt : choix, pages, vérifications, points ouverts |
| `docs/import.md` | Import du tableur : règles et décisions d'implémentation |

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
├── api/     repo Symfony (README, CLAUDE.md, AVANCEMENT.md, docs/), .docker/, docker-compose.yml, Makefile
└── front/   repo Nuxt, .docker/, docker-compose.yml, Makefile
```

Dev : tout tourne dans Docker (aucun PHP/Node local), derrière Traefik (réseau externe `web`).
`make up` / `make bash` dans chaque repo. URLs : `radarpartners.api.dev.local` (API), `radarpartners.dev.local` (front).
Conteneurs : `radarpartners-api`, `radarpartners-db`, `radarpartners-front` (le front joint l'API via `http://radarpartners-api` sur le réseau `web`).
Commandes PHP/Composer toujours dans le conteneur : `docker exec radarpartners-api composer ...` ou `php bin/console ...`.
Commits : un repo par projet, chacun avec ses propres commits.

Pièges connus :
- Les recettes Flex (ex. Doctrine) ajoutent des blocs à `docker-compose.yml` et créent `compose.override.yaml` : les retirer, notre compose est à nous.
- PHP 8.4 : pas d'extension `imap` ; lib IMAP en PHP pur (`webklex/php-imap`).
- URL de l'API : `DEFAULT_URI` dans `.env`. CORS à configurer pour le front.
- Secrets (`IMAP_USER`, `IMAP_PASSWORD`) uniquement dans `.env.local`, jamais committés.

## Règles transverses

- **Sources** : ne jamais scraper LinkedIn ni une plateforme dont les CGU l'interdisent ; vérifier les CGU avant d'ajouter une source (détail : `docs/sources.md`).
- **Soft delete** : une `Company` n'est jamais supprimée (`archivedAt`), son historique non plus.
- **Données personnelles** : contacts B2B, origine de chaque donnée conservée, suppression d'un contact possible. Aucune donnée réelle dans le dépôt, les fixtures, les tests ou les logs.
- **Démo publique** : fixtures Faker uniquement ; `APP_DEMO_MODE` = lecture seule, appels LLM simulés ou plafonnés.

## Conventions

- Code, noms de classes et commits **en anglais** ; échanges et documentation en français.
- Commentaires courts, uniquement quand le code ne suffit pas.
- Enums PHP natives pour tous les statuts.
- DTO `readonly` pour les données qui transitent entre couches.
- Un test unitaire par règle de scoring et par parser de source.
- **Ne jamais committer sans validation explicite.**
- En cas d'objectif flou, poser la question avant de coder.
- Garder ce fichier court : le détail va dans `docs/`.

## MVP (1 à 2 semaines)

Voir la liste cochée dans `AVANCEMENT.md`.
