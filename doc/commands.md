# Console commands

The commands of the project, run in the `php` container through the Makefile:
`make console c="app:..."`. Every command lists its options with `--help`.

## Accounts and groups

| Command | What it does | Document |
|---|---|---|
| `app:push:keys` | Prints a pair of VAPID keys for the notifications, to put in `.env.local` | [installable site](installable-site.md#notifications) |
| `app:user:create <email> <name> [--group=]` | Creates a verified account; asks its password (without terminal, none: "Mot de passe oublié") | [accounts](accounts.md) |
| `app:user:groups <email> [--add=] [--remove=]` | Shows, adds or removes the groups of an account | [accounts](accounts.md) |
| `app:group:list` | Lists the groups, their permissions and member count | [accounts](accounts.md) |
| `app:group:permissions <group> [--grant=] [--revoke=]` | Grants or revokes permissions of a group | [accounts](accounts.md) |

## Scheduled tasks

Run by cron in production ([production](production.md)).

| Command | When | What it does | Document |
|---|---|---|---|
| `app:membership:expire` | Daily | Removes the "Membre" group from the accounts whose membership ended (after the grace month) | [membership](membership.md) |
| `app:membership:remind` | Daily | E-mails the renewal reminder, once, when the next season is open | [membership](membership.md) |
| `app:sessions:import [--since=]` | Daily, while eplaneur.fr publishes sessions | Imports the network sessions and their results from eplaneur.fr | [sessions](sessions.md) |
| `app:sessions:mail [--dry-run]` | Daily, in the evening | Sends the reminders of tomorrow's sessions and the results of the last ones, once each, by e-mail and as notifications | [sessions](sessions.md#reminders-and-results) |
| `app:digest:send [--month=] [--dry-run]` | The first day of the month | Sends the monthly digest of the last month; a second run sends it again | [monthly digest](monthly-digest.md) |

## Imports and data

| Command | What it does | Document |
|---|---|---|
| `app:import-wordpress` | Imports the pages, posts and files of club.eplaneur.fr, with redirects of the old addresses | [WordPress import](wordpress-import.md) |
| `app:flight:analyse` | Analyses every stored flight again from its file, after the analysis changed | [flights](flights.md#analysis) |
| `app:gliders:import <Planes folder>` | Imports the gliders (sheet and polar) of a Condor 3 installation | [flights](flights.md#gliders-and-polars), [development](development.md) |
| `app:condor:airfields <.apt file>` | Imports the airfields of a Condor landscape (glide cone, "Posé sur un terrain") | [flights](flights.md), [development](development.md) |
| `app:condor:airspaces <.air file>` | Imports the airspaces of a Condor landscape | [flights](flights.md), [development](development.md) |
| `app:condor:landscape <.trn file>` | Prints the configuration line of a Condor landscape | [flights](flights.md#landscapes-and-coordinates) |
