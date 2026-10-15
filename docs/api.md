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
| `hidePartners` | `1` : exclut les `PARTNER` (sans effet si `status` est donné) | |
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

### `GET /api/contacts`

Tous les contacts des entreprises **non archivées**, paginés : `{ items, total, page, perPage }`. Chaque item reprend le contact et ajoute `company` (`id`, `name`, `city`, `status`) et `lastInteractionAt` (dernier échange où le contact est cité).

Paramètres : `q` (nom, rôle, email, nom d'entreprise ; sans casse ni accents), `sort` (`fullName` par défaut, `company`, `lastInteractionAt` valeurs vides en dernier, `createdAt`), `direction`, `page`, `perPage` (1 à 100). Paramètre invalide : `422`. La création se fait depuis une entreprise (ci-dessous).

### `POST /api/companies/{id}/contacts`, `PATCH /api/contacts/{id}`, `DELETE /api/contacts/{id}`

Contact d'une entreprise : `fullName` (obligatoire, non vide), `role`, `phone` (normalisé en E.164, `422` si invalide), `email` (minuscules, validé), `linkedinUrl` (`https://` ajouté si absent), `notes`. Création : `201` avec le contact ; modification partielle (`null` ou chaîne vide pour vider, `fullName` ne peut pas être vidé) ; champ inconnu ou valeur invalide : `422`.

`DELETE` (suppression d'un contact, exigence RGPD) répond `204` : le contact disparaît, **ses interactions sont conservées** et simplement détachées (`contactId` à `null`, clé étrangère `ON DELETE SET NULL`). Un texte libre (notes d'interaction) qui citerait la personne n'est pas modifié.

### `PATCH /api/interactions/{id}`

Modification partielle d'une interaction : `channel`, `occurredAt` (non nul), `outcome`, `contactId` (`null` pour détacher ; doit appartenir à l'entreprise), `origin`, `notes`, `nextActionAt` (`null` pour vider). Champ inconnu ou valeur invalide : `422`. Renvoie `{ interaction, company }`.

Règle de statut (`PipelineStatusResolver::followEdit`) : si la date ou le résultat change, le statut est recalculé **seulement s'il suivait déjà la déduction faite sur l'ancien historique** (dernière interaction significative) ; un statut posé à la main est conservé, comme une date de relance modifiée à la main si rien ne change dans la déduction. Pas de suppression d'interaction (l'historique est conservé).

### `GET /api/signals`

Signaux, plus récents d'abord ; `unresolved=1` pour ceux sans entreprise ; `dismissed=1` pour les seuls signaux écartés (ils sont exclus sinon, y compris de l'accueil) ; `page`, `perPage`. Chaque item porte `companyId`, `companyName` et `dismissedAt`.

### `PATCH /api/signals/{id}`

`{"companyId": 12}` rattache (et restaure un signal écarté), `{"companyId": null}` détache ; `{"dismissed": true}` écarte (`422` si le signal a une entreprise), `{"dismissed": false}` restaure. Un seul champ à la fois, rien d'autre n'est accepté. Règle : rattacher une offre (`JOB_POSTING` ou `FREELANCE_MISSION`) à une entreprise `ON_HOLD` met sa date de relance à maintenant : elle remonte dans « à relancer ».

### `GET /api/stats`

Tout ce qu'affiche l'accueil, en un appel (`src/Stats/StatsProvider.php`) :

- `weeks` : lundis des 12 dernières semaines (la semaine en cours en dernier) ; chaque série est alignée dessus.
- `kpis` : `companies` (`total`, `createdLast7Days`, `series` cumulée), `due` (`total`), `interactions` et `signals` (`last30Days`, `previous30Days`, `deltaPercent` ou `null` sans période de comparaison, `series` hebdomadaire ; `signals.unresolved` = offres à rattacher).
- `activity` : séries hebdomadaires `interactions`, `signals` et `companies` (nouvelles entreprises).
- `pipeline` : tous les statuts, dans l'ordre de l'énumération, avec leur nombre.
- `progress` : `total`, `contacted` (au moins une interaction), `reachable` (au moins un contact avec téléphone ou email), `typed` (type renseigné).
- `lists` : `due` (relance dépassée ou action échue), `priorities` (« à contacter » les mieux classées) et `recentSignals`, 5 éléments maximum chacune.

Les entreprises archivées sont exclues partout.

## Score

`score` (0–100) figure dans les résumés et `scoreExplanation` (lignes « +20 Agence web », …, « Total : 40 ») dans la fiche. Il est recalculé après chaque écriture qui le touche ; le tri `sort=score` fonctionne sur `GET /api/companies`. Règles et poids : `docs/pipeline.md`.

## Décisions techniques

- Pas d'authentification pour l'instant ; seule suppression : les contacts (RGPD). Entreprises : soft delete (`archived`) ; interactions : modification seulement (l'historique est conservé).
- Réponses construites à la main (`CompanyPresenter`), pas de sérialisation d'entités : contrôle de ce qui sort.
- Paramètres de liste mappés par `#[MapQueryString]` (`CompanyListQuery`) avec validation.
- CORS (`nelmio/cors-bundle`) limité à `/api/`, origines via `CORS_ALLOW_ORIGIN` (`radarpartners.dev.local` et localhost).
- Recherche sans accent : extension PostgreSQL `unaccent` (migration) + fonction DQL `UNACCENT` (`src/Doctrine/`).
- Apache : `FallbackResource /index.php` dans l'image (pas de `.htaccess`).

## Tests

Les tests d'API (`tests/Api/`) tournent sur la base `radar_test`, chaque test dans une transaction annulée. À créer une fois :
`docker exec radarpartners-api php bin/console doctrine:database:create --env=test` puis `doctrine:migrations:migrate --env=test`.
`phpunit.dist.xml` force `APP_ENV=test` aussi dans `$_ENV` (le conteneur définit `APP_ENV=dev`).
