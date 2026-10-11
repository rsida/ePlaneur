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

The dashboard of the member space shows the next two sessions ("On se retrouve en vol"); the home
page shows the next one in its banner and the next six in "02 / Les prochains vols" (the latest
three with their results when none is announced; `upcoming_sessions()` and `recent_sessions()` of
`SessionExtension`).

### Session form

The flight plan is one text field with suggestions (Stimulus `flight-plan-field`, a `<datalist>` fed
by `/plans-de-vol/recherche`): picking a recorded flight plan fills in the address of its sheet; for
a new code the address of its eplaneur.fr page is suggested and can be changed. The text must hold a
code of the library. The results file is the CSV written by the dedicated server, uploaded with its
original name (the race id is read from it); comma, semicolon or tab separated, units allowed.

## Race view

In 3D the replay shows the thermals of every pilot so far (roadmap 7h4, `sessionThermalLayers()`):
thermals whose first circles are less than 600 m apart are one cylinder, from the lowest entry to the
highest exit, coloured by the average climb weighted by the time spent, labelled from zoom 10.5
with how many pilots climbed in it and their climb. The thermals of the pilot followed, in their
colour, can be added. With "Départ du circuit ensemble" on a flight plan
page, the flights may come from different days: their thermals are grouped all the same.

The layers button under the zoom of the replay map (roadmap 7j6, the panel of the flight page in
`templates/flight/_layers.html.twig`) turns on or off, kept in the browser (`session.layers-off`):
the thermals of every pilot, those of the pilot followed (off at first), task and turnpoints, whole
tracks, trails behind the gliders, the curtain under the pilot followed.

On the session page, below the replay, the **thermal map of the race** (`race-thermals` Stimulus
controller, data from `/sessions/{id}/thermiques.json`, `FlightSession::thermalMap()`): every thermal
of every track after its start line, at its centre, thermals closer than 700 m taken as one. Over
the whole race the size of a point says how many climbs it gave, its colour how strong they were
(under 1, 1 to 2, 2 to 3, over 3 m/s, tokens `--thermal-*`); hovering gives the values. With the
slider, the time counts from each pilot's start line (pilots join the server at different times,
their recordings share no clock): a thermal shows while a pilot climbs in it and for 10 minutes
after.

**Comparer toute la session** (`/sessions/{id}/comparaison`, `SessionComparison`, two tracks that
started at least): at each 250 m of the task, each pilot's time since their start against the
fastest time any pilot took to get there (the virtual leader). Charts of the gap and of the altitude
along the task for every pilot (`session-compare` Stimulus controller, values of every pilot under
the cursor), the time of each pilot on each leg with the best in bold, and for each pilot the 10 km
stretch where they lost the most, with what they did there and what the fastest pilot on that
stretch did, and a link to compare the two flights.

On the page of a flight of the session: its rank among the tracks of the race for the climb in the
thermals and the speed of the transitions on the task (`FlightSession::ranks()`, ties share a rank),
and "Comparer au vainqueur" (the first of the session ranking).

## Task settings

A session can hold the settings of its task (`App\Session\TaskSettings`, stored as JSON): date and
time in Condor, start delay, start window (0: regatta start, the task time counts from the opening
of the gate), launch and its height, maximum start height and ground speed, minimum finish
altitude, maximum wing loading, penalties, realism options and the weather of each zone (wind,
thermal strength and activity, cloud base, inversion). Two sources, same values:

- the **Condor Club link** of the session (`condor.club/task/<id>`): saving the form reads
  `https://condor.club/api/task/<id>`, the public JSON its pages load (`CondorClubTasks`); when
  Condor Club does not answer, a message asks for the `.fpl`;
- the **flight plan file** (`.fpl`, an INI file), uploaded in the form: the one of the library, or
  the one Condor writes for each race in `Documents\Condor3\RaceResults` (`Race <race id> on
  <date>.fpl`, next to the results `.csv`). It replaces the settings of Condor Club.

How the values read (checked on C3-s117 and C3-c046a against their Condor Club pages): `TaskDate`
is a Delphi date; `StartTime`, `StartTimeWindow` and `RaceStartDelay` are hours; `StartType` 0 is
an aerotow, 2 an airborne start (1, a winch, is assumed); the minimum finish altitude is the
`TPWidth` of the finish, the maximum start height the `TPHeight` of the start; wind speeds are m/s
in the `.fpl`, km/h on Condor Club; the cloud base is 125 m per degree between temperature and dew
point; thermal strength 2 is "Faible", activity 3 "Faible" and 4 "Normale".

The session page shows them ("Le circuit dans Condor", "Météo", "Pénalités et options de
réalisme"), and so does the page of each flight of the session, with the official result of the
pilot (row of the server results with the same CN) and the final glide against the minimum finish
altitude.

"Score et pénalités" on the flight page (roadmap 7k1, `App\Session\Penalties`) lists, like
Condor Club, the penalties the track shows: start ground speed and height against their maximum,
finish altitude against its minimum, each with its points lost and a link to the moment; the other
rules of the task (cloud flying, thermal helpers, recoveries, windows, zones) in one line, as the
track does not show them. The estimate comes with the official total of the results and the gap
between the two, the server's total being the one that counts. Measured on the races of the dev
data (2026-10-11), the two often differ: the server penalised a start at 185 km/h of ground speed
63 points instead of 29 and did not penalise one at 173 km/h; 200 points on another flight came
from a rule the track does not show. The rule is on ground speed (`MaxStartGroundSpeed` in the
`.fpl`), but Condor measures it at the exact moment of the crossing, which the 1 s series and the
0.03 s precision of the `.ftr` times cannot match.

When the flight is an IGC exported by Condor, its `LCON` lines give the points of each rule
(`CondorReport`): the table shows them instead of the estimate ("Total calculé par Condor"), with
every rule that cost points, and the measure of a rule that cost points follows exactly (limit plus
points over the rate: 0.2 point at 2 points per km/h above 170 km/h is 170.1 km/h on C3-c060).
Condor's total is preferred to the one of the results file, which rounds it (0 for 0.2).

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
