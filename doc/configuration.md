# Configuration

## Env files

The same files configure **Symfony** and **Docker Compose** (the Makefile passes them to Compose with
`--env-file`), so a value set once is seen by both.

Load order, later files win:

```
.env  <  .env.local  <  .env.$APP_ENV  <  .env.$APP_ENV.local
```

Real environment variables always win (in prod, the containers receive their configuration as
environment variables).

| File | Committed | Purpose |
|---|---|---|
| `.env` | yes | Defaults for every environment, dev-ready credentials |
| `.env.dev` | yes | Dev defaults: Mailpit transport, synchronous Messenger, dev `APP_SECRET` |
| `.env.prod` | yes | Prod defaults (trusted proxies) |
| `.env.test` | yes | Test defaults (null mailer, in-memory Messenger). `.env.local` is **not** loaded in test |
| `.env.local` | **no** | Your machine / your server: URL, credentials, ports, `APP_ENV` |
| `.env.dev.local`, `.env.prod.local` | **no** | Only needed to override a value set in `.env.dev` / `.env.prod` |

> `.env.dev` is loaded **after** `.env.local`: to change `MAILER_DSN` or `MESSENGER_TRANSPORT_DSN`
> in dev, use `.env.dev.local`.

The Makefile picks the environment from `APP_ENV` in `.env.local` (`dev` when absent). It can be
forced per command: `make ps ENV=prod`.

Values must not contain `$` (Compose would interpolate it). Quotes are allowed.

## Variables

### Application

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `dev` | `dev` or `prod`. Also selects the Docker stack in the Makefile |
| `APP_SECRET` | dev: fixed value, prod: **required** | Generate one with `openssl rand -hex 16` |
| `SERVER_NAME` | `eplaneur.local` | Host name of the site. Dev: mkcert certificate + nginx. Prod: Traefik rule and Let's Encrypt certificate |
| `DEFAULT_URI` | `https://${SERVER_NAME}` | Base URL used to generate absolute URLs from the CLI |
| `TRUSTED_PROXIES` | `127.0.0.1`, prod: `PRIVATE_SUBNETS` | Proxies allowed to send `X-Forwarded-*` headers |

### Docker

| Variable | Default | Description |
|---|---|---|
| `COMPOSE_PROJECT_NAME` | `eplaneur` | Prefix of containers, volumes and images |
| `HTTP_PORT` / `HTTPS_PORT` | `80` / `443` | Dev host ports of nginx |
| `MARIADB_HOST_PORT` | `3307` | Dev host port of MariaDB |
| `MAILPIT_PORT` | `8025` | Dev host port of the Mailpit UI |
| `PHP_VERSION` | `8.5` | PHP image version (rebuild after a change) |
| `NGINX_VERSION` | `1.28` | nginx image version |
| `XDEBUG_MODE` | `off` | Xdebug mode at startup (see [development](development.md#xdebug)) |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` | Host where the IDE listens |
| `ACME_EMAIL` | prod: **required** | Let's Encrypt account e-mail |
| `TRAEFIK_VERSION` | `v3.6` | Traefik image version (prod) |

### Database

Shared by the MariaDB container and Doctrine (`config/packages/doctrine.yaml`), there is no
`DATABASE_URL`.

| Variable | Default | Description |
|---|---|---|
| `MARIADB_VERSION` | `11.8` | MariaDB image and Doctrine server version |
| `MARIADB_DATABASE` | `eplaneur` | Database name. Tests use `<name>_test` |
| `MARIADB_USER` / `MARIADB_PASSWORD` | `eplaneur` / `eplaneur` | Application account |
| `MARIADB_ROOT_PASSWORD` | `root` | Root account |
| `DATABASE_HOST` / `DATABASE_PORT` | `database` / `3306` | As seen from the PHP container, set by Compose |

Credentials are applied when the volume is created only (see [installation](installation.md#4-optional-local-configuration)).

### Mail and messages

| Variable | Default | Description |
|---|---|---|
| `MAILER_DSN` | `null://null`, dev: `smtp://mailer:1025` | Prod: real provider, e.g. `smtp://user:pass@smtp.example.com:587` |
| `MAILER_FROM` | `"Club ePlaneur <no-reply@club.eplaneur.fr>"` | Sender of every e-mail; use an address the provider is allowed to send from |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0`, dev: `sync://`, test: `in-memory://` | Async transport, consumed by the `worker` service in prod |

## Examples

Dev, another domain and no conflict with a stack already using 80/443:

```dotenv
# .env.local
SERVER_NAME=eplaneur.test
HTTP_PORT=8080
HTTPS_PORT=8443
```

Then `make certs` (delete `docker/certs/*.pem` first) and `make up`. URL: `https://eplaneur.test:8443`.

Production server: see [production](production.md#2-configure).
