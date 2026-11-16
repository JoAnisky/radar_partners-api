# Import du tableur existant

> Commande implémentée : `app:import:prospection` (`src/Import/`, `src/Command/`).

Commande `app:import:prospection <fichier.xlsx>` (PhpSpreadsheet) :
- une ligne → une `Company` (`scope = LOCAL`) ;
- « Personne à contacter » → `Contact` (extraire un éventuel téléphone entre parenthèses) ; un numéro seul, sans nom, va dans `Company.phone` (pas de `Contact`) ;
- « Date/Type de contact » et « Relance » → une ou plusieurs `Interaction` (dates au format `jj/mm/aa`, `jj/mm/aaaa` ou `mm/aa`) ;
- « Réponse » / « Note » → `outcome` + `notes`, avec un mapping de mots-clés (« pas de besoin », « ne travaillent pas avec des freelances », « viennent d'embaucher »…) ; ce qui n'est pas reconnu va en `OTHER` ;
- cellules `=HYPERLINK(url, nom)` → `website` + `name` ;
- produire un rapport des lignes ambiguës plutôt que de deviner.

## Décisions d'implémentation

- Dates découpées par date trouvée ; le canal est détecté dans le texte (avant ou après la date). « Tel, puis mail » → deux interactions.
- `Réponse` et `Note` s'appliquent à l'interaction la plus récente ; `Note` va aussi dans `Company.notes` (+ flags détectés).
- Statut déduit par `PipelineStatusResolver` ; délai de relance `app.recontact_delay` (`P6M`, `config/services.yaml`).
- Une entreprise dont le nom existe déjà est ignorée (rejouable sans doublon) ; `--dry-run` annule la transaction.
- Cas flous (prénom seul, année seule, date ambiguë, canal inconnu, RDV à vérifier) : rapport en fin de commande, texte conservé dans les notes.
- Tests avec données fictives uniquement ; le fichier réel reste dans `var/import/` (ignoré par git).

## CSV d'agences (`app:import:agencies`)

`app:import:agencies <fichier.csv> [--dry-run]` : colonnes Agence, Ville, Zone, Priorité, Statut, Date contact, Prochaine action, Interlocuteur, Infos (stack, taille), Notes.
- Rapprochement avec l'existant par `CompanyNameMatcher` (« Agence Facton » = « Facton »). Une entreprise existante **n'est jamais écrasée** : seuls les champs vides sont complétés (type inconnu, zone, ville, notes). Plusieurs correspondances : ignorée et signalée.
- Nouvelle agence : `TO_CONTACT`, ville = première ville citée (les autres vont dans les notes), zone selon la colonne Zone, périmètre `LOCAL` sauf Suisse (`NATIONAL`), type déduit des mots de l'agence et de ses infos (web, Symfony, WordPress… → agence web ; com, communication, pub → agence de com ; sinon inconnu). Priorité, infos et prochaine action vont dans les notes (pas de champ dédié, aucun effet sur le score).
- Date de contact et interlocuteur ne sont pas gérés : signalés si renseignés. Rejouable sans doublon.
