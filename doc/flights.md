# Flight viewer

Roadmap step 7: Condor Club-like flight analysis, focused on training. Logged-in users upload a
Condor flight track at `/vols`; any logged-in user sees the flights of the club on a map, in 3D at
their real altitude over the relief, with their charts, phases, task legs and a replay. A pilot can
make a flight public: visitors then see its page (without the comments), and the home page shows
the latest public flights.

| What | Where |
|---|---|
| Pages | `FlightController`: `/vols` (upload, my flights, latest flights), `/vols/{id}` (viewer), `/vols/{id}/analyse.json` (data of the viewer), `/vols/{id}/session` and `/vols/{id}/session.json` (session of the flight: ranking, podium, replay of all pilots), `/vols/{id}/comparer/{other}` and its `.json` (comparison with another flight of the session; `/vols/{id}/comparer` picks the best other one), `/vols/{id}/supprimer` (`FLIGHT_DELETE`: the pilot who uploaded it or an administrator, `FlightVoter`) |
| Reading files | `App\Flight\Reader\`: `FtrReader` (Condor 3 `.ftr`), `IgcReader` (IGC), `TrackReader` (format from the content, longest continuous part) |
| Landscapes | `config/condor/landscapes.yaml`, `App\Flight\Condor\LandscapeRegistry`, `app:condor:landscape` |
| Analysis again | `app:flight:analyse`: every stored flight from its file, after the analysis changed |
| Analysis | `App\Flight\Analysis\FlightAnalyzer` → `FlightAnalysis` (stored as JSON in `Flight::analysis`) |
| Storage | `Flight` entity; files in `app.flights_dir` (`var/flights`, Docker volume `flights` in production) through `FlightImporter` |
| Viewer | `assets/flight/map.js` (map shared by the viewers), `assets/flight/chart.js` (value label of every chart, formats), `assets/controllers/flight_viewer_controller.js` and `session_viewer_controller.js` (lazy), `templates/flight/`, `assets/styles/pages/flight.css` |
| Session | `App\Flight\FlightSession` (ranking, podium), `FlightRepository::findSession()` |
| Comparison | `App\Flight\FlightComparison`, `assets/controllers/compare_viewer_controller.js` |
| Club life | `App\Flight\Club\ClubRecords` (records, season ranking), `Badge`, `App\Flight\Analysis\FlightMarks`, `/vols/records` |
| Progression | `App\Flight\Advice\FlightAdvisor` (advice), `App\Training\TrainingStep`, `FlightComment` (comments), `/vols/carnet` and `/vols/carnet/{user}` (logbook, `logbook_chart_controller.js`), `FlightVoter` (`FLIGHT_COMMENT`, `FLIGHT_COMMENT_DELETE`, `LOGBOOK_VIEW`) |

## Files

### Condor 3 track (`.ftr`)

The file Condor saves in `Documents\Condor3\FlightTracks` and that pilots upload to Condor Club. It is
not documented; what follows was measured on Condor 3.1 files (2026-10-08):

| Offset | Content |
|---|---|
| 0 | `FTR`, then the Condor version (int at 3: 3100) |
| 0x9b, 0xac, 0xbd, 0xce, 0xd6, 0xdf | Pascal short strings: first name, last name, country, registration ("F-CFDU"), competition id ("FDU"), landscape |
| 0xf3 | Number of task points (int); points of 84 bytes from 0xf7: name (short string), x, y, z (floats at +17), radius and angle (ints at +44 and +48: 360° cylinder, 180° half disc for a start or finish), then floats at +52 (1,500), +56 (minimum finish height?) and +60 (maximum height). The first point is the departure airfield, the second the start, the last the finish |
| 0x52eb, 0x52fc | Glider class and model; the flight plan name follows (first short string after 0x531e) |
| 0x549b | Six ints: 1, -1, Condor race id, 0, number of records, ? |
| 0x54b3 | Records of 64 bytes: time (+0, see below), x, y, altitude (floats at +4, +8, +12), attitude, and 24 bytes that look encrypted |

The time of each record is a float in units of 32 hours (115,200 s) whose bits lack 0x500000:
`unpack('g', pack('V', bits + 0x500000)) × 115,200` grows linearly with the time. Found on
2026-10-08 against the IGC export of the same flight (C3-c060): every IGC fix lies within 1.5 m of
the record of its time, and the track lasts 5,911 s in both. Records are 0.06 to 1.2 s apart (about
0.1 s, more spaced when the simulator is busy), so an even interval is wrong by up to 2 %. A file
whose times do not decode to growing values falls back on an even interval of 0.104 s
(`FtrReader::RECORD_INTERVAL`), and the viewer then says durations and speeds are estimated.

Task times checked against the Condor results: s0109 4,524 s (Condor 4,527), LastTrack 4,216 s
(4,219), c060 4,846 s (4,909: the IGC export gives the same 4,846 s; the 63 s may be a penalty).

Condor 2 tracks are not read (their format differs): pilots export an IGC.

### IGC

The FAI flight recorder format, exported by Condor ("Analyse du vol" > "Export IGC") or by a real
logger. Condor writes a fix about every second, sometimes two in the same second (the second one is
put half a second later), the task in C records (take-off, start, turnpoints, finish, landing: the
landing is left out) and the zones in `LSEEYOU OZ=` lines (R1 radius, A1 half angle). B records
give the fixes (UTC time, GNSS altitude, pressure altitude when it is missing), H records the
pilot, glider, competition id and date. Times are exact.

### Discontinuities

Condor moves the glider at once in some cases: 500 m higher after a crash, and at the very record
where it reaches the finish (that position is kept, at the altitude before the jump, so the finish
is still reached). Such a
jump would read as a 5,000 m/s climb, so `TrackReader` cuts the track where the move between two
fixes is beyond any glider (over 150 m/s horizontally or 80 m/s vertically, and over 30 m) and keeps
the longest part. A hard pull-up climbs 4 m in the 0.1 s between two Condor records (40 to 50 m/s):
it is not a jump. A flight with a height or glider recovery (penalised by the server) keeps only its
longest part, so its task may read as not finished; the official result of its session says what
the server counted.

## Landscapes and coordinates

Condor positions are metres in a UTM zone, measured from the south-east corner of the landscape:
easting = easting_origin − x, northing = northing_origin + y. The zone and the corner are in the
header of the landscape's height map, `Landscapes/<Name>/<Name>.trn` (same header in Condor 2 and
3): two ints (columns, rows), five floats (cell size, two unknown, easting and northing of the
corner), the zone (int) and the hemisphere (`N`/`S`). Checked against the coordinates of the
landscape's SeeYou file (`AA3.cup`): the converted turnpoints are within the precision of the file.

To add a landscape (FrSW, used by the club, is not in the list yet): copy its `.trn` file into the
project, run `make console c="app:condor:landscape var/tmp/FrSW.trn"` and add the printed line to
`config/condor/landscapes.yaml`. A track on an unknown landscape is refused with a message asking an
administrator to add it.

## Analysis

`FlightAnalyzer`, all values on one time axis:

1. **Resampling** to one point per second (linear interpolation): Condor records about 10 fixes per
   second, IGC 1.
2. **Ground speed and vario** over 4 seconds centred on each point (the difference between close
   points is mostly noise). The vario is the change of altitude, not a total energy vario.
3. **Phases**: the turn rate (heading change, averaged over 11 s) above 6°/s means circling.
   Circling for less than 20 s is a turn, not a thermal; a straight part shorter than 20 s between two
   thermals is a re-centring. Thermals give their duration, height gained, average climb rate and
   direction; glides their distance, speed and glide ratio (when they lose at least 10 m).
4. **Statistics** between take-off and landing (ground speed above 40 km/h): duration, distance
   flown, altitudes, maximum and average speeds, varios, thermals (count, time, gain, average
   climb, share of the flight, share circling left), glides (time, distance, glide ratio, speed).
   Beyond them, measured as Condor Club does (checked on the C3-s117 flight of 2026-03-05 against
   its Condor Club page, roadmap 7g1):
   - **release**: the end of the tow or winch climb, the first time the glider sinks for 5 s; the
     best height gained from a low point (1,074 m, Condor Club 1,074.20 m) and the height climbed in
     all (4,698 m, Condor Club 5,093 m) are counted from it;
   - **spirals** right and left: each run of one full turn or more without straightening, with their
     average climb (Condor Club counts more of them: 12 and 4 against 3 and 3; its rule is unknown);
   - **stops**: 30 s under the flying speed between take-off and landing;
   - on the task when it was finished: the **band** of the thermals, average altitude at entry and
     exit (1,841 → 2,120 m, Condor Club 1,838 → 2,122 m), and the **detour** of the transitions,
     glides losing height whose ends are 1 km apart or more, split at the turnpoints reached on the
     way, the distance flown taken from a point every 30 s (14 %, Condor Club 9.8 %);
   - the **final glide**, from the end of the last thermal to the finish: last climb, distance,
     time, arrival altitude, height above the finish point and the climb time it stands for (the
     minimum arrival height of the task is not read from the file);
   - each turnpoint: altitude and ground speed when it was reached (the times of the passages match
     Condor Club to the second).
   The `.ftr` gives the water ballast (litres at take-off).
   **Phases** (roadmap 7g2, checked on the flight of FDU of 2026-10-08 against its Condor Club page):
   - each thermal gets its full turns, radius (speed over turn rate), bank (tan = v·ω/g, from the
     ground speed: estimated) and wind (drift from the centre of its first circle to the centre of
     its last one; FDU: 10 km/h from 262°, Condor Club 10 km/h from 246°);
   - straight flight that climbs (1 m/s over 30 s, for a minute and 50 m) is its own phase, slope
     or wave lift (FDU: 1, +299 m; Condor Club 1, +269 m). A height recovery of Condor ("miracle")
     reads as one too;
   - on the task (phases cut at the start and the finish): thermals, share of the time circling,
     average climb, left / right, glides (distance, speed, glide ratio: FDU 225 km at 206 km/h, 59.1;
     Condor Club 216 km at 208 km/h, 58.4), wind averaged as vectors, slope or wave lift;
   - the spiral: average radius and bank, and the radius of the best quarter of the thermals by
     climb (four thermals at least), the "target";
   - the climb by altitude: average climb in the thermals per band of 200 m (20 s at least).
   Not yet: netto, effective MacCready, speeds to fly by MacCready band and wing loading: they need
   the polar and masses of each glider.
5. **Task**: each point is reached when the track enters its zone: a cylinder (360°), or for
   Condor's 180° start and finish a half disc of the radius behind the line across the leg (Condor
   counts the finish as soon as the glider is within the radius on the side it comes from); other
   angles (FAI sectors) are approximated by cylinders. The start is the first point out of the start
   zone, after the last time it was in it
   before the first turnpoint. Legs give their distance (between the centres), time and speed.
   On the race of 2026-02-19 the task distance is 182.4 km (Condor: 182.8 km).
6. **Launch** ("Départ en vol", "Remorquage", "Treuil", shown as a tag on the flight and in the
   lists): airborne when the track does not begin on the ground, that is still for 5 s or within
   50 m of the height of the departure airfield (the first task point). From the ground, a winch
   when the glider gains 150 m or more in the 30 s after lift-off (10 m above the ground), an aerotow
   otherwise (2 to 5 m/s behind the tug). Read from the track so that IGC files get it too. On the
   six sample tracks it matches the int at 0x73 of the `.ftr` header (2 airborne, 0 aerotow; the
   value for a winch is unknown, no sample yet).

The viewer reads `FlightAnalysis::toArray()`, stored when the flight is uploaded (about 250 KB for a
1 h 40 flight). After changing the analysis, `make console c="app:flight:analyse"` analyses the
stored flights again from their files (in production too, after the deployment).

## Gliders and polars

The sheet and the polar of each glider come from the files of Condor (`App\Flight\Glider\CondorGliders`,
`make console c="app:gliders:import <folder>"`): every folder of `Condor3/Planes` with a
`<name>.txt` (the sheet Condor shows when choosing a glider: maker and model, span, wing area,
length, masses, water ballast, wing loadings, speeds, minimum sink, best glide, DAeC handicap,
engine) and a `<name>.pol` (the polar). Tow planes have no polar and are left out. Run it again after
a Condor update: gliders are updated by name. The name is the one of the folder, which the tracks
(`Flight::$glider`) and the results of the server use ("Diana2", "JS3-15").

- **Polar** (`Polar`): a count, then six floats per node: the point (km/h, vertical speed in m/s),
  the handle towards the previous node and the one towards the next; two nodes and the handles
  between them are a cubic Bézier curve. Decoded on 2026-10-10: the minimum sink and best glide of
  the curves match the sheets within 2 % (Diana 2: 0.47 m/s, 49.5 at 89 km/h; sheet 0.45 m/s, 50 at
  91 km/h). Condor does not say at which mass: the curve is taken as the glider without water at its
  minimum wing loading (`Glider::getReferenceMass()`; when the sheet gives none, empty plus a pilot of
  90 kg). At a mass m, speeds and sink rates are multiplied by √(m / reference).
- **Pages**: `/planeurs` (every glider: span, best glide, minimum sink, wing loading, water, handicap,
  tracks of the club) and `/planeurs/{name}` (the sheet, aspect ratio, best glide and minimum sink
  without water and full, the polar at both masses, MacCready speeds for 0.5 to 4 m/s), open to
  everyone. Glider names in session results and rankings link to them (`glider_url()`).
- **On the flight page** (`GliderPerformance`), when its glider is recorded: mass (reference plus
  the water of the `.ftr`, a litre per kilogram), wing loading, best glide and minimum sink at that
  mass, the MacCready speed for the average climb of the flight and the average speed it gives,
  the polar at that mass with the glides of the flight as points (clicking one moves the viewer
  there) and the MacCready speed. A track gives the ground speed: the airspeed of a glide is
  estimated by taking away the wind of the flight (average drift of its thermals, as vectors); its
  sink rate is the height lost over its duration, so a glide through rising air shows above the
  curve.
- **Advice**: the speed of the glides against that MacCready speed (see Progression).

Not done: netto vario (needs the airspeed at each second, not only per glide), effective MacCready
of each glide.

## Downloads

Logged-in users can download, from the page of a flight, its original file (`/vols/{id}/fichier`,
`.ftr` or `.igc`, kept unless the flight was anonymised) and an IGC export of the analysed track
(`/vols/{id}/igc`, `IgcExporter`): one B record per second, the task as C records with its zones
as LSEEYOU lines (as Condor writes them), the pilot, competition id and glider as H records, ASCII
only. The time of day of a `.ftr` track is not known: the export starts the recording at 12:00:00
and says so in an L line. The export reads back with `IgcReader`.

## Viewer

- Map: **MapLibre GL** 5 (version 6 is not supported yet by deck.gl's MapLibre integration).
  Basemaps: OpenTopoMap ("Relief", default), Esri World Imagery ("Satellite"), OpenStreetMap
  ("Plan"); relief: **AWS Terrain Tiles** (terrarium encoding). No API key. These free services ask
  for attribution (shown on the map) and moderate use: a busy site should move to its own tiles or
  a paid plan (IGN Géoplateforme is free for France only).
- Track: **deck.gl** layers interleaved with the map, coloured by vario (sink in red to strong climb
  in green). In 3D the track is at its real altitude (Condor altitudes are above sea level, like the
  terrain tiles), with lines down to the ground every 5 s; the relief hides what is behind it. From
  above, depth testing is off (everything lies at altitude 0 with the map tiles). The camera of the
  3D view looks at the glider in the air (`centerClampedToGround: false`, elevation set with
  `jumpTo()`, the only camera method that takes it).
- Task: drawn by MapLibre (dashed line and cylinders), so it lies on the relief.
- Charts: **uPlot** (altitude with thermals shaded, vario, ground speed), sharing their cursor;
  hovering or clicking a chart, the seek bar or a phase moves the glider. Every chart of the flight
  pages has a label next to the cursor with the exact values of the point (`valueTip()` of
  `assets/flight/chart.js`): add it to any new chart.
- Gliders (`assets/flight/scene.js`, shared by the three viewers, after the 3D replays of Condor
  Club and SportsTrackLive): a glider drawn with deck.gl layers (wings and tailplane as polygons,
  fuselage and fin as ribbons; no 3D model to load), turned to its heading and banked like it
  (tan(bank) = speed × turn rate / g), drawn wider than a real 15 m glider and enlarged with the
  zoom to stay about 56 px wide; a flag above it with its name and altitude in its colour; in 3D a
  line down to the ground. Session and comparison: a trail of the last 5 minutes fading out behind
  each glider, and under the trail of the pilot followed a curtain down to the ground.
- Switching to 3D picks the "Satellite" basemap unless the pilot chose one.
- Replay ×1 to ×300, "Suivre" keeps the glider in the centre; in 3D the camera comes close and
  flies behind it, turned along its track of the last minute (`chaseBearing()`: the course, not
  each circle of a thermal).
- "Vue pilote" (3D): the camera sits in the glider (`calculateCameraOptionsFromCameraLngLatAltRotation`
  of MapLibre 5), looks along its track, 15° below the horizon (fewer far tiles to load), at least
  30 m above the relief of the map (the real one, not Condor's: low over a ridge the glider can be
  under it). Bank: tan(bank) = speed × turn rate / g, shown by turning the canvas of the map (deck.gl
  follows the bearing and pitch of MapLibre, not its roll: a rolled camera drew the track off the
  relief). Heading and bank are interpolated between two seconds and smoothed during the replay;
  the replay goes ×2 when the view starts (×1, ×2 and ×3 are offered for it). The lines down to the
  ground are hidden and the track is a 4 m ribbon (a width in pixels filled the view up close).
- Link to a moment: `#t=<seconds>` opens the flight there; the link button copies it.
- Two thermals side by side: "Comparer" on two thermals of the list shows their figures (duration,
  gain, average and best 30 s climb, altitudes, radius and bank from the turn rate and the speed,
  direction, drift of the circles, i.e. the wind) and the height gained since their entry. Legs are
  compared between flights (comparison page), not within a flight.
- Public flights (`Flight::$public`, "Rendre public" for the pilot, `FLIGHT_PUBLISH`): their page
  and its data are open to visitors (`FLIGHT_VIEW`); comments, sessions, comparisons and logbooks
  still need an account. The home page lists the latest three (`FlightRepository::findPublic()`). New flights are private unless the pilot
  chose "public by default" in their privacy settings.

## Session

The flights of one network race form a session: they share the race id of the `.ftr` header, which
is also in the name of the results file of the server (see [sessions](sessions.md)). The session page
(`/sessions/{id}`) shows the results of the server and, below, what the uploaded tracks give; the
page of a flight plan (`/plans-de-vol/{code}`) gathers every track on it, whatever the day.
Flights whose task is not a flight plan of the library are still grouped by task name
(`/vols/{id}/session`).

- **Ranking**: the pilots who finished by task speed, then the others by distance.
- **Podium**, beyond speed: fastest, best average climb in thermals, best glide ratio in glides,
  least time circling (share of the flight).
- **Replay** (`FlightSession::replayData()`, partial `flight/_session_replay.html.twig`): every
  pilot in a color, the whole track faded, their glider with its flag and trail (see the viewer
  above); from above or in 3D. Two clocks: "Heure de course", each recording from
  its beginning (in a network race everybody starts at once), or "Départ du circuit ensemble", the
  start lines crossed at the same moment (ghost race between flights of different days). The side
  list gives the altitude, vario and ground speed of each pilot at the current time; clicking a
  pilot follows them (in 3D from behind their glider) and highlights their track.

## Comparison

Two flights of the same flight plan that both crossed the start line, compared along the task
rather than in time (`FlightComparison`):

- **Progress**: each point between the start and the finish has the distance of the task done (the
  legs completed, plus the part of the current leg flown towards its next point, measured between
  the centres). The time of a flight at a distance is when its progress first reaches it, every
  250 m, counted from its own start.
- **Gap** at a distance: the difference of these times (positive: the flight is behind the other).
  At the finish it is the difference of the task times.
- **Key moments**: the stretches of 10 km (a quarter of a short task) where the gap grows or shrinks
  the most, at least 30 s, three at most of each, not overlapping. For each pilot: time, time
  circling, number of thermals and their climb rate, glide speed (distance of the glides only),
  altitude in and out.
- **Thermals** of each pilot between start and finish, with the distance of the task where they
  begin.
- **Viewer**: both tracks on the map, a chart of the gap and one of both altitudes along the task;
  the slider, the charts, the key moments and the thermals move both gliders to the same distance.
  The replay moves along the task at the pace of the first pilot (both gliders always at the same
  distance), the camera behind the first one in 3D.
  The colors of the two pilots are the tokens `--color-pilot-a` and `--color-pilot-b`, read by the
  JavaScript.

## Progression

- **Advice** on the flight page (`FlightAdvisor`, from the stored analysis, so it applies to every
  flight already uploaded; the glide speed uses the polar of the glider when it is recorded), each
  tied to a step of the training path (`TrainingStep`): too much
  time circling (over 40 %) or little (under 25 %, task done); weak thermals taken (a minute or more
  under 1 m/s or under half the average climb); circling without gain (three or more under 50 m);
  always turning the same way (85 %, four thermals or more); slow glides (20 km/h under the
  MacCready speed of a 15 m glider, about 95 km/h + 20 km/h per m/s of climb); marks of the Silver
  badge (1 h, 1,000 m gained from a low point, 50 km from the take-off). No advice on flying too fast:
  straight flight includes ridge running, and ballasted gliders fly much faster. Kinds: something
  to work on, something done well, or where the flight stands (badge marks reached in part).
- **Logbook** (`/mon-compte/carnet`, in the member space; `/vols/carnet` redirects there): the pilot's
  flights from the oldest, totals, the advice that comes back most, and one chart per value (task
  speed, average climb, glide ratio, time circling) with the date, flight plan and value of each
  flight under the cursor. Seen by the pilot and by trainers (`/vols/carnet/{user}`).
- **Comments** (`FlightComment`): by the pilot of the flight or a trainer (`FLIGHT_REVIEW`), on the
  whole flight or on the moment shown by the viewer (the form takes it from the map, the charts or
  the slider), about a step of the training path or not. Every member who sees the flight reads
  them; clicking the moment of a comment moves the glider there. Their author (or an administrator)
  deletes them. The pilot gets an e-mail for each new comment by someone else, unless they turned it
  off in their profile (`FlightMailer`).

## Training path

Each pilot's logbook starts with the **training path** (roadmap 9g, `App\Training\TrainingPath`,
partial `flight/_training_path.html.twig`): the eight steps of `TrainingStep`, each not started, in
progress or validated (`StepProgress`: status, trainer's note, reference flight of the pilot,
validation date, who marked it), the internal qualification of the club reached (steps 1 to 4:
Élève ePilote, ePilote Solo, ePilote, ePilote breveté Condor Club) and the current step.

- A trainer (`FLIGHT_REVIEW`) marks the steps from the logbook of a pilot (`/vols/carnet/{id}`,
  `TrainingController`, POST `/vols/carnet/{id}/parcours/{step}`); "Pas commencée" removes the
  progress. Nobody marks their own steps, except an administrator (`TRAINING_EDIT` of
  `FlightVoter`).
- The pilot reads them in their logbook (`/mon-compte/carnet`) and on their dashboard.
- The progress is in the export of the personal data and goes with the account.

The committee has not validated this follow-up yet (it replaces the trainers' Excel workbooks, see
[training](project/training.md#progression-tracking)): no default group holds `FLIGHT_REVIEW`.

## Club life

Kept modest, so that the site stays a training tool rather than a game; everything is computed from
the uploaded flights, nothing is stored.

- **Records** (`/vols/records`, logged-in users): per flight plan the best task speed (with its
  pilot, the number of flights and a link to the session); per landscape the longest distance flown,
  the longest flight and the highest altitude.
- **Season ranking** (same page, `?saison=`): pilots (accounts that did not opt out in their privacy
  settings) of a calendar year by kilometres of
  finished tasks, with their tasks, flights, hours and best speed. The date of a flight is the one
  of its file, or of its upload when the file has none (`.ftr`).
- **Badges** (logbook): first task finished, 100, 300 and 500 km flown in one flight, 3 h and 5 h
  flights, 1,000 m gained from a low point, the three Silver badge marks in one flight; each with
  the first flight that earned it (`Badge::earned()`, from `FlightMarks`).: comparison with another flight, progression (advice, logbook,
instructor comments), viewer extras, club life.
