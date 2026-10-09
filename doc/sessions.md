# Network sessions

Roadmap step 9f. The club site keeps the network sessions and their results; the flight plan library
(FPL list, technical sheets, `.fpl` and `.tsk` files) stays on eplaneur.fr, which each session links
to (decision of 2026-10-09, see [ecosystem](project/ecosystem.md)).

## Model

- **`FlightPlan`**: a flight plan of the library the club has flown. Its code (`C3-c046a`: Condor
  version, category letter `d` initiation / `s` standard / `c` competition, number, variant) is
  unique; route, gliders, landscape and the address of its sheet on eplaneur.fr
  (`https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=<code>` by default). A flight plan is recorded
  the first time a session or an uploaded track uses its code (`App\Session\FlightPlans`); the Condor
  task name of the club's files gives the rest (`C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur`).
- **`NetworkSession`**: flight plan, date, briefing time (metropolitan France; unknown for the
  history), dedicated group (`dedicatedTo`, empty: open to everyone), organiser, Condor Club circuit,
  practical notes, and the results of the dedicated server: the name of its file
  (`DSRace-971793592-on-2026-10-08`), the race id read from it, and one row per pilot (rank, status,
  player, CN, registration, glider, distance, time, speed, penalty, score; `App\Session\SessionResults`).
- **`Flight::$session`**: the session a track was flown in.

## Tracks and sessions

Every pilot of a race on the Condor dedicated server gets the same race id in the header of their
`.ftr` file, and the server names its results file after it (checked on 2026-10-09: the four tracks
of the dev database match the results files of eplaneur.fr). So (`App\Session\SessionLinker`):

- an uploaded track joins the session that has its race id;
- otherwise, a session on its flight plan without results yet, held on the day of the upload or up
  to three days before (an IGC file has no race id but has a date; a `.ftr` file has no date);
- when a session gets its results, it takes every track of its race and drops the tracks of another
  race that had joined it by date (a pilot flying the task alone the same evening).

A `.ftr` track takes the date of its session (`Flight::getDate()`).

## Pages

| URL | Route | Access | Content |
|---|---|---|---|
| `/sessions` | `app_session_index` | everyone | The coming sessions (cards), then the past ones: filters by year, Condor version, category, open or dedicated; 20 per page |
| `/sessions/{id}` | `app_session_show` | everyone; results and tracks for logged-in users | Flight plan, date, organiser, links to the FPL sheet and the Condor Club circuit, practical notes; once flown, the results of the server (with a link to the analysis of each pilot whose CN matches an uploaded track), then the replay, podium and ranking of the uploaded tracks |
| `/sessions/nouvelle` | `app_session_new` | `SESSION_ORGANISE` (members) | Propose a session; the proposer becomes its organiser |
| `/sessions/{id}/modifier`, `/supprimer` | `app_session_edit`, `app_session_delete` | its organiser, or `SESSION_MANAGE` (`SessionVoter`) | Edit, add the results file of the server, delete (the tracks stay) |
| `/plans-de-vol/{code}` | `app_flight_plan_show` | everyone; tracks for logged-in users | Link to the sheet on eplaneur.fr, the club's sessions on it with their winner, and every track on it, replayed from their start line (ghost race) |
| `/plans-de-vol/recherche?q=` | `app_flight_plan_search` | `SESSION_ORGANISE` | JSON suggestions of the session form |

`/vols/{id}/session` now redirects to the session of the flight, or to its flight plan page; flights
whose task is not a flight plan of the library keep the former page (every flight on the same task).

The dashboard of the member space shows the next two sessions ("On se retrouve en vol").

### Session form

The flight plan is one text field with suggestions (Stimulus `flight-plan-field`, a `<datalist>` fed
by `/plans-de-vol/recherche`): picking a recorded flight plan fills in the address of its sheet; for
a new code the address of its eplaneur.fr page is suggested and can be changed. The text must hold a
code of the library. The results file is the CSV written by the dedicated server, uploaded with its
original name (the race id is read from it); comma, semicolon or tab separated, units allowed.

## History from eplaneur.fr

`make console c="app:sessions:import"` reads the public pages of the eplaneur.fr tools: the session
list (`/tools/afficher-sessions.php`, 635 sessions since 2020 on 2026-10-09), the same list per
dedicated group (`?session_dediee=`, values in `EplaneurSessions::DEDICATED`) and the results table
of each session (`/tools/lire-tableau-csv-resultats-session.php`). It creates the flight plans and
sessions, reads the results, and joins the tracks of each race. Imported sessions are kept; running
it again only adds the new sessions and the missing results.

| Option | Use |
|---|---|
| `--since=2026-01-01` | Only the sessions from this day |
| `--no-results` | Do not read the results pages (one request per session) |
| `--delay=0.5` | Seconds between two requests to eplaneur.fr (default 0.5 s; the full history takes about 6 minutes) |
| `-v` | One line per session |

The owner of eplaneur.fr was asked for an export of the sessions tables (2026-10-09); this import of
the public pages is the fallback and gives the same data except the organisers. While sessions are
still published on eplaneur.fr, running the command daily keeps the club site up to date.

## Back-office

"Sessions" and "Plans de vol" (`SESSION_MANAGE`, committee), see [back-office](admin.md).
