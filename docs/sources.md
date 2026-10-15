# Sourcing — sources de signaux et référentiel

> Décisions et pistes. À jour au 2026-10-12. Les points marqués « non vérifié » viennent de recherches sans source officielle.

## Grille pour juger une source

1. Alerte par mail avec requête précise (stack, région, remote) ?
2. Le mail contient entreprise, titre et lien, sans clic intermédiaire ?
3. CGU et `robots.txt` autorisent l'usage ?
4. Annonceur = l'entreprise qui recrute, ou un intermédiaire ? (une ESN qui publie est une cible ; un cabinet de recrutement masque le client)

**Interdit** : scraper LinkedIn ou toute plateforme dont les CGU l'interdisent.

## Référentiel (ce qui existe)

- **API Recherche d'entreprises** : accès ouvert, 7 appels/s, Open Licence 2.0. Client : `src/Referential/ReferentialClient.php` ; commande d'exploration en lecture seule : `app:referential:search`.
- **Import local** : `app:referential:import [--dry-run]` (défauts : agglomération de Besançon `epci=242500361`, effectif ≥ 1 salarié, liste de NAF ci-dessous ; la taille sert ensuite au scoring plutôt qu'à filtrer). Crée une `Company` (`LOCAL`, `TO_CONTACT`, `UNKNOWN`) par entreprise absente, complète `siren`/`nafCode`/`headcountRange` des entreprises du tableur dont nom **et** lieu correspondent (`CompanyNameMatcher`), signale les cas ambigus sans rien modifier, et liste les entreprises du tableur restées sans correspondance (effectif < 6, autre NAF ou nom différent). Les nouvelles entreprises dont le siège est ailleurs ont « Siège : … » dans leurs notes.
- **Paramètres validés par appels réels** : `q` (3 caractères min, sinon un filtre), `activite_principale` (liste séparée par des virgules), `departement`, `code_postal`, `tranche_effectif_salarie` (codes INSEE : 03 = 6-9, 11 = 10-19, 12 = 20-49…), `etat_administratif=A`, `per_page` (**25 max**), `page`. Un rayon géographique (`lat`/`long`/`radius`) n'est pas accepté sur `/search` (autre point d'accès, à explorer).
- **Spec OpenAPI** (fichier local `var/import/openapi.json`, ignoré par git) : `/search` accepte aussi `epci`, `code_commune`, `region`, `categorie_entreprise` (PME/ETI/GE), `sort_by_size`, `minimal`/`include` ; `limite_matching_etablissements` vaut 10 par défaut (le client demande 100). `/near_point` (rayon ≤ 50 km) filtre seulement par NAF : **pas de filtre d'effectif**.
- **Volumes avec effectif ≥ 6 (codes 03,11,12,21,22)** : agglomération de Besançon (`epci=242500361`) 72, Doubs 87, code postal 25000 55 ; rayon 30 km (sans filtre d'effectif) 1 601.
- **Pièges** : les résultats sont des unités légales ; le siège peut être ailleurs (Flowbird à Neuilly, établissement à Besançon). L'établissement local est dans `matching_etablissements`, à ne retenir que s'il est actif. Les filtres s'appliquent aux établissements, le NAF à l'unité légale.
- **Rapprochement par nom peu fiable** : nom commercial ≠ dénomination légale (4 entreprises sur 7 testées sans résultat avec nom + code postal ; une homonyme dans une autre ville renvoie un faux positif). Préférer lister le référentiel local (NAF + zone + effectif) puis rapprocher (pg_trgm sur nom, adresse).
- **Volumes (NAF 62.01Z, 62.02A/B, 62.03Z, 62.09Z, 63.11Z, 63.12Z, 73.11Z, 58.29A/C)** : 1 217 unités dans le Doubs, 5 447 en Bourgogne-Franche-Comté, majoritairement des micro-structures : filtre d'effectif indispensable.
- Le NAF ne distingue pas ESN et agence web → `type = UNKNOWN` jusqu'à analyse du site. La réponse contient aussi `activite_principale_naf25` (nouvelle nomenclature, ex. 62.01Z → 62.20H) ; `Company.nafCode` garde l'ancienne pour l'instant.
- Filtre sur la date de création pour `COMPANY_CREATED` : non vérifié.

## Signaux

| Source | Statut |
|---|---|
| **Free-Work** (alerte mail quotidienne le matin) | MVP, alerte créée, aucun échantillon reçu. Beaucoup de bruit (Angular, Java, React) ; pas de champ d'exclusion trouvé, les compétences du profil pilotent peut-être l'alerte (non vérifié). |
| **LinkedIn** (alertes natives par mail) | MVP, via règle de transfert depuis la boîte principale. Deux alertes freelance créées : dernières 24 h (flux) et sans limite de durée (amorçage, à supprimer ensuite). Parseur fait (2026-10-15). |
| **Welcome to the Jungle, HelloWork** | Piste après Free-Work. Emploi salarié : signal « entreprise qui recrute un dev ». Alertes mail non vérifiées. |
| **API France Travail** | Non vérifié, ne pas en dépendre. Jeu open data trimestriel : trop lent pour un signal. |
| **Apify** | Piste après l'IMAP : détection de stack (`Enricher`) sur les sites connus ; offres WTTJ éventuelles après vérification des CGU. Actors communautaires, responsabilité de l'usage à l'utilisateur. Plan gratuit 5 $/mois. |
| Pages carrières des entreprises connues | Idée, CGU et `robots.txt` à vérifier au cas par cas. |

## Filtre de stack (décidé le 2026-10-13)

Un signal n'est retenu que si son **titre** contient un mot entier de la liste `app.stack_keywords` (`config/services.yaml`) : symfony, php, laravel, wordpress, vue, nuxt, docker, kubernetes, k3s, devops. Implémenté dans `StackFilter`. « fullstack » seul ne qualifie pas (les offres fullstack reçues étaient Java/.NET/Angular) ; React n'est pas dans la liste. La catégorie de l'URL Free-Work est ignorée : elle regroupe plusieurs technos (`…react-angular-vue`). Sur l'échantillon du 2026-10-13 : 1 offre retenue sur 10.

**Mise à jour (2026-10-15) : catégorie « à vérifier »**. Un titre qui ne nomme **aucune** techno (« Développeur Freelance », « Dev FullStack ») n'est plus écarté : le signal est enregistré avec `extracted.toReview = true` et un badge « À vérifier » dans la page Signaux. Sont écartés seulement les titres qui nomment une autre techno connue (liste `app.other_stack_keywords`, `config/services.yaml`) sans mot-clé de la stack. Une techno absente des deux listes tombe en « à vérifier » : compléter la liste d'exclusion si le bruit gêne.

## Structure des alertes (échantillons du 2026-10-13)

Échantillons dans `var/import/samples/` (ignoré par git ; obtenus avec `app:mailbox:export`).

- **Free-Work** (`jobs@free-work.com`, « N offres matchant avec vos critères », 1 mail/jour) :
  - HTML régulier : `<li><a href="lien tracké"><b>Titre - <span>Contrat</span></b><br>durée [- salaire] [- TJM] - Ville, Région</a></li>`.
  - **Aucun nom d'entreprise** dans le mail.
  - Le dernier segment du lien tracké `mjt.lu` est l'URL de l'offre en base64 (décodable sans réseau) : `https://www.free-work.com/fr/tech-it/job-mission/<catégorie>/<slug>?utm…`. Le slug identifie l'offre (fingerprint) ; la catégorie (`developpeur-java-kotlin-groovy-scala`…) donne un indice de stack.
  - Les 13 offres reçues étaient toutes hors stack (.NET, Java, Angular, Python, Go) : le filtre par stack est indispensable.
- **LinkedIn** (`jobalerts-noreply@linkedin.com`, échantillon réel du 2026-10-15, parseur `src/Signal/LinkedIn/`) : un lien `/jobs/view/{id}` (l'id sert de fingerprint) portant le titre, puis un `<p>` « Entreprise · Lieu (Hybride|À distance) ». Le mail annonce 11 offres mais n'en contient que 6. Entreprise toujours présente (`companyName`, à rapprocher via `ResolveCompany`, pas encore fait). Le mail « alerte créée » est ignoré par le parseur. Ancien constat : le mail « votre alerte a été créée » était le seul reçu ; il liste des offres avec **le nom de l'entreprise** (« Entreprise · Lieu »), titre et lien. Les alertes réelles auront probablement la même mise en page (à confirmer). Ce mail de confirmation n'est pas une alerte d'offres : à ignorer (objet « a été créée »).
- **Transfert manuel** : l'expéditeur visible est celui qui transfère ; l'expéditeur d'origine est lu dans le bandeau « Forwarded message » (`RawEmail::isFrom()`). Le comportement des règles de transfert automatiques reste à confirmer.

### Conséquence : l'entreprise n'est pas toujours connue

Free-Work ne donne pas l'entreprise : `Signal.company` reste nul. **Décision (2026-10-13) : résolution manuelle** — le dashboard affiche le lien de l'offre, l'utilisateur saisit l'entreprise pour les offres qui l'intéressent. Pas de requête automatique vers Free-Work pour l'instant.

CGU lues le 2026-10-13 (version du 14/09/2026, lecture automatisée à confirmer) : aucune clause sur le scraping ; art. 7.3 interdit l'extraction d'une part substantielle de la base de données et « l'exploitation professionnelle ou commerciale » du site (point flou) ; `robots.txt` n'interdit pas les pages d'offres mais bloque Wget, HTTrack et plusieurs crawlers. Si on automatise plus tard : écrire d'abord à Free-Work (tolérance ? API ?), pages publiques seulement (vérifier hors connexion), uniquement les offres filtrées, une requête toutes les quelques secondes, cache, User-Agent honnête.

## Contraintes de conception (alertes mail)

- Deux alertes qui se chevauchent : dédupliquer par **identité de l'offre** (ex. identifiant d'offre dans l'URL, à confirmer sur un échantillon), pas par `Message-ID`.
- Amorçage (alerte sans limite) : renseigner `publishedAt` quand le mail l'indique ; ne jamais le remplacer par `detectedAt` (fausserait le bonus « urgence »).
- Premier lot volumineux : règles PHP éliminatoires avant tout appel LLM.

## Boîte IMAP

- **Choix** : compte Gmail dédié, mot de passe d'application (validation en 2 étapes requise), `imap.gmail.com:993`.
- Variables : `IMAP_HOST`, `IMAP_PORT` dans `.env` ; `IMAP_USER`, `IMAP_PASSWORD` dans `.env.local` (jamais committé).
- Librairie : `webklex/php-imap` 6.2 (PHP pur ; tire illuminate/* et Carbon). `directorytree/imapengine` avait un arbre comparable. Connexion testée (dossiers listés).
- Écartés : Outlook.com (OAuth seul), Proton (Bridge payant, local), `contact@` du site perso (mélange). Alternative payante : 2ᵉ boîte Hostinger (`imap.hostinger.com:993`, non vérifié).
- Gmail expose les étiquettes comme dossiers : lire `INBOX` + filtre expéditeur.

## Ingestion planifiée (2026-10-15)

- **Quand** : tous les jours à 9h (Europe/Paris), l'alerte Free-Work arrivant vers 8h40. Déclaré dans `src/Schedule.php` : `RecurringMessage::cron` + `RunCommandMessage('app:signals:ingest --since="-14 days"')` (le paquet `dragonmantank/cron-expression` est requis pour le cron).
- **Rattrapage** : la planification est `stateful` avec `processOnlyLastMissedRun`. Si le PC était éteint à 9h, le worker rattrape à son démarrage (en une exécution, parfois deux d'affilée si l'état est très ancien : sans conséquence, l'ingestion ne crée jamais de doublon). L'état est dans le cache de l'application (`var/cache`) : un `cache:clear` l'efface, sans gravité (la déduplication par `fingerprint` rend toute relecture sans danger).
- **Fenêtre** : 14 jours de mails relus à chaque passage. Après plus de deux semaines d'arrêt, les offres plus anciennes seraient manquées (lancer `app:signals:ingest --since=…` à la main).
- **Exécution** : conteneur `radarpartners-worker` (même image que l'API, `messenger:consume scheduler_default`). Tous les conteneurs ont `restart: unless-stopped` ; pour qu'ils démarrent avec la session Windows, activer « Start Docker Desktop when you sign in » dans Docker Desktop.
- **Suivre** : `docker logs radarpartners-worker` (la commande journalise une ligne « Ingestion des signaux » avec les compteurs) ; prochain passage : `php bin/console debug:schedule`.

## Prochaines étapes

1. ~~Lecteur IMAP~~ fait : `ImapMailbox` (lecture seule), `app:mailbox:list`, `app:mailbox:export`. Marquage des mails traités non implémenté : la déduplication par `fingerprint` suffit (`app:signals:ingest` relit les mails depuis `--since`).
2. Un parseur par source, testé avec des fixtures fictives (jamais de vrais mails dans le dépôt) : Free-Work fait (`src/Signal/FreeWork/`) ; aperçu avec `app:signals:preview` (rien n'est enregistré).
3. Fait : `app:signals:ingest` (filtre de stack, déduplication, enregistrement). Parseur LinkedIn fait (2026-10-15). Reste : parseur Indeed dès le premier vrai mail. Ingestion planifiée : faite, voir plus haut. Offres sans entreprise : résolution manuelle (décidé).
