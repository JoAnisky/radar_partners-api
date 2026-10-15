# Assistant (chat avec modèle local)

> Ajouté le 2026-10-15. Le modèle tourne en local (Ollama) : aucune donnée n'est envoyée à un fournisseur externe.

## Architecture

- **Modèle** : service Docker `radarpartners-llm` (image `ollama/ollama`, GPU NVIDIA, volume `radarpartners_llm_data`), joignable depuis l'API sur `http://radarpartners-llm:11434` (non exposé à l'hôte). Variables dans `.env` : `OLLAMA_URL`, `OLLAMA_MODEL`, `OLLAMA_NUM_CTX`.
- **Changer de modèle** : `docker exec radarpartners-llm ollama pull <nom>`, puis modifier `OLLAMA_MODEL` (dans `.env.local` pour ne pas committer l'essai). Le modèle doit gérer les outils (catégorie « tools » sur ollama.com).
- **Code** (`src/Llm/`) : `LlmClientInterface` (une implémentation, `OllamaClient`, en HTTP non streamé sur `/api/chat`), `ChatService` (boucle : le modèle appelle des outils jusqu'à répondre, 5 tours au plus ; une erreur d'outil est renvoyée au modèle), `ChatController` (`POST /api/chat`).
- **Outils** (`src/Llm/Tool/`, tag `app.chat_tool`, **lecture seule**) : `search_companies` (partenaires exclus, 20 résultats au plus), `get_company` (fiche, contacts, 10 dernières interactions, offres), `list_signals` (à traiter ou archivées, indicateur `toReview`). Ajouter un outil = une classe implémentant `ChatToolInterface`.
- **Invite** : `config/assistant/system.md` (règles de rédaction : ne rien inventer, champs `[entre crochets]` si une info manque) + `config/assistant/profile.md` (**ignoré par git**, voir `profile.example.md`) injecté à la place de `{profile}`.

## Modèle essayé

`granite4.1:8b` (IBM, 5,3 Go, tient dans 8 Go de VRAM à 100 % GPU, contexte 8192). Appelle correctement les outils (essais du 2026-10-15) ; ses rédactions sont correctes mais il extrapole parfois (formules d'accroche non fondées) : relire avant d'envoyer.

## Pas encore fait

Réponse en flux (l'API attend la réponse complète), historique persistant (en mémoire dans le navigateur), outils d'écriture (noter une interaction, changer un statut : prévoir une confirmation), bouton « Rédiger une approche » sur la fiche entreprise, mode démo (appels simulés), second fournisseur (Claude) derrière `LlmClientInterface`.
