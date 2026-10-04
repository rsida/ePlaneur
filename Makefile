# ePlaneur — development and production commands. Run `make` to list them.
#
# The target environment comes from APP_ENV in .env.local (dev by default) and can be
# forced on the command line: `make ps ENV=prod`.

ENV ?= $(or $(shell sed -n 's/^APP_ENV=//p' .env.local 2>/dev/null | tail -1 | tr -d "\"'"),dev)

# Same load order as Symfony: .env < .env.local < .env.$(ENV) < .env.$(ENV).local
env_files = $(foreach f,.env .env.local .env.$(1) .env.$(1).local,$(wildcard $(f)))
compose_files_dev  = -f compose.yaml -f compose.override.yaml
compose_files_prod = -f compose.yaml -f compose.prod.yaml
dc = docker compose $(compose_files_$(1)) $(addprefix --env-file ,$(call env_files,$(1)))
# Value of a variable as resolved from the env files
env_value = $(shell for f in $(call env_files,$(ENV)); do sed -n 's/^$(1)=//p' $$f; done | tail -1 | tr -d "\"'")

DC := $(call dc,$(ENV))
PHP := $(DC) exec $(if $(filter prod,$(ENV)),--user www-data) php
PHP_TEST := $(DC) exec -e APP_ENV=test php
CONSOLE := $(PHP) php bin/console

SERVER_NAME := $(call env_value,SERVER_NAME)
HTTPS_PORT := $(call env_value,HTTPS_PORT)
SITE_URL := https://$(SERVER_NAME)$(if $(filter-out 443,$(HTTPS_PORT)),:$(HTTPS_PORT))

# Files created by the dev containers belong to the host user
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
# Image tag of the production release currently deployed (written by deploy-prod)
export APP_VERSION ?= $(shell cat .app-version 2>/dev/null || echo latest)

.DEFAULT_GOAL := help
.PHONY: help urls install deploy-dev certs build up down restart ps logs bash bash-root \
	console sf composer cc db db-root sql db-create db-migrate migration db-reset fixtures \
	db-dump db-restore media-dump assets assets-build importmap-require xdebug-on xdebug-off \
	test test-unit test-behat test-db cs cs-fix phpstan lint qa \
	release deploy-prod prod-check

help:
	@echo "Environment: \033[33m$(ENV)\033[0m"
	@$(MAKE) --no-print-directory urls
	@awk 'BEGIN {FS = ":.*## "} /^## / {printf "\n\033[1m%s\033[0m\n", substr($$0, 4)} /^[a-zA-Z_-]+:.*## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## Development environment

install: certs build up ## First install / full refresh of the dev environment
	$(PHP) composer install --no-interaction
	$(MAKE) db-migrate test-db
	@echo "\n✅ Ready:"
	@$(MAKE) --no-print-directory urls

deploy-dev: install ## Alias of install

urls: ## Show the URLs of the environment
	@echo "  Site      $(SITE_URL)"
ifeq ($(ENV),dev)
	@echo "  Profiler  $(SITE_URL)/_profiler"
	@echo "  Mailpit   http://localhost:$(call env_value,MAILPIT_PORT)"
	@echo "  MariaDB   localhost:$(call env_value,MARIADB_HOST_PORT)  (user $(call env_value,MARIADB_USER), db $(call env_value,MARIADB_DATABASE))"
endif

certs: ## Generate the local TLS certificate for SERVER_NAME with mkcert (if missing)
	@test -f docker/certs/cert.pem || mkcert -cert-file docker/certs/cert.pem -key-file docker/certs/key.pem $(SERVER_NAME)

build: ## Build the Docker images
	$(DC) build --pull

up: ## Start the containers
	$(DC) up -d --remove-orphans --wait

down: ## Stop and remove the containers (data volumes are kept)
	$(DC) down --remove-orphans

restart: down up ## Restart the containers

ps: ## List the containers
	$(DC) ps

logs: ## Follow logs (s=<service> for a single one)
	$(DC) logs -f --tail=100 $(s)

bash: ## Shell in the PHP container
	$(PHP) bash

bash-root: ## Root shell in the PHP container
	$(DC) exec --user root php bash

console: ## Symfony console: make console c="debug:router"
	$(CONSOLE) $(c)

sf: console ## Alias of console

composer: ## Composer: make composer c="require symfony/uid"
	$(PHP) composer $(c)

cc: ## Clear the Symfony cache
	$(CONSOLE) cache:clear

## Database

db: ## MariaDB shell (application user)
	$(DC) exec database sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"'

db-root: ## MariaDB shell (root)
	$(DC) exec database sh -c 'mariadb -uroot -p"$$MARIADB_ROOT_PASSWORD"'

sql: ## Run a SQL query: make sql q="SELECT * FROM user"
	$(DC) exec -T database sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE" -e "$$0"' "$(q)"

db-create: ## Create the database if missing
	$(CONSOLE) doctrine:database:create --if-not-exists

db-migrate: ## Run the Doctrine migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migration: ## Generate a migration from the entity changes
	$(CONSOLE) make:migration --no-interaction

db-reset: ## Drop and rebuild the dev database, then load the fixtures
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(MAKE) db-migrate fixtures

fixtures: ## Load the fixtures (dev only)
	$(CONSOLE) doctrine:fixtures:load --no-interaction

db-dump: ## Dump the database into backups/
	@mkdir -p backups
	$(DC) exec -T database sh -c 'mariadb-dump -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" --single-transaction --routines "$$MARIADB_DATABASE"' | gzip > backups/$(ENV)-$$(date +%Y%m%d-%H%M%S).sql.gz
	@ls -t backups/*.sql.gz | head -1

media-dump: ## Archive the uploaded media (var/uploads) into backups/
	@mkdir -p backups
	$(DC) exec -T php tar -czf - -C var uploads > backups/$(ENV)-media-$$(date +%Y%m%d-%H%M%S).tar.gz
	@ls -t backups/*-media-*.tar.gz | head -1

db-restore: ## Restore a dump: make db-restore file=backups/xxx.sql.gz
	@test -n "$(file)" || (echo "Usage: make db-restore file=backups/xxx.sql.gz" && exit 1)
	gunzip -c $(file) | $(DC) exec -T database sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"'

## Assets

assets: ## Download the importmap packages into assets/vendor
	$(CONSOLE) importmap:install

assets-build: ## Compile the assets into public/assets (done automatically in the prod image)
	$(CONSOLE) asset-map:compile

importmap-require: ## Add a JS package: make importmap-require p=bootstrap
	$(CONSOLE) importmap:require $(p)

## Debug

xdebug-on: ## Enable Xdebug (debug + develop modes) until the next `make up`
	XDEBUG_MODE=debug,develop $(DC) up -d --wait php

xdebug-off: ## Disable Xdebug
	XDEBUG_MODE=off $(DC) up -d --wait php

## Tests and quality (run in the dev PHP container, next to the dev environment)

test: test-unit test-behat ## Run every test suite

test-unit: ## PHPUnit (unit + functional): make test-unit f=MyTest to filter
	$(PHP_TEST) php bin/phpunit $(if $(f),--filter $(f))

test-behat: ## Behat scenarios: make test-behat f=features/home.feature
	$(PHP_TEST) php vendor/bin/behat $(f)

test-db: ## (Re)create the test database (<database>_test)
	$(PHP_TEST) php bin/console doctrine:database:drop --force --if-exists
	$(PHP_TEST) php bin/console doctrine:database:create
	$(PHP_TEST) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

cs: ## Check the coding standard (dry run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix the coding standard
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

lint: ## Lint container, Twig, YAML and the Doctrine mapping
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates/
	$(CONSOLE) lint:yaml config/ --parse-tags
	$(CONSOLE) doctrine:schema:validate --skip-sync

qa: lint cs phpstan test ## Everything the CI should run

## Production

release: ## Create and push a release tag: make release TAG=v1.0.0
	@test -n "$(TAG)" || (echo "Usage: make release TAG=v1.0.0" && exit 1)
	@test -z "$$(git status --porcelain)" || (echo "Working tree is not clean" && exit 1)
	git tag -a $(TAG) -m "Release $(TAG)"
	git push origin $(TAG)

prod-check: ## Check the production configuration (.env.local) without deploying
	@test "$(ENV)" = prod || (echo "Set APP_ENV=prod in .env.local before deploying" && exit 1)
	$(call dc,prod) config --quiet && echo "✅ Production configuration is valid"

deploy-prod: prod-check ## Deploy a tag on the server: make deploy-prod TAG=v1.0.0
	@test -n "$(TAG)" || (echo "Usage: make deploy-prod TAG=v1.0.0" && exit 1)
	@test -z "$$(git status --porcelain --untracked-files=no)" || (echo "Working tree is not clean" && exit 1)
	git fetch --tags --force
	git -c advice.detachedHead=false checkout tags/$(TAG)
	APP_VERSION=$(TAG) $(call dc,prod) build --pull
	@# Migrate before starting the worker: it needs the messenger tables
	APP_VERSION=$(TAG) $(call dc,prod) up -d --remove-orphans --wait database php
	APP_VERSION=$(TAG) $(call dc,prod) exec -T --user www-data php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	APP_VERSION=$(TAG) $(call dc,prod) exec -T --user www-data php php bin/console messenger:setup-transports
	APP_VERSION=$(TAG) $(call dc,prod) up -d --remove-orphans --wait
	echo $(TAG) > .app-version
	docker image prune -f
	@echo "\n✅ $(TAG) deployed on $(SITE_URL)"
