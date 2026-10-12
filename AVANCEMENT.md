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

## À faire / points ouverts

- **Sourcing** : recevoir les premiers mails d'alerte (Free-Work, LinkedIn), les transférer en `.eml` dans `var/import/samples/`, puis écrire lecteur IMAP et parseurs.
- **Décision** : règle de filtrage par stack (un signal sans mot-clé de la stack n'est pas retenu), voir `docs/sources.md`.
- Délai de relance `P6M` (`app.recontact_delay`) choisi par défaut : à ajuster.
- PHPStan niveau max : faux positifs `property.unusedType` sur `$id` des entités (config Doctrine à ajouter).
- Vérifier les noms de paramètres de l'API Recherche d'entreprises (documentation officielle illisible par outil).
- Outils PHPStan, PHP-CS-Fixer, ESLint : installés (API) ou à installer (front), pas encore configurés.
- Identifiants PostgreSQL de dev en clair (`radar`/`radar`) : à sortir avant la prod.
- CORS (`nelmio/cors-bundle`) à prévoir quand le navigateur appellera l'API.
- Interface PostgreSQL : Adminer partagé (`../../adminer-pgsql`), `http://adminer.dev.local` ; réseau externe `pgsql_network` à créer une fois (`docker network create pgsql_network`).
