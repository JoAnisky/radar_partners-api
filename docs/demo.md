# Démo publique

> Vitrine en lecture seule, données fictives, sur K3s (comme `portfolio_jl_v3`). Front : `radar.jonathanlore.fr`, API : `api.radar.jonathanlore.fr`.

## Mode démo

`APP_DEMO_MODE=true` (API) et `NUXT_PUBLIC_DEMO_MODE=true` (front) : écritures refusées en 403 (`DemoReadOnlyListener`), boutons grisés, bandeau, assistant factice (`docs/assistant.md`). `app:demo:seed` **efface toute la base** puis la remplit (`src/Demo/`), il refuse de tourner hors mode démo.

## En local

`make demo-up` (dans `api/` puis `front/`) lance `radardemo.dev.local` / `radardemo.api.dev.local` sur la base `radar_demo` (hosts Windows à ajouter). Commandes : `docs/commandes.md`.

## Images

Cibles `prod` des deux Dockerfiles (`docker build --target prod -f .docker/Dockerfile .`) : `joanisky/radarpartners-api` (Apache, vendor sans dev, cache chauffé) et `joanisky/radarpartners-front` (Nitro, config lue au démarrage depuis l'environnement). `.dockerignore` exclut `.env.local` et `config/assistant/profile.md` : ne pas l'affaiblir, un build local les embarquerait.

## Kubernetes

`k8s/` (Kustomize) dans chaque repo : namespaces `radarpartners-api` (API, PostgreSQL 17 + PVC, CronJob `radar-demo-reset`, le lundi à 3 h) et `radarpartners-front`. Hors Kustomize : `k8s/jobs/job-migrate.yaml` (Job immuable, à supprimer puis recréer à chaque déploiement). Sondes sur `GET /api/health` (sans base).

Secrets à créer avant le premier `kubectl apply -k` (ils ne sont pas dans le dépôt) :
- `radar-api-env` (namespace `radarpartners-api`) : `APP_SECRET`, `DATABASE_URL` (`postgresql://radar:<mot de passe>@radar-db:5432/radar?serverVersion=17&charset=utf8`).
- `radar-db-secret` (même namespace) : `password`, le même mot de passe.

Premier déploiement : appliquer, lancer le Job de migration, puis `kubectl create job --from=cronjob/radar-demo-reset radar-demo-seed-first -n radarpartners-api` pour remplir la base.
