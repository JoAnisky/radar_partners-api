# Pipeline et scoring

> Chargé à la demande depuis `CLAUDE.md`. Cible : aucune étape n'est encore implémentée (seul l'import du tableur existe).

```
SignalSource (adapter) → RawSignal (DTO)
  → [Messenger] IngestSignal      dédup par fingerprint, persistance
  → [Messenger] ResolveCompany    rapprochement SIREN / nom via API Recherche d'entreprises
  → [Messenger] AnalyzeSignal     1) règles PHP (éliminatoires)  2) LLM (extraction + angle d'approche)
  → [Messenger] ScoreCompany      recalcul du score à partir de tous ses signaux
```

## Interfaces clés

```php
interface SignalSourceInterface
{
    public function getName(): string;

    /** @return iterable<RawSignal> */
    public function fetch(\DateTimeImmutable $since): iterable;
}

interface EnricherInterface
{
    public function supports(Company $company): bool;
    public function enrich(Company $company): void;
}

interface ScoringRuleInterface
{
    /** @return list<ScoreContribution> vide si la règle ne s'applique pas */
    public function evaluate(Company $company, ScoringContext $context): array;
}
```

- Implémenté : `src/Scoring/` (voir plus bas), `src/Signal/` (`RawSignal`, `SignalSourceInterface` taguée `app.signal_source`, `StackFilter`, `SignalIngester`) et `src/Signal/FreeWork/`. `app:signals:ingest` remplace pour l'instant le message Messenger `IngestSignal` (appel synchrone).
- Une source = une classe qui implémente `SignalSourceInterface`, taguée automatiquement (`#[AutoconfigureTag]`). Ajouter une source ne modifie pas le pipeline.
- Même principe pour les enrichers et les règles de scoring.
- Chaque `ScoreContribution` porte un libellé : le score doit toujours être explicable.

## Scoring

Implémenté dans `src/Scoring/`. Une règle = une classe taguée `app.scoring_rule` (`ScoringRuleInterface`) ; elle reçoit l'entreprise et un `ScoringContext` (interactions, signaux, date) chargé une seule fois, et renvoie des `ScoreContribution` (points + libellé, ou « éliminatoire »). `CompanyScorer` additionne, trie par points décroissants, plafonne à 0–100 ; une règle éliminatoire met le score à 0 et ne garde que sa raison. Le détail (« +20 Agence web », …, « Total : 40 ») est enregistré dans `Company.scoreExplanation`.

| Règle | Contribution |
|---|---|
| `CompanyTypeRule` | ESN / agence web / agence de com **+20** ; client final **éliminatoire** |
| `ActivityRule` | type inconnu seulement : NAF cible (62.01Z, 62.02A/B, 62.03Z, 62.09Z, 63.11Z, 63.12Z, 73.11Z) **+15** ; édition de logiciels (58.29A/C) **+5** |
| `HeadcountRule` | 1-2 **+3** ; 3-5 **+8** ; 6-9, 10-19, 20-49 **+15** ; 50-99 **+8** ; au-delà 0 |
| `ScopeRule` | zone locale **+10** |
| `SignalRule` | signaux rattachés des 90 derniers jours : **+25** puis +5 par offre en plus (max **+40**) ; un signal de moins de 14 jours **+10** |
| `RelationRule` | échange positif ou rendez-vous **+25** ; contact physique (personne rencontrée) **+10** ; recommandation (« reco » dans l'origine) **+10** |
| `StatusRule` | exclue **éliminatoire** ; partenaire **+30** ; en attente **−15** (sauf si la date de relance est passée) |
| `FlagsRule` | en difficulté **−15** ; peut-être inactive **−15** |
| `ContactRule` | un contact avec téléphone ou email **+5** |

Les poids sont des constantes en tête de chaque règle. Le score est recalculé : à chaque écriture de l'API qui le touche (entreprise, interaction, contact, rattachement d'un signal) et en bloc par `app:score:recompute [--top=N] [--dry-run]` (à lancer après un import ou un changement de règle).

Pas encore couvert : modalité (remote ou présentiel hors région) et urgence « offre ouverte depuis plus de 30 jours / republiée » (demandent de suivre la présence d'une offre dans le temps).

Le LLM n'est appelé **que** sur les signaux qui passent les règles éliminatoires.
