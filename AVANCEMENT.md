# Avancement — Radar Partenaires

Dernière mise à jour : 2026-10-12 (sourcing cadré, IMAP connecté)

## MVP

- [x] 1. Docker Compose (PHP/Apache, PostgreSQL, Nuxt) — un compose par repo, Traefik (réseau `web`)
- [x] 2. Entités + migrations
- [x] 3. Import du tableur
- [ ] 4. Source IMAP (alertes email)
- [ ] 5. Résolution d'entreprise + scoring en deux étapes
- [ ] 6. Dashboard Nuxt : liste triée par score, fiche entreprise, saisie d'interaction

## Fait

- Squelette Symfony 8.1 (`api/`) : Doctrine, Messenger, Scheduler, HttpClient, Uid ; dev : MakerBundle, PHPUnit, PHPStan, PHP-CS-Fixer.
- Squelette Nuxt 4 (`front/`), host Vite autorisé via `DOMAIN`.
- Deux repos git indépendants (`api`, `front`).
- Entités `Company`, `Contact`, `Interaction`, `Signal` + migration ; import du tableur (`app:import:prospection`, 28 entreprises importées en dev) ; `PipelineStatusResolver`.
- Connexion IMAP testée sur un Gmail dédié (`webklex/php-imap`) ; variables `IMAP_*` dans `.env`.
- Docs : `README.md` (contexte partageable) et `docs/` (domaine, pipeline, sourcing, import) ; `CLAUDE.md` allégé.

## Décisions

- Deux repos, `CLAUDE.md` et `AVANCEMENT.md` dans `api/` (pas de repo parent).
- PHP 8.4 : pas d'extension `imap` → lib IMAP en PHP pur pour la source email.
- Dockerfiles : cible `dev` uniquement pour l'instant, `prod` à ajouter avec le déploiement K3s. Extension `gd` ajoutée (requise par PhpSpreadsheet).
- Soft delete des entreprises : pas de cascade, `Interaction.company` et `Signal.company` sans `onDelete`.
- Boîte IMAP : Gmail dédié + mot de passe d'application (Outlook.com OAuth seul, Proton Bridge payant, `contact@` à éviter).
- Ordre des sources : référentiel Recherche d'entreprises et IMAP d'abord ; Apify, Welcome to the Jungle, HelloWork ensuite (voir `docs/sources.md`).

## Étape 5 : scoring

- [x] **Score explicable** : 9 règles (`src/Scoring/`), explication enregistrée, recalcul à chaque écriture et par `app:score:recompute` ; affiché dans la liste (tri par défaut) et la fiche. 203 tests. Voir `docs/pipeline.md`.
- [ ] **Résolution d'entreprise** (`ResolveCompany`) pour les signaux avec un nom d'entreprise (LinkedIn) ; Free-Work reste manuel.
- [x] La preuve de besoin (offre dans la stack, télétravail) l'emporte sur l'adéquation de profil ; une entreprise hors région n'est pas pénalisée. 215 tests.
- [ ] Ajuster les poids après quelques semaines d'usage ; règle « urgence » à ajouter.

## Dashboard (étape 6) : première tranche

- [x] **API de lecture** : `GET /api/companies` (filtres, tri, pagination, recherche sans accent) et `GET /api/companies/{id}` (contacts, interactions, signaux), CORS, 91 tests dont des tests sur base `radar_test`. Voir `docs/api.md`.
- [x] **API d'écriture** : création d'entreprise, modification partielle (statut, notes, flags, archivage), saisie d'interaction (statut mis à jour sans écraser un statut manuel), liste des signaux et rattachement à une entreprise. 122 tests. Voir `docs/api.md`.
- [x] **Gestion des contacts** : création, modification et suppression (RGPD, interactions conservées) depuis la fiche ; onglet Contacts (liste globale avec recherche, tri, dernier échange). 157 tests.
- [x] **Modification des informations d'une entreprise** : nom, site, adresse, ville, code postal, téléphone, email, SIREN, NAF, effectif, type, zone (validations, 409 sur nom/SIREN en doublon) ; formulaire dans la fiche. 142 tests.
- [x] **Modification d'une interaction** : `PATCH /api/interactions/{id}`, le statut suit la déduction seulement s'il la suivait déjà ; bouton « Modifier » dans l'historique. 137 tests.
- [~] **Front Nuxt** avec Nuxt UI : liste (filtre « À relancer »), fiche entreprise, formulaire d'interaction et page des signaux sans entreprise écrits ; types vérifiés (`vue-tsc`) et composants compilés par Vite, **pas encore vérifiés visuellement**. ESLint et 50 tests Vitest (formulaires, pages) en place ; `nuxt typecheck` propre. Reste : essai dans le navigateur. Voir `docs/front.md`.
- [ ] Authentification avant toute exposition (prod ou démo).

## En attendant les échantillons d'alertes (ordre recommandé)

1. [x] **Référentiel Recherche d'entreprises** : client, DTO, `app:referential:search` (exploration) et `app:referential:import` (création + rapprochement du tableur, `--dry-run`). **Import réel lancé le 2026-10-13** (agglomération de Besançon, effectif ≥ 1, 10 codes NAF) : 135 créées, 12 rapprochées, soit 163 entreprises en base dont 16 du tableur sans SIREN (nom commercial ≠ dénomination légale, ou hors agglomération : Atol CD, Tête de com, Facton à Lure…). Reste : les rapprocher à part ; la taille servira au scoring (étape 5).
2. [x] **Lecteur IMAP générique** : `ImapMailbox` (lecture seule, mails non lus), `RawEmail`, expéditeur d'origine des transferts manuels, `app:mailbox:list` et `app:mailbox:export`. Reste le marquage « traité » (avec le pipeline). Échantillons Free-Work et LinkedIn analysés : voir `docs/sources.md`.
3. [x] **Socle du pipeline** : `RawSignal`, `SignalSourceInterface`, `FreeWorkAlertParser`, `StackFilter`, `SignalIngester` et `app:signals:ingest [--since] [--dry-run]` (filtre, déduplication par `fingerprint`, enregistrement) faits et testés sur un vrai mail : 1 signal enregistré en base de dev. Reste : parseurs LinkedIn et Indeed dès les premiers vrais mails d'offres ; planifier l'ingestion (Scheduler). Marquage des mails « traités » volontairement non fait : la déduplication rend la relecture sans danger (à reprendre si la boîte grossit trop).
4. [~] **Règles éliminatoires** : filtre de stack fait (titre, mots-clés dans `app.stack_keywords`). Restent type d'entreprise et présentiel hors région.
5. [ ] **Petites dettes** : config PHPStan pour les faux positifs `$id`, fixtures Faker pour la démo.

## À faire / points ouverts

- **Sourcing** : recevoir les premiers mails d'alerte (Free-Work, LinkedIn), les transférer en `.eml` dans `var/import/samples/`, puis écrire lecteur IMAP et parseurs.
- Décidé : offres Free-Work sans entreprise résolues à la main depuis le dashboard ; filtre de stack sur le titre (voir `docs/sources.md`).
- Délai de relance `P6M` (`app.recontact_delay`) choisi par défaut : à ajuster.
- PHPStan niveau max : faux positifs `property.unusedType` sur `$id` des entités (config Doctrine à ajouter).
- Vérifier les noms de paramètres de l'API Recherche d'entreprises (documentation officielle illisible par outil).
- Outils PHPStan, PHP-CS-Fixer, ESLint : installés (API) ou à installer (front), pas encore configurés.
- Identifiants PostgreSQL de dev en clair (`radar`/`radar`) : à sortir avant la prod.
- CORS (`nelmio/cors-bundle`) à prévoir quand le navigateur appellera l'API.
- Interface PostgreSQL : Adminer partagé (`../../adminer-pgsql`), `http://adminer.dev.local` ; réseau externe `pgsql_network` à créer une fois (`docker network create pgsql_network`).
