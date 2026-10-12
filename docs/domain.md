# Modèle de domaine

> Chargé à la demande depuis `CLAUDE.md`. Les entités vivent dans `src/Entity/`.

Le modèle découle des limites du tableur de prospection actuel, où une même cellule mélangeait date, canal, contexte et résultat.

## `Company`
- `siren` (nullable, unique) : clé de rapprochement avec l'API Recherche d'entreprises
- `name`, `website`, `address`, `postalCode`, `city`
- `phone` (standard, normalisé E.164, nullable), `email` (générique, nullable)
- `nafCode`, `headcountRange`
- `type` : enum `CompanyType` → `ESN`, `WEB_AGENCY`, `COM_AGENCY`, `END_CLIENT`, `UNKNOWN`
- `scope` : enum `Scope` → `LOCAL`, `NATIONAL`
- `parent` : `?Company` (filiales et agences d'un groupe, ex. bureau local d'un siège ailleurs)
- `status` : enum `PipelineStatus` → `TO_CONTACT`, `CONTACTED`, `FOLLOW_UP`, `MEETING`, `PARTNER`, `ON_HOLD`, `EXCLUDED`
- `recontactAfter` : `?DateTimeImmutable`
- `score` (int 0–100), `scoreExplanation` (texte)
- `notes` (texte libre)
- `archivedAt` : `?DateTimeImmutable`, soft delete (une entreprise n'est jamais supprimée, ni son historique)
- `flags` : liste libre (ex. `IN_DIFFICULTY`, `MAYBE_INACTIVE`, `BROKEN_CONTACT_FORM`)

## `Contact`
- `company`, `fullName`, `role`, `phone` (E.164, nullable), `email`, `linkedinUrl`, `notes`
- Plusieurs contacts par entreprise, un seul téléphone et un seul email par contact.
- Un contact par personne, jamais un nom et un numéro dans le même champ.

## `Interaction` (historique, une ligne par tentative)
- `company`, `contact` (nullable), `occurredAt`
- `channel` : enum → `PHONE`, `EMAIL`, `IN_PERSON`, `LINKEDIN`, `WEB_FORM`
- `origin` : texte court (événement, recommandation de X, réseau…)
- `outcome` : enum `InteractionOutcome` → `NO_ANSWER`, `VOICEMAIL`, `NOBODY_ON_SITE`, `NO_NEED`, `NO_FREELANCE_POLICY`, `JUST_HIRED`, `MEETING_SCHEDULED`, `POSITIVE`, `OTHER`
- `notes`, `nextActionAt`

## `Signal` (événement daté rattaché à une entreprise)
- `company` (nullable tant que non résolue)
- `type` : enum `SignalType` → `JOB_POSTING`, `FREELANCE_MISSION`, `STACK_DETECTED`, `COMPANY_CREATED`, `RECENT_HIRE`
- `source` (nom de l'adapter), `url`, `title`, `rawContent`
- `extracted` (JSON : stack, modalité remote, TJM, localisation…)
- `publishedAt`, `detectedAt`
- `fingerprint` (unique) : déduplication entre sources

## Règles métier issues du terrain
- `NO_FREELANCE_POLICY` → `status = EXCLUDED`.
- `JUST_HIRED` / `NO_NEED` → `ON_HOLD` + `recontactAfter` (délai par défaut configurable, à ajuster).
- `MEETING_SCHEDULED` → `MEETING`.
- Le statut se déduit de la dernière interaction significative, mais reste modifiable à la main.
- Un signal `JOB_POSTING` sur une entreprise `ON_HOLD` la fait remonter (le besoin a changé).
