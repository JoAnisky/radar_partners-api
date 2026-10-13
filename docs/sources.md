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
- **Paramètres validés par appels réels** : `q` (3 caractères min, sinon un filtre), `activite_principale` (liste séparée par des virgules), `departement`, `code_postal`, `tranche_effectif_salarie` (codes INSEE : 03 = 6-9, 11 = 10-19, 12 = 20-49…), `etat_administratif=A`, `per_page` (**25 max**), `page`. Un rayon géographique (`lat`/`long`/`radius`) n'est pas accepté sur `/search` (autre point d'accès, à explorer).
- **Pièges** : les résultats sont des unités légales ; le siège peut être ailleurs (Flowbird à Neuilly, établissement à Besançon). L'établissement local est dans `matching_etablissements`, à ne retenir que s'il est actif. Les filtres s'appliquent aux établissements, le NAF à l'unité légale.
- **Rapprochement par nom peu fiable** : nom commercial ≠ dénomination légale (4 entreprises sur 7 testées sans résultat avec nom + code postal ; une homonyme dans une autre ville renvoie un faux positif). Préférer lister le référentiel local (NAF + zone + effectif) puis rapprocher (pg_trgm sur nom, adresse).
- **Volumes (NAF 62.01Z, 62.02A/B, 62.03Z, 62.09Z, 63.11Z, 63.12Z, 73.11Z, 58.29A/C)** : 1 217 unités dans le Doubs, 5 447 en Bourgogne-Franche-Comté, majoritairement des micro-structures : filtre d'effectif indispensable.
- Le NAF ne distingue pas ESN et agence web → `type = UNKNOWN` jusqu'à analyse du site. La réponse contient aussi `activite_principale_naf25` (nouvelle nomenclature, ex. 62.01Z → 62.20H) ; `Company.nafCode` garde l'ancienne pour l'instant.
- Filtre sur la date de création pour `COMPANY_CREATED` : non vérifié.

## Signaux

| Source | Statut |
|---|---|
| **Free-Work** (alerte mail quotidienne le matin) | MVP, alerte créée, aucun échantillon reçu. Beaucoup de bruit (Angular, Java, React) ; pas de champ d'exclusion trouvé, les compétences du profil pilotent peut-être l'alerte (non vérifié). |
| **LinkedIn** (alertes natives par mail) | MVP, via règle de transfert depuis la boîte principale. Deux alertes freelance créées : dernières 24 h (flux) et sans limite de durée (amorçage, à supprimer ensuite). Échantillons à fournir. |
| **Welcome to the Jungle, HelloWork** | Piste après Free-Work. Emploi salarié : signal « entreprise qui recrute un dev ». Alertes mail non vérifiées. |
| **API France Travail** | Non vérifié, ne pas en dépendre. Jeu open data trimestriel : trop lent pour un signal. |
| **Apify** | Piste après l'IMAP : détection de stack (`Enricher`) sur les sites connus ; offres WTTJ éventuelles après vérification des CGU. Actors communautaires, responsabilité de l'usage à l'utilisateur. Plan gratuit 5 $/mois. |
| Pages carrières des entreprises connues | Idée, CGU et `robots.txt` à vérifier au cas par cas. |

## Règle de filtrage à valider

Un signal sans aucun mot-clé de la stack (Symfony, PHP, WordPress, Nuxt, Vue, Docker, K3s, DevOps… liste configurable) n'est pas retenu : ni signal, ni `Company` créée. Évite que le bruit des alertes pollue la base (scope national : une entreprise n'existe que si un signal pertinent la fait apparaître). **Proposé, pas encore décidé.**

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

## Prochaines étapes

1. Recevoir des alertes réelles ; les transférer **en pièce jointe** (`.eml`, en-têtes conservés) vers la boîte dédiée, puis les déposer dans `var/import/samples/` (ignoré par git).
2. Lecteur IMAP générique : filtre expéditeur, déduplication par `Message-ID`, marquage des mails traités.
3. Un parseur par source, testé avec des fixtures fictives (jamais de vrais mails dans le dépôt).
4. Décider la règle de filtrage par stack.
