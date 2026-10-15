# What the site does

An overview of the features, by audience, with the page or screen and the document that describes
each one. The domain reference (the club, the training, the network flights) is in
[project](project/README.md); the order of work and its decisions in the [roadmap](roadmap.md).

## Visitors

| Feature | Where | Document |
|---|---|---|
| Home page: the club, the next network session with its countdown and the time of the reader, the latest news, a public flight replayed in 3D, "Votre premier vol ?" | `/` | [content](content.md#news-list-and-home-page), [sessions](sessions.md#calendar), [flights](flights.md#viewer) |
| Pages and news imported from the WordPress site, with the redirects of the old addresses | `/{path}`, `/actualites` | [content](content.md), [WordPress import](wordpress-import.md) |
| Mon premier vol: six steps from the discovery of the club to the first network flight | `/premier-vol` | [Mon parcours](training-path.md#mon-premier-vol) |
| The training path as a path of eight steps, its badges and their thresholds | `/parcours` | [Mon parcours](training-path.md) |
| Network sessions: coming ones, history since 2020 with results, flight plans, calendar to subscribe to | `/sessions`, `/plans-de-vol/{code}` | [sessions](sessions.md) |
| Public flights, chosen by their pilots | `/vols/{id}` | [flights](flights.md#viewer) |
| Gliders of Condor with their polar | `/planeurs` | [flights](flights.md#gliders-and-polars) |
| Free account, membership request and online payment, contact form | `/inscription`, `/adhesion`, `/contact` | [accounts](accounts.md), [membership](membership.md), [member space](member-space.md) |
| Search engines and AI answer engines: structured data, sitemap, `llms.txt` | | [SEO and GEO](seo.md) |
| Installable on a phone or a computer, offline page; notifications per device (network sessions, messages, comments on a flight) | Footer, "Installer l'application"; `/mon-compte/profil#push` | [installable site](installable-site.md) |

## Pilots (free account)

| Feature | Where | Document |
|---|---|---|
| Dashboard: membership, latest flights, Mon premier vol, the step of Mon parcours to work on and its badges, coming sessions, messages | `/mon-compte` | [member space](member-space.md) |
| Upload a Condor track (`.ftr`, IGC) and analyse it: phases, thermals, glides, advice, penalties, 2D and 3D replay with the camera behind the glider, ghosts, glide cone, clouds | `/vols` | [flights](flights.md) |
| Compare two flights, replay and compare a whole session, the lift of the club | `/vols/{id}/comparer`, `/sessions/{id}`, `/vols/ascendances` | [flights](flights.md#comparison), [sessions](sessions.md#race-view) |
| Logbook: totals, flight badges, skills and their trend, the training path marked by the trainers | `/mon-compte/carnet` | [flights](flights.md#progression) |
| Mon parcours: readings, quizzes with their correction, exercises checked by the analysis of the flight, personal bests, rankings by season and level, badges | `/parcours` | [Mon parcours](training-path.md) |
| Records of the club and ranking of the season | `/vols/records` | [flights](flights.md#club-life) |
| Profile, name shown to the others (name, first name or pseudonym), e-mail preferences (comments, messages, renewal, the monthly digest, session reminders and results), privacy, export and deletion of the data | `/mon-compte/profil`, `/mon-compte/confidentialite` | [member space](member-space.md), [name shown](accounts.md#name-shown), [monthly digest](monthly-digest.md) |

## Members

| Feature | Where | Document |
|---|---|---|
| Content reserved to members | Pages and posts | [content](content.md#pages-menus-and-restricted-content) |
| Propose a network session | `/sessions/nouvelle` | [sessions](sessions.md) |
| Write to other members, the committee or the administrators | `/mon-compte/messages` | [member space](member-space.md#messages) |

## Trainers (a group with `FLIGHT_REVIEW`)

| Feature | Where | Document |
|---|---|---|
| Comment the flights of the pilots, at a moment of the flight | Flight page | [flights](flights.md#progression) |
| Follow a pilot: logbook, steps marked or validated, what they did in Mon parcours | `/vols/carnet/{id}` | [flights](flights.md#training-path), [Mon parcours](training-path.md) |

## Committee

| Feature | Where | Document |
|---|---|---|
| Posts and pages in a block editor, media, official documents, menus | `/admin` | [back-office](admin.md), [content](content.md) |
| Memberships: seasons and fees, payments, validation, export, search of the members | `/admin` | [membership](membership.md), [back-office](admin.md) |
| Sessions and flight plans, contact messages | `/admin` | [sessions](sessions.md), [member space](member-space.md) |
| Activity of the club month by month, CSV export for the general meeting | `/activite` | [activity](activity.md) |

## Administrators

| Feature | Where | Document |
|---|---|---|
| Accounts, groups and their permissions | `/admin` | [accounts](accounts.md) |
| Mon parcours: open the steps, place readings, exercises (flight plan, glider, criteria, ranking), quizzes and validations; the quiz builder | `/admin/path-step`, `/admin/quiz` | [Mon parcours](training-path.md#back-office) |
| Flight plan workshop: the instructions to have a Condor 3 flight plan written | `/plans-de-vol/atelier` | [sessions](sessions.md#flight-plan-workshop) |
| Console commands: imports, scheduled tasks, accounts | | [console commands](commands.md) |
