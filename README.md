# ePlaneur

Website of the **Club ePlaneur**, a French association that teaches and organises virtual gliding
(*ePlaneur*) with the Condor simulator: a structured 8-step training path, weekly multiplayer
"network flights", membership and association life, all run online. This project rebuilds the
current WordPress site https://club.eplaneur.fr with Symfony.

What the site does and for whom: [doc/project/](doc/project/README.md).

## Stack

Symfony 8.1 (PHP 8.5), MariaDB 11.8, AssetMapper + Stimulus/Turbo, fully dockerized (nginx + PHP-FPM,
own Traefik in production).

## Quick start

```bash
make install   # first setup, see doc/installation.md for the prerequisites
make           # list every command and the local URLs
```

- Site: https://eplaneur.local — Mailpit: http://localhost:8025
- Technical documentation: [doc/](doc/README.md)
