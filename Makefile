.DEFAULT_GOAL := help
.PHONY: help install update fresh dev build test lint format clean

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

install: ## Einmaliges Setup: .env, dependencies, key, SQLite-DB, migrate
	@[ -f .env ] || cp .env.example .env
	composer install
	bun install
	bun run build
	@grep -q "^APP_KEY=base64:" .env || php artisan key:generate
	@touch database/database.sqlite
	php artisan migrate

update: ## Nach git pull: dependencies + migrations nachziehen
	composer install
	bun install
	php artisan migrate

fresh: ## Datenbank neu aufbauen (DESTRUKTIV)
	php artisan migrate:fresh --seed

dev: ## Dev-Server starten (Laravel + Vite)
	bun run dev

build: ## Production-Assets bauen
	bun run build

test: ## Test-Suite ausführen
	php artisan test

lint: ## Code-Stil prüfen
	./vendor/bin/pint --test
	bun run lint

format: ## Code-Stil fixen
	./vendor/bin/pint
	bun run lint --fix

clean: ## Caches leeren
	php artisan optimize:clear
