# Assistant (chat avec modèle local)

> Ajouté le 2026-10-15. Le modèle tourne en local (Ollama) : aucune donnée n'est envoyée à un fournisseur externe.

## Architecture

- **Modèle** : service Docker `radarpartners-llm` (image `ollama/ollama`, GPU NVIDIA, volume `radarpartners_llm_data`), joignable depuis l'API sur `http://radarpartners-llm:11434` (non exposé à l'hôte). Variables dans `.env` : `OLLAMA_URL`, `OLLAMA_MODEL`, `OLLAMA_NUM_CTX`.
- **Changer de modèle** : `docker exec radarpartners-llm ollama pull <nom>`, puis modifier `OLLAMA_MODEL` (dans `.env.local` pour ne pas committer l'essai). Le modèle doit gérer les outils (catégorie « tools » sur ollama.com).
- **Code** (`src/Llm/`) : `LlmClientInterface` (une implémentation, `OllamaClient`, en HTTP non streamé sur `/api/chat`), `ChatService` (boucle : le modèle appelle des outils jusqu'à répondre, 5 tours au plus ; une erreur d'outil est renvoyée au modèle), `ChatController` (`POST /api/chat`).
- **Outils** (`src/Llm/Tool/`, tag `app.chat_tool`, **lecture seule**) : `search_companies` (partenaires exclus, 20 résultats au plus), `get_company` (fiche, contacts, 10 dernières interactions, offres), `list_signals` (à traiter ou archivées, indicateur `toReview`). Ajouter un outil = une classe implémentant `ChatToolInterface`.
- **Invite** : `config/assistant/system.md` (règles de rédaction : ne rien inventer, champs `[entre crochets]` si une info manque) + `config/assistant/profile.md` (**ignoré par git**, voir `profile.example.md`) injecté à la place de `{profile}`.

## Interface

L'assistant s'appelle **Amelio** (nom défini dans `ASSISTANT_NAME`, `front/app/utils/labels.ts`, et dans `config/assistant/system.md` ; avatar robot en SVG : `AmelioAvatar.vue`, trois visages selon l’état : normal avec clignements, en réflexion, en erreur). Bulle fixée en bas à droite (`ChatBubble`, montée dans `layouts/default.vue`), raccourci ⌘J. L'état du chat vit dans `useChat` (partagé entre pages, sauvegardé dans le navigateur). La fiche entreprise renseigne l'entreprise courante : le panneau propose alors des demandes toutes faites qui citent son nom et son id. **Contexte** : chaque requête envoie `context` (titre de la page + entreprise ouverte) ; `ChatContext` l'injecte dans l'invite (`{context}`) et demande d'appeler `get_company` avec cet id quand l'utilisateur dit « cette entreprise ». **Historique** : gardé dans `localStorage` (clé `radar-chat`, 60 messages, les 20 derniers envoyés). **Rendu** : les réponses sont du Markdown rendu par `markdown-it` (`html: false`, liens `noopener`, `ChatMarkdown.vue`) ; « Copier » renvoie du texte sans mise en forme (`markdownToPlain`).

## Consignes de l'invite

`system.md` fixe la longueur (réponses de 5 à 8 lignes, mail 5 à 8 lignes, LinkedIn 3 à 4), le texte brut pour ce qui sera envoyé, le vouvoiement par défaut, l'interdiction d'inventer des coordonnées, et les alertes qui s'adressent à l'utilisateur, jamais au destinataire. Constat du 2026-10-15 : le modèle local les suit **à peu près** (résumés passés d'environ 25 à 12-15 lignes) mais ajoute encore des rubriques ; ne pas compter sur une obéissance stricte. `profile.md` (privé) contient le profil, les cibles, les coordonnées de signature et le ton.

## Dates

Les petits modèles comparent mal les dates : `get_company` fournit les écarts déjà calculés en PHP (`daysAgo`, `daysSinceLastInteraction`, `nextActionOverdueByDays`, `recontactOverdueByDays`), les dates écrites dans les notes (`NoteDateExtractor` : `17.09.2026`, `17/09/2026`, `2026-09-17`, `17 septembre 2026` ; sans année, elles sont ignorées) avec leur position par rapport à aujourd'hui, et des `alerts` en français prêtes à relayer (rendez-vous noté dans la dernière interaction, passé, sans suite). Constat du 2026-10-15 : une simple consigne dans l'invite ou un champ `when` n'ont pas suffi (le modèle répétait « il reste à attendre le rendez-vous ») ; l'alerte explicite a fonctionné. Règle : ce que le modèle doit absolument respecter se calcule en PHP et se formule en phrase.

## Modèles essayés

- `granite4.1:8b` (IBM, 5,3 Go, tient dans 8 Go de VRAM, contexte 8192) : appelle les outils, mais sur « quelles ESN as-tu ? » il ajoute de lui-même `status: TO_CONTACT`, ne trouve rien et conclut que la base est vide (0 réussite sur 5 passes). Résumés longs, avec rubriques ; il extrapole parfois (formules d'accroche non fondées) : relire avant d'envoyer.
- `gemma4:12b` (8 Go, déborde un peu de la VRAM) : un seul appel sans filtre, listes propres, résumés de 2 paragraphes. Essais du 2026-10-29 : recherche réussie 5 fois sur 6 (échec : recherche par mots-clés exacts, « aucune ESN » à tort). Vitesse comparable à granite (20 à 130 s pour une question, très variable).

**Comparer** : `php bin/console app:llm:bench <modèle> <modèle>…` pose quatre questions (recherche, signaux, résumé de la fiche la mieux classée, société inexistante) avec la vraie boucle de l'assistant, et affiche durée, outils appelés et réponse. À relancer plusieurs fois : un seul passage ne suffit pas. Aucun des deux n'invente de réponse à la question piège (toutes les passes).

**Leçons sur les outils** : décrire explicitement ce que `query` accepte (nom, ville, SIREN, pas un type d'entreprise) et dire que `status` n'est à renseigner que sur demande. Dans `get_company`, `scoreExplanation` est renvoyé sous la forme « +45 points : … » : sans le mot « points », gemma lisait « 45 offres ». Le front reçoit toujours le format d'origine.

## Pas encore fait

Réponse en flux (l'API attend la réponse complète), outils d'écriture (noter une interaction, changer un statut : prévoir une confirmation), bouton « Rédiger une approche » sur la fiche entreprise, second fournisseur (Claude) derrière `LlmClientInterface`.

## Mode démo

Avec `APP_DEMO_MODE=true`, `LlmClientFactory` fournit `DemoLlmClient` (`src/Llm/Demo/`) à la place d'Ollama : pas de GPU, pas de modèle. Il reconnaît cinq questions (priorités, relances en retard, dernières offres, résumé d'une fiche, rédaction d'un mail / message LinkedIn / script d'appel) par mots-clés, appelle les **vrais outils de lecture** (`search_companies`, `list_signals`, `get_company`) puis met leur résultat en forme (`DemoReplies`). L'entreprise visée est trouvée par son nom, son `id` ou la fiche ouverte. Toute autre question reçoit la liste des questions possibles. Le front propose ces questions en suggestions (`ChatBubble`, `useDemo`).
