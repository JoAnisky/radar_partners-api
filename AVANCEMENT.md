# Avancement — Radar Partenaires

Dernière mise à jour : 2026-10-08

## MVP

- [x] 1. Docker Compose (PHP/Apache, PostgreSQL, Nuxt) — un compose par repo, Traefik (réseau `web`)
- [ ] 2. Entités + migrations
- [ ] 3. Import du tableur
- [ ] 4. Source IMAP (alertes email)
- [ ] 5. Résolution d'entreprise + scoring en deux étapes
- [ ] 6. Dashboard Nuxt : liste triée par score, fiche entreprise, saisie d'interaction

## Fait

- Squelette Symfony 8.1 (`api/`) : Doctrine, Messenger, Scheduler, HttpClient, Uid ; dev : MakerBundle, PHPUnit, PHPStan, PHP-CS-Fixer.
- Squelette Nuxt 4 (`front/`), host Vite autorisé via `DOMAIN`.
- Deux repos git indépendants (`api`, `front`).

## Décisions

- Deux repos, `CLAUDE.md` et `AVANCEMENT.md` dans `api/` (pas de repo parent).
- PHP 8.4 : pas d'extension `imap` → lib IMAP en PHP pur pour la source email.
- Dockerfiles : cible `dev` uniquement pour l'instant, `prod` à ajouter avec le déploiement K3s.

## À faire / points ouverts

- Outils PHPStan, PHP-CS-Fixer, ESLint : installés (API) ou à installer (front), pas encore configurés.
- Identifiants PostgreSQL de dev en clair (`radar`/`radar`) : à sortir avant la prod.
- CORS (`nelmio/cors-bundle`) à prévoir quand le navigateur appellera l'API.
- Interface PostgreSQL : Adminer partagé (`../../adminer-pgsql`), `http://adminer.dev.local` ; réseau externe `pgsql_network` à créer une fois (`docker network create pgsql_network`).
