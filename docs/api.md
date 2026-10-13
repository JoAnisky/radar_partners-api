# API HTTP

> Contrôleurs dans `src/Controller/Api/`, format de réponse dans `src/Api/`. URL de dev : `http://radarpartners.api.dev.local`. Pas d'authentification pour l'instant (local uniquement) : à ajouter avant toute exposition.

## Endpoints (lecture seule)

### `GET /api/companies`

Liste paginée. Réponse : `{ items, total, page, perPage }` ; chaque item : id, name, siren, website, city, postalCode, phone, nafCode, headcountRange, type, scope, status, score, flags, recontactAfter, lastInteractionAt, archived.

| Paramètre | Valeurs | Défaut |
|---|---|---|
| `q` | texte, insensible à la casse et aux accents (nom, ville, SIREN) | |
| `status` | `TO_CONTACT`, `CONTACTED`, `FOLLOW_UP`, `MEETING`, `PARTNER`, `ON_HOLD`, `EXCLUDED` | |
| `scope` | `LOCAL`, `NATIONAL` | |
| `due` | `1` : relance dépassée, ou prochaine action échue sur la dernière interaction | |
| `archived` | `1` : seulement les archivées (sinon exclues) | |
| `sort` | `name`, `score`, `recontactAfter`, `lastInteractionAt`, `createdAt` (valeurs vides en dernier) | `name` |
| `direction` | `asc`, `desc` | `asc` |
| `page`, `perPage` | `perPage` de 1 à 100 | 1, 25 |

Paramètre invalide : `422`.

### `GET /api/companies/{id}`

Résumé + `address`, `email`, `parentId`, `scoreExplanation`, `notes`, `createdAt`, `contacts[]`, `interactions[]` (plus récente d'abord), `signals[]`. `404` si inconnue.

## Décisions techniques

- Réponses construites à la main (`CompanyPresenter`), pas de sérialisation d'entités : contrôle de ce qui sort.
- Paramètres de liste mappés par `#[MapQueryString]` (`CompanyListQuery`) avec validation.
- CORS (`nelmio/cors-bundle`) limité à `/api/`, origines via `CORS_ALLOW_ORIGIN` (`radarpartners.dev.local` et localhost).
- Recherche sans accent : extension PostgreSQL `unaccent` (migration) + fonction DQL `UNACCENT` (`src/Doctrine/`).
- Apache : `FallbackResource /index.php` dans l'image (pas de `.htaccess`).

## Tests

Les tests d'API (`tests/Api/`) tournent sur la base `radar_test`, chaque test dans une transaction annulée. À créer une fois :
`docker exec radarpartners-api php bin/console doctrine:database:create --env=test` puis `doctrine:migrations:migrate --env=test`.
`phpunit.dist.xml` force `APP_ENV=test` aussi dans `$_ENV` (le conteneur définit `APP_ENV=dev`).
