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
    public function evaluate(Company $company): ScoreContribution;
}
```

- Une source = une classe qui implémente `SignalSourceInterface`, taguée automatiquement (`#[AutoconfigureTag]`). Ajouter une source ne modifie pas le pipeline.
- Même principe pour les enrichers et les règles de scoring.
- Chaque `ScoreContribution` porte un libellé : le score doit toujours être explicable.

## Scoring (première version)

| Dimension | Règle |
|---|---|
| Type | ESN / agence web / agence com → OK ; `END_CLIENT` → éliminatoire |
| Stack | Symfony, WordPress, Vue/Nuxt, DevOps → fort |
| Modalité | Full remote ou local → OK ; présentiel hors région → éliminatoire |
| Urgence | Offre ouverte > 30 j ou republiée → bonus |
| Taille | 5–50 salariés → bonus |
| Relation | Recommandation ou contact physique positif → bonus |

Le LLM n'est appelé **que** sur les signaux qui passent les règles éliminatoires.
