# Commandes utiles

> Aide-mémoire. Tout tourne dans Docker : jamais de PHP, Node ou Composer en local. Les commandes se tapent dans un terminal WSL ; dans Claude Code, préfixe-les par `!` pour qu'elles s'exécutent dans la session.
> Raccourci : `docker exec radarpartners-api php bin/console …` est écrit `console …` dans ce document.

## Démarrer, arrêter, surveiller

| Action | Commande |
|---|---|
| Tout démarrer | `make up` dans `api/` (api, worker, base, modèle) **et** dans `front/` |
| Tout arrêter | `make down` dans `api/` et dans `front/` (les données et le modèle sont conservés) |
| Voir ce qui tourne | `docker ps --format '{{.Names}}\t{{.Status}}' \| grep radarpartners` |
| Entrer dans un conteneur | `make bash` (api) ou `docker exec -it radarpartners-front sh` |
| Journaux de l'API / du front | `make logs` (api), `docker logs -f radarpartners-front` |
| Journaux du worker (ingestion quotidienne) | `docker logs -f radarpartners-worker` |
| Libérer le GPU tout de suite | `docker stop radarpartners-llm` (relancer : `docker start radarpartners-llm`) |

- URLs : front `http://radarpartners.dev.local`, API `http://radarpartners.api.dev.local`.
- Au démarrage de Windows : tout redémarre seul si « Start Docker Desktop when you sign in » est activé (tous les conteneurs sont en `restart: unless-stopped`).
- Docker Desktop affiche parfois « WSL needs updating » : le redémarrer suffit.

## Récupérer les offres (alertes mail)

L'ingestion tourne **toute seule chaque jour à 9h** (Europe/Paris) et se rattrape au démarrage si le PC était éteint. À la main :

| Action | Commande |
|---|---|
| Lancer la récupération maintenant | `console app:signals:ingest` |
| La simuler sans rien écrire | `console app:signals:ingest --dry-run` |
| Relire plus loin dans le passé | `console app:signals:ingest --since=2026-10-01` (défaut : 7 jours à la main, 14 jours en planifié) |
| Voir ce que chaque parseur lit, sans enregistrer | `console app:signals:preview --since=2026-10-15 --all` |
| Lister les mails de la boîte dédiée | `console app:mailbox:list --since=2026-10-15` |
| Exporter des mails pour écrire un parseur | `console app:mailbox:export` (dans `var/import/samples/`, ignoré par git) |
| Prochain passage planifié | `console debug:schedule` |

- `preview --all` affiche « oui » (stack reconnue), « à vérifier » (aucune techno nommée) et « non » (autre techno). Sans `--all`, les « non » sont masqués.
- Rien n'arrive ? Vérifier dans l'ordre : le mail est-il transféré dans la boîte dédiée ? est-il resté dans **INBOX** (seule lue) ? `app:mailbox:list` montre l'expéditeur d'origine.
- Une offre écartée par erreur dans l'interface se restaure depuis l'onglet « Archivées » de la page Signaux.

## Entreprises, score, référentiel

| Action | Commande |
|---|---|
| Recalculer tous les scores (après un changement de règle) | `console app:score:recompute` (`--dry-run`, `--top=20`) |
| Importer le référentiel de Besançon (API Recherche d'entreprises) | `console app:referential:import --dry-run` puis sans `--dry-run` |
| Explorer le référentiel sans rien écrire | `console app:referential:search --query=agence --department=25 --headcount=03,11` |
| Importer le tableur de prospection | `console app:import:prospection chemin/fichier.xlsx --dry-run` |

## Assistant Amelio et modèle local (Ollama)

| Action | Commande |
|---|---|
| Discuter directement avec le modèle (sans la base) | `docker exec -it radarpartners-llm ollama run granite4.1:8b` (quitter : `/bye`) |
| Voir les modèles installés | `docker exec radarpartners-llm ollama list` |
| Voir le modèle chargé, sa taille et le GPU utilisé | `docker exec radarpartners-llm ollama ps` |
| Décharger le modèle de la mémoire du GPU | `docker exec radarpartners-llm ollama stop granite4.1:8b` |
| Installer un autre modèle | `docker exec radarpartners-llm ollama pull <nom>` (choisir un modèle avec la catégorie « tools » sur ollama.com) |
| Supprimer un modèle | `docker exec radarpartners-llm ollama rm <nom>` |
| Voir l'occupation du GPU | `nvidia-smi` |

- **Changer de modèle** : après un `pull`, modifier `OLLAMA_MODEL` dans `api/.env.local` (valeur par défaut dans `.env`).
- Le modèle se décharge seul après 5 minutes sans question : le GPU est alors libre.
- **Tester le chat côté API** (avec la base) :

  ```bash
  docker exec radarpartners-api curl -s -X POST http://localhost/api/chat \
    -H 'Content-Type: application/json' \
    -d '{"messages":[{"role":"user","content":"Quelles entreprises dois-je relancer ?"}]}'
  ```

  La réponse contient `reply` et `toolCalls` (les outils appelés). Pour simuler l'ouverture d'une fiche, ajouter `"context":{"page":"Fiche entreprise","company":{"id":32,"name":"Atol CD"}}`.
- Le profil et les coordonnées de signature sont dans `api/config/assistant/profile.md` (ignoré par git) ; les règles de l'assistant dans `system.md`.
- Dans l'interface : bulle en bas à droite, raccourci ⌘J (Ctrl+J).

## Base de données

| Action | Commande |
|---|---|
| Ouvrir la console SQL | `docker exec -it radarpartners-db psql -U radar radar` |
| Sauvegarder (données réelles, **ne pas committer**) | `docker exec radarpartners-db pg_dump -U radar radar > ~/sauvegarde-radar.sql` |
| Appliquer les migrations | `console doctrine:migrations:migrate` |
| Appliquer les migrations à la base de test | `console doctrine:migrations:migrate --env=test` (**obligatoire** après chaque nouvelle migration, sinon des dizaines de tests échouent) |

La base de dev contient des données réelles : ne rien réinitialiser ni supprimer sans y penser (`doctrine:schema:drop`, `doctrine:database:drop` sont à proscrire).

## Vérifier avant de committer

| Où | Commande |
|---|---|
| API : tests | `docker exec radarpartners-api php bin/phpunit` |
| API : style | `docker exec radarpartners-api vendor/bin/php-cs-fixer fix` |
| API : analyse statique | `docker exec radarpartners-api vendor/bin/phpstan analyse src tests --level=max --memory-limit=1G` (5 erreurs connues : c'est la base de référence) |
| API : conteneur Symfony | `console lint:container` |
| Front : style | `docker exec radarpartners-front npm run lint` (`lint:fix` pour corriger) |
| Front : types | `docker exec radarpartners-front npm run typecheck` |
| Front : tests | `docker exec radarpartners-front npm test` |

Règles du dépôt : commits courts en anglais, un repo = ses propres commits, jamais de secret (`.env.local`, `profile.md`) committé.

## Dépannage rapide

| Symptôme | Piste |
|---|---|
| L'assistant affiche un visage triste / « le modèle local ne répond pas » | `docker ps` : le conteneur `radarpartners-llm` tourne-t-il ? `docker start radarpartners-llm` |
| Réponses de l'assistant très lentes | `docker exec radarpartners-llm ollama ps` : le modèle est-il chargé sur le GPU ? Premier appel après 5 min d'inactivité = rechargement (quelques secondes) |
| Pas d'ingestion à 9h | `docker logs radarpartners-worker` ; `docker ps` (worker démarré ?) ; `console debug:schedule` |
| Page blanche ou erreur 500 côté front | `docker logs radarpartners-front`, `make logs` dans `api/` |
| Beaucoup de tests API en erreur 500 | La base de test n'est pas migrée : `console doctrine:migrations:migrate --env=test` |
| Un `composer require` a modifié `docker-compose.yml` | Retirer les blocs ajoutés par la recette Flex ; supprimer `compose.override.yaml` |
