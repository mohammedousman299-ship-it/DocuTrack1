# Raccourcis de développement DocuTrack.
.DEFAULT_GOAL := help
.PHONY: help setup up down fresh test lint analyse check

help: ## Affiche cette aide
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Démarre PostgreSQL et MinIO
	docker compose up -d --wait

down: ## Arrête les services (les données sont conservées)
	docker compose down

setup: up ## Installe les dépendances et prépare la base
	composer install
	npm install
	@test -f .env || cp .env.example .env
	php artisan key:generate
	php artisan migrate --seed

fresh: ## Réinitialise la base et rejoue les seeders
	php artisan migrate:fresh --seed

test: ## Lance la suite de tests
	./vendor/bin/pest

lint: ## Corrige le style de code
	./vendor/bin/pint

analyse: ## Analyse statique
	./vendor/bin/phpstan analyse --memory-limit=1G

check: ## Tout ce que la CI vérifie
	./vendor/bin/pint --test
	./vendor/bin/phpstan analyse --memory-limit=1G
	./vendor/bin/pest
