# ePlaneur documentation

| Document | Content |
|---|---|
| [What the site does](features.md) | The features by audience (visitors, pilots, members, trainers, committee, administrators), with their pages and documents |
| [Roadmap](roadmap.md) | Planned order of work, status, decisions and open questions |
| [Project (functional reference)](project/README.md) | Purpose of the site, the club, training, network flights, ecosystem, current site, glossary |
| [Accounts and permissions](accounts.md) | Registration, login, groups, permissions, content visibility, console commands, dev accounts |
| [Content: posts, blocks, media](content.md) | Post model, block types and how to add one, media storage and access, post permissions |
| [SEO and GEO](seo.md) | What search and AI answer engines see: robots, canonical, Open Graph, JSON-LD, `robots.txt`, `sitemap.xml`, `llms.txt`, search settings of posts and pages |
| [WordPress import](wordpress-import.md) | `app:import-wordpress`: pages, posts and files of the current site, page tree mapping, redirects of the old addresses |
| [Flight viewer](flights.md) | `/vols`: Condor `.ftr` and IGC tracks, landscape projections, analysis, 2D/3D viewer and its chase camera, replay of the home page |
| [Condor 3 integration study](condor-integration.md) | What Condor 3 writes and outputs (IGC export, race results, UDP, `cndr3:` links) and what the site could do with it |
| [Mon parcours](training-path.md) | `/parcours`: the training path as a path of progression, steps and items, quizzes, exercises checked by the analysis, rankings by season and level, badges; `/premier-vol`; back-office |
| [Presentation for the committee](site-tour.md) | `/comite/le-site`: every feature, the striking ones first, with live links; reserved to the committee |
| [Teacher space](teacher-space.md) | `/mon-compte/classes`: classes of the teachers, the link to join, the progression of the pupils, the rules for minors |
| [Monthly digest](monthly-digest.md) | The e-mail of the month for the accounts that asked for it, and its command |
| [Installable site](installable-site.md) | Manifest, icons, service worker, offline page and install button |
| [Activity of the club](activity.md) | `/activite`: the activity month by month for the committee, its charts and CSV export |
| [Network sessions](sessions.md) | `/sessions`: coming sessions, history and results, flight plans, tracks joined by race id, import from eplaneur.fr |
| [Membership](membership.md) | `/mon-compte/adhesion`: seasons and fees, requests, validation, certificate, end of membership, reminders |
| [Member space and contact](member-space.md) | `/mon-compte`: dashboard, flights, profile, privacy, GDPR export and deletion; contact form |
| [Back-office](admin.md) | `/admin` (EasyAdmin): access, screens and their permissions, how to add a screen |
| [Figma prompts](figma-prompts.md) | Prompts used to design screens without mock-up, and their status |
| [Design system](design-system.md) | Tokens, surface themes, layout primitives, Twig components, icons |
| [Installation (development)](installation.md) | Prerequisites and first setup of the local environment |
| [Configuration](configuration.md) | `.env` files, load order, every variable |
| [Daily development](development.md) | Makefile commands, database, assets, mails, Xdebug |
| [Console commands](commands.md) | Every `app:*` command: accounts, scheduled tasks, imports |
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
