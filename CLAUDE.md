# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Project-specific overrides of AGENTS.md

- Never run `php`, `composer` or `symfony` on the host (host PHP is 8.3, the project needs 8.4+).
  Everything goes through the Makefile, which runs commands in the `php` container:
  `make console c="..."`, `make composer c="..."`, `make bash`. Do not use `symfony serve`.
- The Makefile feeds Docker Compose the same env files as Symfony (`.env < .env.local < .env.$ENV <
  .env.$ENV.local`). Calling `docker compose` directly only reads `.env`: prefer `make`.
- Full documentation lives in `doc/` (installation, configuration, development, tests, production):
  keep it in sync when changing Docker, env variables or Makefile targets.

## Commands

```bash
make install                      # build + start + composer install + migrations + test DB
make up / make down
make console c="debug:router"
make migration && make db-migrate # schema changes, always via migrations
make test                         # PHPUnit + Behat (in the running dev container, APP_ENV=test)
make test-unit f=HomeControllerTest
make test-behat f=features/home.feature
make test-db                      # recreate eplaneur_test after adding migrations
make qa                           # lint + php-cs-fixer + phpstan + tests
```

## Architecture

- **Docker**: `compose.yaml` (shared) + `compose.override.yaml` (dev, auto-loaded) or `compose.prod.yaml`
  (prod, own Traefik + Let's Encrypt + messenger `worker`). One multi-stage Dockerfile
  (`docker/php/Dockerfile`: `php_dev`, `php_prod`, `nginx_prod`). Dev bind-mounts the project on `/app`
  and runs PHP as the host user. Standalone: does not use the shared Traefik of `../local-network-multisite`.
- **Database**: MariaDB. Doctrine reads `DATABASE_HOST/PORT` and `MARIADB_*` (no `DATABASE_URL`), the same
  variables that configure the MariaDB container. Tests use `<db>_test` (Doctrine `dbname_suffix`),
  isolated per test by DAMA DoctrineTestBundle.
- **Messenger**: `sync://` in dev (mails go straight to Mailpit), `in-memory://` in test, Doctrine
  transport + `worker` service in prod.
- **Behat 4**: PHP config only (`behat.dist.php`, YAML is ignored), steps declared with
  `#[Given/When/Then]` attributes from `Behat\Step`. Contexts in `tests/Behat/` are services and use
  `KernelBrowser` (`test.client`); the Mink extension is not Symfony 8 compatible.
- **Prod deploys** are tag-based: `make release TAG=vX` locally, `make deploy-prod TAG=vX` on the server
  (APP_ENV=prod in the server's `.env.local`).
