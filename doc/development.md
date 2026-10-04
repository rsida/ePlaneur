# Daily development

Run `make` to list every command. Commands target the environment of `.env.local`
(`APP_ENV`, `dev` by default); add `ENV=prod` to force another one.

## Containers

| Command | Description |
|---|---|
| `make urls` | Site, profiler, Mailpit and MariaDB addresses (also shown at the top of `make`) |
| `make up` / `make down` | Start (and wait until healthy) / stop the stack. Volumes are kept |
| `make restart` | `down` + `up` |
| `make build` | Rebuild the images (after a change in `docker/` or `PHP_VERSION`) |
| `make ps` | Container status |
| `make logs` / `make logs s=nginx` | Follow every log / one service |
| `make install` | Full refresh: certificate, build, start, composer install, migrations, test database |

## PHP / Symfony

The PHP container runs as your host user, so generated files belong to you.

| Command | Example |
|---|---|
| `make bash` | Shell in the PHP container (`make bash-root` for root) |
| `make console c="..."` | `make console c="debug:router"` (alias: `make sf`) |
| `make composer c="..."` | `make composer c="require symfony/uid"` |
| `make cc` | Clear the cache |

The Symfony web debug toolbar and the profiler (`/_profiler`) are enabled in dev.

## Database

| Command | Description |
|---|---|
| `make db` | MariaDB shell with the application account (`make db-root` for root) |
| `make sql q="SELECT ..."` | One-shot query |
| `make migration` | Generate a migration from the entity changes |
| `make db-migrate` | Run the migrations |
| `make fixtures` | Load `src/DataFixtures` (purges the dev database): default groups, one account per access level (see [accounts](accounts.md#development-accounts)) and demo posts with their media, among them the Figma article at `/actualites/votre-premier-vol` |
| `make db-reset` | Drop, create, migrate and load the fixtures |
| `make db-dump` | Gzipped dump into `backups/` |
| `make db-restore file=backups/xxx.sql.gz` | Restore a dump |

From a GUI client (DBeaver, PhpStorm...): `localhost:3307`, user `eplaneur` / `eplaneur`, database
`eplaneur` (values from your env files).

Schema changes always go through migrations, never `doctrine:schema:update`.

## Assets

AssetMapper, no Node.js. Files in `assets/` are served directly in dev, nothing to build.

| Command | Description |
|---|---|
| `make importmap-require p=bootstrap` | Add a JavaScript package |
| `make assets` | Download the importmap packages (`assets/vendor/`, git-ignored) |
| `make assets-build` | Compile into `public/assets/` (prod images do it themselves). Delete `public/assets/` afterwards in dev, otherwise the compiled files take precedence |

## Front-end toolkit

Styles and Twig components follow the [design system](design-system.md). The living style guide is
available in dev at https://eplaneur.local/_toolkit.

## Mails

Every e-mail sent in dev is caught by Mailpit: http://localhost:8025. Messenger is synchronous in dev,
so e-mails are sent immediately without a worker. Test with:

```bash
make console c="mailer:test you@example.com"
```

## Xdebug

Xdebug is installed in the dev image but off by default (no slowdown).

| Command | Description |
|---|---|
| `make xdebug-on` | Restart PHP with `XDEBUG_MODE=debug,develop` |
| `make xdebug-off` | Back to `off` |

`make up` resets the mode to `XDEBUG_MODE` from the env files: set `XDEBUG_MODE=debug` in
`.env.local` to keep it on.

Debugging starts on trigger: use a browser extension (Xdebug helper) or add
`?XDEBUG_TRIGGER=1` to the URL. For a console command:

```bash
docker compose exec -e XDEBUG_TRIGGER=1 php php bin/console <command>
```

PhpStorm setup:

1. *Settings → PHP → Debug*: port `9003`, "Can accept external connections" checked.
2. *Settings → PHP → Servers*: name = `SERVER_NAME` (`eplaneur.local`), host `eplaneur.local`, port `443`,
   path mapping: project root → `/app`.
3. Start listening for PHP debug connections.

The container reaches the IDE through `host.docker.internal`, i.e. the Docker host. If the IDE runs on
Windows while Docker runs in WSL2 (NAT networking), set `XDEBUG_CLIENT_HOST` in `.env.local` to the
Windows host IP (`ip route show default | awk '{print $3}'` from WSL), or enable WSL mirrored
networking.
