# Front (Nuxt)

> Repo `front/`. Dev : `make up` dans `front/` → `http://radarpartners.dev.local`. Types et libellés reprennent le contrat décrit dans `docs/api.md`.

## Choix

- **SPA** (`ssr: false`) : tableau de bord privé, pas de rendu serveur ; le navigateur appelle l'API directement (`NUXT_PUBLIC_API_BASE_URL`, CORS côté API).
- **Nuxt UI v4** (Tailwind 4) : composants `UTable`, `USelect`, `UCard`, `UBadge`… ; `UApp` à la racine pour les toasts. CSS : `app/assets/css/main.css`.
- Client HTTP : `useApi()` (`$fetch.create` avec la `baseURL` de la config publique). Types de l'API : `app/types/api.ts`. Libellés français et couleurs de statut : `app/utils/labels.ts`.

## Piège : HMR derrière Traefik

Par défaut `nuxt dev` fait tourner l'application dans un processus enfant derrière un processus parent qui relaie les requêtes ; ce relais coupe la WebSocket de Vite (`ECONNRESET`, erreur « WebSocket connection to ws://radarpartners.dev.local/_nuxt/ failed » dans la console). La commande du conteneur utilise donc `nuxt dev --host 0.0.0.0 --no-fork` (`front/.docker/Dockerfile`). Test : une requête d'upgrade WebSocket sur `/_nuxt/?token=…` doit répondre 101, y compris via Traefik.

## Design

Modèle visuel : une capture de tableau de bord « Apex » en mode sombre (non versionnée). Appliqué :

- **Thème sombre uniquement, sans bascule** : `colorMode` forcé (`nuxt.config.ts`), couleur primaire **émeraude** et neutre `neutral` (`app/app.config.ts`), fond quasi noir, cartes `bg-elevated` à coins arrondis et bordure fine (variables `--ui-*` dans `app/assets/css/main.css`). Langue française de Nuxt UI via `UApp :locale="fr"` (`app/app.vue`) ; icônes Lucide embarquées (`@iconify-json/lucide`).
- **Mise en page** (`layouts/default.vue`) : sidebar repliable et redimensionnable (marque, bouton de recherche ⌘K, sections *Prospection* : Entreprises, Contacts, et *Veille* : Signaux avec pastille du nombre d'offres à rattacher), barre du haut avec le titre de la page (`definePageMeta({ title })`) et le bouton « + Entreprise ».
- **Recherche globale ⌘K** (`composables/useGlobalSearch.ts`) : pages filtrées localement, entreprises et contacts cherchés côté serveur (2 caractères minimum, délai de 250 ms, réponses périmées ignorées).
- **Création d'une entreprise** à la main (`components/CompanyCreateModal.vue`), jusque-là impossible hors rattachement d'un signal.
- **Accueil de type tableau de bord** (`pages/index.vue`, alimenté par `GET /api/stats`) : 4 cartes indicateurs avec tendance et mini-courbe (entreprises suivies, à relancer, interactions et offres sur 30 jours), grand graphique d'activité par semaine avec sélecteur (interactions / offres / nouvelles entreprises), donut du pipeline (légende cliquable vers la liste filtrée), barres d'avancement de la base, et trois listes (à relancer, à contacter en priorité, dernières offres). Graphiques en SVG maison, sans librairie (`utils/chart.ts` pour les calculs, testés à part).
- **Pas encore fait** : bloc utilisateur en bas de la sidebar (attend l'authentification).
- Les dates affichées sont les jours calendaires du serveur lus dans la chaîne ISO (`formatDate`), jamais reconvertis dans le fuseau du navigateur.

## Pages et composants

| Fichier | Rôle |
|---|---|
| `pages/index.vue` | Accueil : indicateurs, activité, pipeline, avancement et listes |
| `pages/companies/index.vue` | Liste des entreprises triée par score par défaut : recherche, filtre de statut, tri, « À relancer », pagination ; filtres initialisés depuis l'adresse (`?status=…&due=1`) |
| `pages/companies/[id].vue` | Fiche : infos, statut modifiable, archivage, notes, contacts, historique, signaux |
| `pages/contacts.vue` | Onglet Contacts : tous les contacts avec leur entreprise (liens vers la fiche), recherche, tri, pagination |
| `layouts/default.vue` | Sidebar, barre du haut, recherche ⌘K, modal de création |
| `components/CompanyCreateModal.vue` | Création d'une entreprise (nom, type, zone, ville, site) |
| `components/ContactForm.vue` | Ajout et modification d'un contact ; ajout, modification et suppression (avec confirmation) depuis la carte Contacts |
| `components/CompanyForm.vue` | Modification des informations d'une entreprise (bouton « Modifier » de la carte Informations) ; n'envoie que les champs changés |
| `components/InteractionForm.vue` | Saisie ou modification d'une interaction (bouton « Modifier » dans l'historique) ; les dates inchangées ne sont pas renvoyées |
| `components/ChatBubble.vue` + `composables/useChat.ts` | Amelio, l’assistant : bulle en bas à droite de toutes les pages (⌘J), panneau de chat avec le modèle local ; conversation partagée entre les pages et gardée dans `localStorage` (`radar-chat`, 60 messages, les 20 derniers envoyés) ; sur une fiche entreprise, suggestions de mail / script d'appel / LinkedIn pour cette entreprise |
| `pages/signals.vue` + `components/SignalAttach.vue` | Offres sans entreprise : recherche d'une entreprise et rattachement, ou création ; « Écarter » (onglet Archivées avec « Restaurer »), badge « À vérifier » |
| `components/KpiCard.vue`, `SparklineChart.vue`, `AreaChart.vue`, `DonutChart.vue`, `DashboardCard.vue`, `SegmentedControl.vue` | Briques du tableau de bord (carte indicateur, mini-courbe, courbe d'activité, donut, carte, sélecteur) |
| `components/StatusBadge.vue`, `components/ScoreBadge.vue` | Badges de statut et de score (couleur selon le niveau) ; la fiche affiche le détail du score |

## Vérifications

À lancer dans le conteneur (`docker exec radarpartners-front npm run …`) :

| Commande | Rôle |
|---|---|
| `lint` / `lint:fix` | ESLint (`@nuxt/eslint`, style Stylistic : 2 espaces, sans point-virgule, apostrophes simples) |
| `typecheck` | `nuxt typecheck`. **Pas `vue-tsc --noEmit` seul** : le `tsconfig.json` de Nuxt a `files: []` et des références, `vue-tsc` n'y analyse alors aucun fichier. TypeScript reste en **5** (`vue-tsc` ne supporte pas encore TypeScript 7). |
| `test` | Vitest 5 : projet `unit` (fonctions pures, `test/unit/`) et projet `nuxt` (composants et pages montés dans l'environnement Nuxt, `test/nuxt/`) |

### Tests

- **Formulaires** (`InteractionForm`, `CompanyForm`, `ContactForm`, `SignalAttach`) : `useApi` est simulé (`mockNuxtImport`) et on vérifie ce qui est envoyé à l'API : seuls les champs modifiés, `null` pour un champ vidé, dates inchangées omises, refus d'un contact sans nom, annulation sans appel.
- **Pages** (liste, contacts, signaux, fiche) : `useApi` renvoie `$fetch` et les endpoints sont simulés (`registerEndpoint`) : rendu des données, paramètres envoyés (tri par score par défaut, recherche avec délai, « À relancer »), ouverture des formulaires, confirmation de suppression.
- Pièges : l'application Nuxt est **partagée entre les tests** (`clearNuxtData()` avant chaque test, pages démontées après) ; `runtimeConfig.public.apiBaseUrl` vient du `.env` et n'est pas surchargeable pour les tests, d'où `useApi` simulé.
- `vitest.config.ts` contient un petit plugin qui contourne un défaut de résolution de l'entrée virtuelle de `@nuxt/test-utils` avec Vite 8 (importeur vu comme `/node_modules/...`, imports relatifs introuvables) ; à retirer si une mise à jour de `@nuxt/test-utils` le corrige. Le fichier `.nuxtrc` est créé par l'assistant de `@nuxt/test-utils`.
- Pas de test de bout en bout dans un vrai navigateur : l'affichage visuel reste à vérifier à l'œil.

## Points ouverts

- `npm audit` : 15 alertes (2 critiques) toutes indirectes via `nuxt` (devtools, nitropack) ; le correctif proposé par npm est une rétrogradation en Nuxt 3, à ne pas faire. À suivre aux mises à jour de Nuxt.
- Pas d'authentification ; pas de pagination de la page des signaux (50 premiers).
- Une image `dark-mode-dashboards-apex-2.avif` traîne non suivie à la racine de `front/` (pas de moi) : à ranger ou supprimer.
