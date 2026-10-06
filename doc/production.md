# Production

The production stack ships its **own Traefik** (`compose.prod.yaml`): it listens on ports 80/443,
obtains Let's Encrypt certificates for `SERVER_NAME` and redirects HTTP to HTTPS. Images are built on
the server from a git **tag** and named `eplaneur-php:<tag>` / `eplaneur-nginx:<tag>`.

```
Internet ──▶ traefik :80/:443 ──▶ nginx :80 ──▶ php-fpm :9000 ──▶ database
                                                worker (messenger:consume async)
```

## 1. Server prerequisites

- Linux with Docker Engine 24+, the Compose plugin 2.24+, GNU Make and Git.
- A DNS `A` (and `AAAA` if IPv6) record of `SERVER_NAME` pointing to the server.
- Ports 80 and 443 open and **free** (no other web server or Traefik).
- Read access to the GitHub repository (deploy key or SSH key).

## 2. Configure

```bash
git clone git@github.com:rsida/ePlaneur.git
cd ePlaneur
```

Create `.env.local` (never committed):

```dotenv
APP_ENV=prod
APP_SECRET=<openssl rand -hex 16>
SERVER_NAME=eplaneur.example.com
ACME_EMAIL=admin@example.com

MAILER_DSN=smtp://user:password@smtp.example.com:587

MARIADB_PASSWORD=<strong password>
MARIADB_ROOT_PASSWORD=<another strong password>
```

`APP_ENV=prod` makes every `make` command target the production stack. Avoid `$` in values.
Database passwords are applied when the volume is created, choose them before the first deployment.

Check the configuration:

```bash
make prod-check
```

## 3. Release and deploy

From your machine, once `main` is ready:

```bash
make release TAG=v1.0.0    # creates the annotated tag and pushes it
```

On the server:

```bash
make deploy-prod TAG=v1.0.0
```

`deploy-prod`:

1. checks that `APP_ENV=prod` and that tracked files are not modified,
2. fetches the tags and checks out `tags/v1.0.0` (detached HEAD),
3. builds the PHP and nginx images (`composer install --no-dev`, optimized autoloader, cache warmup,
   `asset-map:compile`, OPcache preloading),
4. starts `database` and `php`, runs the Doctrine migrations and `messenger:setup-transports`,
5. starts the rest (Traefik, nginx, worker) and waits until every container is running,
6. stores the tag in `.app-version` (used by the next `make` commands) and prunes dangling images.

The first deployment takes a few seconds more: Traefik requests the certificate on the first HTTPS
request.

## Rollback

Deploy the previous tag:

```bash
make deploy-prod TAG=v0.9.0
```

Migrations are **not** rolled back automatically. If the faulty release migrated the schema, revert it
first, before deploying the old tag:

```bash
make console c="doctrine:migrations:migrate prev"
```

Taking a dump before each deployment is recommended (`make db-dump`).

## Operations

Every command below is run on the server, in the project directory.

| Command | Description |
|---|---|
| `make ps` / `make logs s=php` | Status / logs |
| `make console c="..."` | Console, run as `www-data` |
| `make bash` | Shell in the PHP container (`www-data`) |
| `make db` / `make sql q="..."` | MariaDB shell / query |
| `make db-dump` | Gzipped dump into `backups/prod-<date>.sql.gz` |
| `make media-dump` | Archive of the uploaded media (Docker volume `uploads`, mounted on `var/uploads`) into `backups/prod-media-<date>.tar.gz`. It includes the resized pictures of `var/uploads/cache/`, which are rebuilt on demand if missing |
| `make db-restore file=...` | Restore a dump |
| `make console c="messenger:failed:show"` | Messages that failed after 3 retries |
| `make console c="messenger:failed:retry"` | Retry them |

Backups are not scheduled automatically, for instance with cron:

```cron
0 3 * * * cd /path/to/ePlaneur && make db-dump media-dump >/dev/null 2>&1 && find backups \( -name '*.sql.gz' -o -name '*.tar.gz' \) -mtime +14 -delete
```

The `worker` restarts every hour (`--time-limit=3600`) to release memory; Docker restarts it
(`restart: unless-stopped`). It is also recreated on each deployment, so it always runs the new code.

## What differs from dev

- No bind mount: code changes require a new tag and a deployment.
- `php.ini-production`, OPcache without timestamp validation, no Xdebug.
- MariaDB and Mailpit are not exposed; there is no Mailpit.
- Messenger is asynchronous (Doctrine transport), handled by the `worker` service.
- `TRUSTED_PROXIES=PRIVATE_SUBNETS`: Symfony trusts the `X-Forwarded-*` headers set by Traefik
  (client IP, HTTPS scheme).
