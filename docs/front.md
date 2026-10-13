# Front (Nuxt)

> Repo `front/`. Dev : `make up` dans `front/` → `http://radarpartners.dev.local`. Types et libellés reprennent le contrat décrit dans `docs/api.md`.

## Choix

- **SPA** (`ssr: false`) : tableau de bord privé, pas de rendu serveur ; le navigateur appelle l'API directement (`NUXT_PUBLIC_API_BASE_URL`, CORS côté API).
- **Nuxt UI v4** (Tailwind 4) : composants `UTable`, `USelect`, `UCard`, `UBadge`… ; `UApp` à la racine pour les toasts. CSS : `app/assets/css/main.css`.
- Client HTTP : `useApi()` (`$fetch.create` avec la `baseURL` de la config publique). Types de l'API : `app/types/api.ts`. Libellés français et couleurs de statut : `app/utils/labels.ts`.

## Piège : HMR derrière Traefik

Par défaut `nuxt dev` fait tourner l'application dans un processus enfant derrière un processus parent qui relaie les requêtes ; ce relais coupe la WebSocket de Vite (`ECONNRESET`, erreur « WebSocket connection to ws://radarpartners.dev.local/_nuxt/ failed » dans la console). La commande du conteneur utilise donc `nuxt dev --host 0.0.0.0 --no-fork` (`front/.docker/Dockerfile`). Test : une requête d'upgrade WebSocket sur `/_nuxt/?token=…` doit répondre 101, y compris via Traefik.

## Pages et composants

| Fichier | Rôle |
|---|---|
| `pages/index.vue` | Liste des entreprises : recherche, filtre de statut, tri, « À relancer », pagination |
| `pages/companies/[id].vue` | Fiche : infos, statut modifiable, archivage, notes, contacts, historique, signaux |
| `pages/contacts.vue` | Onglet Contacts : tous les contacts avec leur entreprise (liens vers la fiche), recherche, tri, pagination |
| `components/ContactForm.vue` | Ajout et modification d'un contact ; ajout, modification et suppression (avec confirmation) depuis la carte Contacts |
| `components/CompanyForm.vue` | Modification des informations d'une entreprise (bouton « Modifier » de la carte Informations) ; n'envoie que les champs changés |
| `components/InteractionForm.vue` | Saisie ou modification d'une interaction (bouton « Modifier » dans l'historique) ; les dates inchangées ne sont pas renvoyées |
| `pages/signals.vue` + `components/SignalAttach.vue` | Offres sans entreprise : recherche d'une entreprise et rattachement, ou création |
| `components/StatusBadge.vue` | Badge de statut |

## Vérifications

- Types : `docker exec radarpartners-front npx vue-tsc --noEmit` (TypeScript **5** : `vue-tsc` ne supporte pas encore TypeScript 7).
- Pas encore de tests ni d'ESLint ; l'interface n'a pas été vérifiée visuellement (aucun navigateur côté outils de développement).

## Points ouverts

- `npm audit` : 15 alertes (2 critiques) toutes indirectes via `nuxt` (devtools, nitropack) ; le correctif proposé par npm est une rétrogradation en Nuxt 3, à ne pas faire. À suivre aux mises à jour de Nuxt.
- Pas d'authentification ; pas de pagination de la page des signaux (50 premiers).
