# API HTTP

> Contrôleurs dans `src/Controller/Api/`, format de réponse dans `src/Api/`. URL de dev : `http://radarpartners.api.dev.local`. Pas d'authentification pour l'instant (local uniquement) : à ajouter avant toute exposition.

## Endpoints

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

### `POST /api/companies`

Crée une entreprise : `name` (obligatoire), `scope` (défaut `NATIONAL`), `type` (défaut `UNKNOWN`), `website`, `city`, `notes`. `201` avec la fiche ; `409` + `id` de l'existante si un nom identique (sans casse) existe, archivée ou non ; `422` si invalide.

### `PATCH /api/companies/{id}`

Modification partielle : seuls les champs présents sont touchés. Champs : `status`, `type`, `scope`, `notes` (`null` pour vider), `recontactAfter` (date ou `null`), `flags` (liste de `MAJUSCULES_`), `archived` (booléen), et l'identité : `name` (obligatoire, unique), `website` (`https://` ajouté si absent), `address`, `postalCode`, `city`, `phone` (normalisé en E.164, `422` si invalide), `email` (minuscules, validé), `siren` (9 chiffres, unique), `nafCode` (ex. `62.01Z`), `headcountRange`. Une chaîne vide ou `null` vide le champ. Champ inconnu ou valeur invalide : `422` ; nom ou SIREN déjà pris par une autre entreprise : `409`. Renvoie la fiche.

Règle : un changement de `status` vers `ON_HOLD` sans `recontactAfter` fixe la date à maintenant + `app.recontact_delay` ; vers un autre statut, la date est effacée.

### `POST /api/companies/{id}/interactions`

Saisie d'une interaction : `channel` (obligatoire), `occurredAt` (défaut maintenant), `outcome` (défaut `OTHER`), `contactId` (doit appartenir à l'entreprise), `origin`, `notes`, `nextActionAt`. `201` avec `{ interaction, company }` (résumé à jour).

Règle de statut (`PipelineStatusResolver::register`) : seule une interaction **significative et la plus récente** change le statut (et la date de relance) ; sinon un statut posé à la main est conservé, sauf `TO_CONTACT` qui passe à `CONTACTED`.

### `PATCH /api/interactions/{id}`

Modification partielle d'une interaction : `channel`, `occurredAt` (non nul), `outcome`, `contactId` (`null` pour détacher ; doit appartenir à l'entreprise), `origin`, `notes`, `nextActionAt` (`null` pour vider). Champ inconnu ou valeur invalide : `422`. Renvoie `{ interaction, company }`.

Règle de statut (`PipelineStatusResolver::followEdit`) : si la date ou le résultat change, le statut est recalculé **seulement s'il suivait déjà la déduction faite sur l'ancien historique** (dernière interaction significative) ; un statut posé à la main est conservé, comme une date de relance modifiée à la main si rien ne change dans la déduction. Pas de suppression d'interaction (l'historique est conservé).

### `GET /api/signals`

Signaux, plus récents d'abord ; `unresolved=1` pour ceux sans entreprise ; `page`, `perPage`. Chaque item porte `companyId` et `companyName`.

### `PATCH /api/signals/{id}`

`{"companyId": 12}` rattache, `{"companyId": null}` détache ; rien d'autre n'est accepté. Règle : rattacher une offre (`JOB_POSTING` ou `FREELANCE_MISSION`) à une entreprise `ON_HOLD` met sa date de relance à maintenant : elle remonte dans « à relancer ».

## Décisions techniques

- Pas d'authentification pour l'instant ; aucun endpoint de suppression (soft delete par `archived`).
- Réponses construites à la main (`CompanyPresenter`), pas de sérialisation d'entités : contrôle de ce qui sort.
- Paramètres de liste mappés par `#[MapQueryString]` (`CompanyListQuery`) avec validation.
- CORS (`nelmio/cors-bundle`) limité à `/api/`, origines via `CORS_ALLOW_ORIGIN` (`radarpartners.dev.local` et localhost).
- Recherche sans accent : extension PostgreSQL `unaccent` (migration) + fonction DQL `UNACCENT` (`src/Doctrine/`).
- Apache : `FallbackResource /index.php` dans l'image (pas de `.htaccess`).

## Tests

Les tests d'API (`tests/Api/`) tournent sur la base `radar_test`, chaque test dans une transaction annulée. À créer une fois :
`docker exec radarpartners-api php bin/console doctrine:database:create --env=test` puis `doctrine:migrations:migrate --env=test`.
`phpunit.dist.xml` force `APP_ENV=test` aussi dans `$_ENV` (le conteneur définit `APP_ENV=dev`).
