.PHONY: up down bash logs restart

up:
	docker compose --env-file .docker/.env.docker.dev up -d --build

down:
	docker compose --env-file .docker/.env.docker.dev down

bash:
	docker exec -it radarpartners-api bash

logs:
	docker compose --env-file .docker/.env.docker.dev logs -f radarpartners-api

restart: down up
