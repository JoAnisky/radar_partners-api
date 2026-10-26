.PHONY: up down bash logs restart demo-up demo-down

up:
	docker compose --env-file .docker/.env.docker.dev up -d --build

down:
	docker compose --env-file .docker/.env.docker.dev down

bash:
	docker exec -it radarpartners-api bash

logs:
	docker compose --env-file .docker/.env.docker.dev logs -f radarpartners-api

restart: down up

demo-up:
	docker compose -p radarpartners-demo-api -f docker-compose.demo.yml up -d

demo-down:
	docker compose -p radarpartners-demo-api -f docker-compose.demo.yml down
