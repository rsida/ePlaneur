# ePlaneur documentation

| Document | Content |
|---|---|
| [Roadmap](roadmap.md) | Planned order of work, status, decisions and open questions |
| [Project (functional reference)](project/README.md) | Purpose of the site, the club, training, network flights, ecosystem, current site, glossary |
| [Accounts and permissions](accounts.md) | Registration, login, groups, permissions, content visibility, console commands, dev accounts |
| [Content: posts, blocks, media](content.md) | Post model, block types and how to add one, media storage and access, post permissions |
| [Design system](design-system.md) | Tokens, surface themes, layout primitives, Twig components, icons |
| [Installation (development)](installation.md) | Prerequisites and first setup of the local environment |
| [Configuration](configuration.md) | `.env` files, load order, every variable |
| [Daily development](development.md) | Makefile commands, database, assets, mails, Xdebug |
| [Tests and quality](tests.md) | PHPUnit, Behat, test database, PHP-CS-Fixer, PHPStan |
| [Production](production.md) | Server prerequisites, deployment by tag, rollback, backups |
| [Troubleshooting](troubleshooting.md) | Known issues and fixes |

## Stack at a glance

| Service | Dev | Prod |
|---|---|---|
| `php` | PHP-FPM 8.5 + Xdebug, sources bind-mounted | PHP-FPM 8.5, sources, vendors and compiled assets baked into the image |
| `nginx` | Serves `https://<SERVER_NAME>` with a mkcert certificate | Plain HTTP behind Traefik, long cache on `/assets/` |
| `database` | MariaDB 11.8, exposed on `localhost:3307` | MariaDB 11.8, not exposed |
| `mailer` | Mailpit, UI on `http://localhost:8025` | — (real `MAILER_DSN`) |
| `worker` | — (messages handled synchronously) | `messenger:consume async` |
| `traefik` | — | Own Traefik instance, Let's Encrypt certificates, HTTP → HTTPS |

Files:

```
compose.yaml            services shared by every environment
compose.override.yaml   dev only (loaded automatically by Docker Compose)
compose.prod.yaml       prod only
docker/php/Dockerfile   multi-stage: php_dev, php_prod, nginx_prod
docker/nginx/           nginx configs (shared Symfony rules + dev/prod server blocks)
docker/mariadb/init/    SQL run once when the dev database volume is created
docker/certs/           local TLS certificate (generated, git-ignored)
Makefile                every command, run `make` to list them
```

The project is fully standalone: it does **not** use the shared Traefik of `../local-network-multisite`.
