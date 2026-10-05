# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

## Project purpose

Rebuild with Symfony the website of the **Club ePlaneur** (https://club.eplaneur.fr, WordPress today):
a French association, run entirely online, that trains people to fly gliders on the Condor simulator
and organises weekly multiplayer "network flights". The site presents the activity, publishes the
8-step training path, organises flights, manages accounts and membership, and hosts association
content (official documents, governance, news, restricted committee pages).

Before any functional work, read `doc/project/` — it is the reference for the domain:
`club.md` (association, membership, access levels), `training.md`, `flights.md`, `ecosystem.md`
(what eplaneur.fr, Condor Club, Yapla... already do — do not rebuild them without a decision),
`current-site.md` (site map and features to carry over) and `glossary.md`.

The plan of work lives in `doc/roadmap.md`: read it to know what to do next, and update its status,
decisions and open questions as work progresses.

Key domain constraints:
- User-facing content is in **French**; times are metropolitan France time (many members are in
  La Réunion).
- Access levels: visitor, registered user (free account), member (paid via Yapla, validated by the
  club), committee, admin.
- "Simulator, not game": features support structured progression (steps, qualifications), not just fun.

## Writing

Use the project skill `writing` (`.claude/skills/writing/SKILL.md`) for every writing task: replies to
the user in French, every written artifact (docs, comments, commits, skills) in English unless the user
asks otherwise, site content in French.

## Project-specific overrides of AGENTS.md

- Never run `php`, `composer` or `symfony` on the host (host PHP is 8.3, the project needs 8.4+).
  Everything goes through the Makefile, which runs commands in the `php` container:
  `make console c="..."`, `make composer c="..."`, `make bash`. Do not use `symfony serve`.
- The Makefile feeds Docker Compose the same env files as Symfony (`.env < .env.local < .env.$ENV <
  .env.$ENV.local`). Calling `docker compose` directly only reads `.env`: prefer `make`.
- Full documentation lives in `doc/` (project/functional reference, installation, configuration,
  development, tests, production). Keep it in sync when changing Docker, env variables, Makefile
  targets or the functional scope.

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
- **Front-end**: design tokens and components follow `doc/design-system.md` (Figma source). Never
  hard-code a color, font or size in a component: add or reuse a token in `assets/styles/tokens.css`.
  Reusable UI = anonymous Twig component in `templates/components/` (`<twig:Ui:Button>`...) + its CSS
  in `assets/styles/components/` + an entry in the `/_toolkit` style guide. Mobile first.
  Icons: SVG in `assets/icons/` using `currentColor`, rendered with `<twig:ux:icon>`.
- **Security**: rights are permissions (`App\Security\Permission`) held by groups in database, checked
  with voters (`is_granted('USER_MANAGE')`, `is_granted('CONTENT_VIEW', content)`); every account only
  has `ROLE_USER`. See `doc/accounts.md` before adding a protected feature.
- **Content**: post and page bodies are lists of typed blocks stored as JSON (`App\Content\Block`),
  rendered by `Block:*` Twig components; media are served by `MediaController` with access control;
  pages form a tree, menus (`MenuItem`) are a separate tree filtered by rights (`menu('main')`).
  See `doc/content.md` before adding a block type or touching navigation.
- **Prod deploys** are tag-based: `make release TAG=vX` locally, `make deploy-prod TAG=vX` on the server
  (APP_ENV=prod in the server's `.env.local`).

<easyadmin-guidelines>
This project uses EasyAdmin 5.6.1.

Before creating or modifying admin dashboards, CRUD controllers, fields, actions,
filters or their tests, read and follow the `easyadmin` skill at `.claude/skills/easyadmin/SKILL.md`.

Prefer the makers (`make:admin:dashboard`, `make:admin:crud`) to generate the
initial classes instead of writing them from scratch.

Team conventions for EasyAdmin, if any, are in the `## EasyAdmin conventions`
section of this file, outside this block.
</easyadmin-guidelines>

## EasyAdmin conventions

- The back-office is documented in `doc/admin.md`; keep its screen table in sync.
- One `final` CRUD controller per entity in `src/Controller/Admin/`, restricted as a whole with
  `#[IsGranted(Permission::X->value)]` (never only with menu or action permissions), listed in
  `DashboardController::configureMenuItems()` with the same permission.
- Labels, help texts and flash messages in French; restricted contents use `VisibilityFields::create()`.
- Every screen has a functional test in `tests/Functional/Admin/` (`AbstractCrudTestCase`), and
  `AdminAccessTest` checks who may open it.
