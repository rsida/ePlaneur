# Installation (development)

## Prerequisites

| Tool | Version | Check |
|---|---|---|
| Docker Engine | 24+ | `docker version` |
| Docker Compose plugin | 2.24+ (multiple `--env-file`, `up --wait`) | `docker compose version` |
| GNU Make | any | `make --version` |
| mkcert | any | `mkcert -version` |
| Git | any | `git --version` |

PHP, Composer and the Symfony CLI are **not** needed on the host: everything runs in the containers
(the project requires PHP 8.4+, the image ships PHP 8.5).

Host ports used by default: **80**, **443**, **3307** (MariaDB) and **8025** (Mailpit).
Another stack listening on 80/443 (e.g. the shared Traefik of `../local-network-multisite`) must be
stopped first, or the ports changed in `.env.local` (see [configuration](configuration.md)).

## 1. Clone

```bash
git clone git@github.com:rsida/ePlaneur.git
cd ePlaneur
```

## 2. Resolve the local domain

The site is served on `SERVER_NAME` (`eplaneur.local` by default). Add it to the hosts file of the
machine running the browser:

- Linux / macOS: `/etc/hosts`
- Windows (browser on Windows, Docker in WSL2): `C:\Windows\System32\drivers\etc\hosts`, edited as administrator

```
127.0.0.1 eplaneur.local
```

## 3. Trust the local certificate authority (once per machine)

```bash
mkcert -install
```

With WSL2 and a browser on Windows, the Windows certificate store must trust the same CA as well:

```bash
# In WSL: copy the CA where Windows can read it
cp "$(mkcert -CAROOT)/rootCA.pem" /mnt/c/Users/<windows-user>/Downloads/mkcert-rootCA.pem
```

```powershell
# In PowerShell (Windows)
certutil -addstore -user Root $env:USERPROFILE\Downloads\mkcert-rootCA.pem
```

Firefox uses its own store: import the same file in *Settings → Certificates → Authorities*.

## 4. Optional: local configuration

Defaults work out of the box. To change the domain, ports or credentials, create `.env.local` before
the first start (see [configuration](configuration.md)). Database credentials are applied when the
database volume is **created**: changing them afterwards requires `make down` +
`docker volume rm eplaneur_database_data` (data loss) or a manual `ALTER USER`.

## 5. Install

```bash
make install
```

This command:

1. generates `docker/certs/cert.pem` for `SERVER_NAME` with mkcert (only if missing),
2. builds the images,
3. starts the containers and waits until they are healthy,
4. runs `composer install` (which also downloads the importmap assets),
5. runs the migrations on the dev database,
6. (re)creates the test database `eplaneur_test`.

## 6. Check

| URL | Expected |
|---|---|
| https://eplaneur.local | Home page with the Symfony web debug toolbar at the bottom |
| http://localhost:8025 | Mailpit inbox |

```bash
make test   # PHPUnit + Behat must be green
```

Day-to-day commands are described in [development](development.md).
