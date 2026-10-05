# Troubleshooting

## `make up` fails with "port is already allocated"

Another container uses port 80, 443, 3307 or 8025 (often the shared Traefik of
`../local-network-multisite` or another project). Find it:

```bash
docker ps --format '{{.Names}}\t{{.Ports}}' | grep -E ':(80|443|3307|8025)->'
```

Stop it, or change `HTTP_PORT`, `HTTPS_PORT`, `MARIADB_HOST_PORT` or `MAILPIT_PORT` in `.env.local`.

## The browser says the certificate is not trusted

The mkcert CA is not trusted by this browser: see [installation, step 3](installation.md#3-trust-the-local-certificate-authority-once-per-machine).
After changing `SERVER_NAME`, delete `docker/certs/*.pem`, then `make certs` and `make restart`.

## `eplaneur.local` does not resolve

The hosts entry is missing on the machine running the **browser** (Windows when using WSL2).

Inside WSL (tests with Playwright, `curl`), WSL regenerates `/etc/hosts` on restart: without a
`127.0.0.1 eplaneur.local` line every request waits about 11 seconds for name resolution. Add the
line again (`sudo` needed), or use `https://127.0.0.1` for scripted checks.

## Access denied for the database user

Credentials changed after the volume was created. Either restore the previous values, or recreate the
volume (data loss):

```bash
make down
docker volume rm eplaneur_database_data
make install
```

The same applies when `make test-db` cannot create `eplaneur_test`: the grant script only runs when
the volume is created.

## Files owned by root in the project

A command was run with `make bash-root` or `docker compose exec --user root`. Fix with:

```bash
sudo chown -R "$(id -u):$(id -g)" .
```

## Xdebug does not stop on breakpoints

- `make xdebug-on` was run, and `make up` was not run afterwards (it resets the mode).
- The trigger is present (browser extension or `XDEBUG_TRIGGER=1`).
- The PhpStorm server name equals `SERVER_NAME` and maps the project root to `/app`.
- IDE on Windows + Docker in WSL2: set `XDEBUG_CLIENT_HOST` (see [development](development.md#xdebug)).

Check from the container: `docker compose exec php php -r 'var_dump(xdebug_info("mode"));'`.

## Production: the worker keeps restarting

`make logs s=worker`. A missing `messenger_messages` table means the migrations did not run:
`make console c="messenger:setup-transports"`.

## Production: no certificate / Traefik default certificate

- The DNS record of `SERVER_NAME` must point to the server and port 80 must be reachable from the
  Internet (HTTP challenge).
- `make logs s=traefik` shows the ACME errors. Let's Encrypt rate-limits failures: fix the cause before
  retrying several times.
