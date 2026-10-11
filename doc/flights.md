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
| Storage | `Flight` entity (columns, statistics and task distance for the lists), its full analysis in `FlightData` (own table, loaded only when read: `Flight::getAnalysis()`); files in `app.flights_dir` (`var/flights`, Docker volume `flights` in production) through `FlightImporter` |
| Airfields | `Airfield` entity, `app:condor:airfields` (`CondorAirfields` reads the `.apt` file of a landscape), `GlideReach` (glide cone) |
| Relief | `App\Flight\Terrain\Terrain` (AWS Terrain Tiles kept in `app.terrain_dir`, `var/terrain`), `FlightTerrain` (height of the ground, slopes, wave) |
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
| 0x54b3 | Records of 64 bytes: time (+0, see below), x, y, altitude (floats at +4, +8, +12), a float at +16, the attitude as a quaternion (floats x, y, z, w at +20, see below), and bytes that look encrypted |

The time of each record is a float in units of 32 hours (115,200 s) whose bits lack 0x500000:
`unpack('g', pack('V', bits + 0x500000)) × 115,200` grows linearly with the time. Found on
2026-10-08 against the IGC export of the same flight (C3-c060): every IGC fix lies within 1.5 m of
the record of its time, and the track lasts 5,911 s in both. Records are 0.06 to 1.2 s apart (about
0.1 s, more spaced when the simulator is busy), so an even interval is wrong by up to 2 %. A file
whose times do not decode to growing values falls back on an even interval of 0.104 s
(`FtrReader::RECORD_INTERVAL`), and the viewer then says durations and speeds are estimated.

Task times checked against the Condor results: s0109 4,524 s (Condor 4,527), LastTrack 4,216 s
(4,219), c060 4,846 s (4,909: the IGC export gives the same 4,846 s; the 63 s may be a penalty).

The attitude (found on 2026-10-10 on C3-s117 and the C3-c046a race): the quaternion turns the world
(x east, y north, z up) into the glider, so its conjugate turns the nose (body x) and the right wing
(body y) into the world; it is not always of length 1 (up to 2 % off) and is normalised. With it,
the heading of the nose follows the track within 2.4° (median), the pitch stays within a few
degrees while circling and the bank reads 16° to 45° in the thermals. `FtrReader::attitude()` gives
the bank (minus the height of the right wing: positive banked right; read on the wrong side from
2026-10-10 to 2026-10-11, now checked against the turns of the track by `FtrReaderTest`) and the
pitch (height of the nose) of each record; the analysis keeps them for each second (`series.bank`, `series.pitch`) when every
second has them, and the viewers draw the gliders with them (else the bank of a steady turn).

Condor 2 tracks are not read (their format differs): pilots export an IGC.

### IGC

The FAI flight recorder format, exported by Condor ("Analyse du vol" > "Export IGC") or by a real
logger. Condor writes a fix about every second, sometimes two in the same second (the second one is
put half a second later), the task in C records (take-off, start, turnpoints, finish, landing: the
landing is left out) and the zones in `LSEEYOU OZ=` lines (R1 radius, A1 half angle). B records
give the fixes (UTC time, GNSS altitude, pressure altitude when it is missing), H records the
pilot, glider, competition id and date. Times are exact.

Condor's export also has `LCON` lines (`App\Flight\Condor\CondorReport`, kept in the analysis as
`condor`): the whole flight plan (`LCONFPL` + each line of the `.fpl`), the official result
(`LCONFlightInfo…`: race id, start time and gate opening as clock times, flight and task times,
status, distance, speed, total penalty) and the points of each penalty (`LCONPenalties<rule>`, only
the rules that cost points). The race id joins the IGC to its session like a `.ftr`; the landscape,
water ballast and flight plan name come from the flight plan. The flight page shows Condor's result
when the session has no results, the exact penalties, and the settings of the task from that flight
plan when the flight has no session. See [condor-integration](condor-integration.md).

### Discontinuities

Condor moves the glider at once in some cases. A move between two fixes beyond any glider (over
150 m/s horizontally or 80 m/s vertically, and over 30 m) is a jump; a hard pull-up climbs 4 m in
the 0.1 s between two Condor records (40 to 50 m/s) and is not one.

- **Height recovery** (roadmap 7m1): the pilot asks for height, Condor puts the glider about 500 m
  higher at the same place (a jump upwards, not sideways), and the server counts the penalty of the
  rule (200 points on the club's races). The track is kept whole and the recovery recorded
  (`Track::$recoveries`, `recoveries` of the analysis: second and height). The analysis runs the
  climbs (vario, phases, best gain, total climb, spirals, climb by altitude, lift crossed) on the
  altitude without the recoveries, and keeps the real altitude for the rest (highest and lowest,
  band of the thermals, start and finish, height above the ground). The flight page lists them
  under "Le vol en détail", and "Score et pénalités" counts the points of each. Found on the IGC
  exports of 2026-10-11: +501 m in the middle of C3-c058 (the track was cut there before and the
  task lost), +495 m and +498 m as the glider crossed the finish of two races, which the reader took
  for an automatic lift at the finish.
- **Any other jump** (a glider recovery moves it to an airfield): `TrackReader` cuts the track there
  and keeps the longest part, so the task may read as not finished; the official result of the
  session says what the server counted.

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
     its last one; FDU: 10 km/h from 262°, Condor Club 10 km/h from 246°); from two full turns, its
     **centring** (roadmap 7h5): the average vario in each eighth of the circles, by the bearing of
     the glider from the centre of its circle (the average position over one circle around that
     second, which follows the drift), an eighth crossed for less than 3 s left out; and its
     **entry and exit** (roadmap 7i1), from the climb over each circle: the seconds before the
     first circle climbing 70 % of the best one (centring), and the seconds after the last circle
     climbing the average climb of the flight (staying while it weakens);
   - **lift crossed** in the glides without turning (roadmap 7i1, `crossings`): the total energy
     vario over 10 s (height plus the height the speed is worth, so a pull-up is not lift) at
     1 m/s or more for 5 s, away from the 20 s after a thermal and the 30 s before the next one,
     with its best value and the height gained. In the Condor races of 2026-10 the good pilots
     gain 1,000 to 3,300 m that way (dolphin flight);
   - straight flight that climbs (1 m/s over 30 s, for a minute and 50 m) is its own phase, slope
     or wave lift (FDU: 1, +299 m; Condor Club 1, +269 m). A height recovery of Condor ("miracle")
     reads as one too. Before the release it is the tow or winch climb, a "launch" phase
     (2026-10-10: the aerotow of C3-s117 read as 1,504 m of slope lift);
   - on the task (phases cut at the start and the finish): thermals, share of the time circling,
     average climb, left / right, glides (distance, speed, glide ratio: FDU 225 km at 206 km/h, 59.1;
     Condor Club 216 km at 208 km/h, 58.4), wind averaged as vectors, slope or wave lift;
   - the spiral: average radius and bank, and the radius of the best quarter of the thermals by
     climb (four thermals at least), the "target";
   - the climb by altitude: average climb in the thermals per band of 200 m (20 s at least).
   Netto, effective MacCready and wing loading come from the polar of the glider (see Gliders and
   polars).
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

## Relief

Roadmap 7i3. `FlightImporter` adds the relief to the analysis once the session of the flight is
known (on upload and with `app:flight:analyse`), through `FlightTerrain`:

- **Ground**: the elevation under each second (`series.ground`), from the AWS Terrain Tiles of zoom
  11 (about 55 m a pixel), the relief of the 3D view, interpolated between four pixels. The real
  relief, which Condor's is built from: C3-s117 takes off at 180.6 m over a ground read at 179 m.
  Tiles are downloaded once (`ClubEplaneur/1.0` user agent) into `app.terrain_dir` (`var/terrain`,
  about 12 MB for the 17 local flights). Not a volume in production: a cache, downloaded again
  when needed. Without tiles (no network; `app.terrain_tiles` is empty in the tests) the
  elevations are null and the rest is left out.
- **Slopes expected to work**: on a grid of 200 m within 3 km of the track, the faces looking into
  the wind (downhill towards where it comes from) where it would rise at 1 m/s or more: wind ×
  sin(slope) × cos(angle between the face and the wind). The wind is the one of the task (base
  weather zone of the session's settings); without it the average drift of the thermals, often too
  weak (4 km/h on C3-s117 for a task wind of 20 km/h from the south: then no slope is expected
  under 10 km/h). On C3-s117 its slope lift phases were 29 m and 144 m from a cell expected to
  work. A model: Condor's own lift also depends on the shape of the ridge and the height.
- **Wave**: straight lift ("lift" phases) 600 m or more above the ground on average and farther
  than 500 m from any slope expected to work is wave (`phase.wave`), the rest slope lift; null
  without the ground.

On the flight page: the slopes as orange squares lying on the relief, more opaque where the lift
should be stronger (button "Pentes"); wave in purple in 3D and on the timeline, "Onde" / "Pente" in
the lists; a chart of the height above the ground with dashed lines at 300 m (plan where to land)
and 150 m; "Trop bas" under the timeline for 10 s or more under 150 m, from the release to 3 min
before the landing, farther than 300 m from a slope expected to work (ridge running is flown that
low); and with a task, the glide ratio required to reach the finish (distance left through the
turnpoints not reached over the height above the finish point, "hors de portée" from 100), as a
chart drawn only under 100 (a long task not finished stays out of reach all along: on the 201 km
of flight 4, 150 to 280; then a sentence says so instead of the chart) with the average glide ratio
of the flight dashed, and beside the map with the height above
the ground.

**Glide cone** (done 2026-10-10): the airfields of the landscape (`Airfield`, imported from its
`.apt` file by `app:condor:airfields`, see `CondorAirfields` for the format; 304 on AA3) within
about 40 km of the track come with the data of the flight (`GlideReach`), with a safe glide ratio:
70 % of the best glide ratio of the polar, 40 at most, 25 without the polar, and an arrival 200 m
above the airfield. The viewer shows them (button "Terrains"): green when within reach at the
moment shown, with their names, grey otherwise, a green ring of the reach over the ground around
the glider ((height above the ground − 200 m) × the ratio), the nearest airfield within reach
beside the map ("aucun" in red), and on the timeline the stretches of 30 s or more with none. In
straight lines: the relief in between and the wind are not counted.

The address of the data of a flight carries `FlightController::DATA_VERSION` next to the version of
its analysis: raise it when `analysis()` adds something, or browsers keep the old data an hour.

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
- **Netto and effective MacCready**: the netto of a glide is the sink of the polar at its airspeed
  minus the sink measured, the vertical speed of the air crossed (positive: rising air); its
  effective MacCready is the climb rate (by tenths, 0 to 6 m/s) whose speed to fly is its airspeed
  (`Polar::macCreadyFor()`). Both are averaged over the glides by distance on the flight page and
  shown for each glide on the polar chart. Second by second, the netto vario of the glides (vario
  plus the sink of the polar at the airspeed of that second, heading over 5 s, wind taken away)
  is added to the analysis served to the viewer (`series.netto`, null outside glides: the polar does
  not hold while circling) and drawn dashed under the vario.
- **Advice**: the speed of the glides against that MacCready speed (see Progression).

## Downloads

Logged-in users can download, from the page of a flight, its original file (`/vols/{id}/fichier`,
`.ftr` or `.igc`, kept unless the flight was anonymised) and an IGC export of the analysed track
(`/vols/{id}/igc`, `IgcExporter`): one B record per second, the task as C records with its zones
as LSEEYOU lines (as Condor writes them), the pilot, competition id and glider as H records, ASCII
only. The time of day of a `.ftr` track is not known: the export starts the recording at 12:00:00
and says so in an L line. The export reads back with `IgcReader`.

## Viewer

Layout of the flight page (roadmap 7j1, from the Figma pass of 2026-10-10): key figures, then
"Quelques repères" (the three pieces of advice of `FlightAdvisor::highlights()`: something to work
on first, something done well; each advice knows the moment and the phase that show it,
`Advice::$at` and `$phase`, a click moves the replay there), then the viewer, then the detail in
tabs (`tabs` controller: a link to an element of a panel opens it; the comment form with errors
opens its tab):

- Map toolbar: `Carte · 3D · Suivre · Instruments · Affichage`; "Instruments" shows or hides the
  instruments over the map (shown by default, the choice kept in the browser). "Affichage" is a
  panel over the map with how the map is seen: basemap, colour of the track, ghost, camera and
  pilot view. It closes outside it and with Escape.
- What the map draws: a layers button under the zoom of the map (roadmap 7j5) opens a list of
  check boxes, each kept in the browser (`flight.layers-off`): own thermals, slope and wave lift,
  lift crossed, strong sink, windsocks, curtain and shadow of the track (these in 3D only), thermals
  of the other pilots and the ones missed (when the race has other tracks), task and turnpoints,
  expected slopes, airfields and glide reach, comments. Rows without data for the flight are left
  out. The same panel (`templates/flight/_layers.html.twig`) is on the session replay.
- Beside the map, the phase at the cursor ("Phase en cours · Thermique n° 4") with the advice it
  illustrates as a title, then the values (vario in green or red, height in orange under 300 m,
  red under 150 m), the legs, the centring radar and the list of phases.
- Charts: one at a time or all ("Toutes les courbes"), the choice kept in the browser
  (`localStorage`, nothing breaks without it); the key above the map is open on the first visit.
- On a phone: altitude, vario and height on the map, the replay bar stuck to the bottom of the
  screen.
- "Débrief guidé" in the replay bar (roadmap 7j4, track B of the Figma pass): the flight told in
  chapters in place of the current phase beside the map (`FlightDebrief::chapters()`): the launch
  and the start, each leg of the task (distance, time and speed, thermals and their climb, share
  of time circling, lowest height above the ground), the final glide, then the review (task time,
  arrival height). Each piece of advice goes to the chapter of its moment; advice without a moment
  goes to the review. A final glide begun before the last leg (often, in a race in the mountains)
  is not a chapter: the leg where it begins says so and the review gives the arrival. Without a
  task: launch, the flight, the review. "Précédent / Suivant" frame the chapter on the map and put
  the cursor at its start; the replay stops at the end of the chapter; moving the cursor elsewhere
  shows the chapter there.

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
  Club and SportsTrackLive): from above a flat glider (tapered wings with red tips, fuselage,
  canopy, tailplane, an outline and a light shadow), in 3D a lit model in volume
  (`assets/flight/glider-mesh.js`: fuselage, canopy, tapered wings with 4° of dihedral and red
  tips, fin and T-tail, a few hundred triangles built in code and drawn once per glider by
  `SimpleMeshLayer`, no file to load), turned to its heading, banked and pitched like it
  (recorded by Condor 3, else tan(bank) = speed × turn rate / g), drawn wider than a real 15 m
  glider and enlarged with the zoom to stay about 56 px wide (1.6 times more in 3D); a flag above it with its name and altitude in its colour; in 3D a
  line down to the ground. Session and comparison: a trail of the last 5 minutes fading out behind
  each glider, and under the trail of the pilot followed a curtain down to the ground.
- Readings in the flag, as on Condor Club (`readings()`): for the glider of the flight page, the
  pilot followed in a session and both pilots of a comparison, ground speed, vario, average climb
  of the last 30 s ("ø"), altitude and, in 3D, the height above the relief of the map (the real
  relief, not Condor's: a guide only).
- 3D scene: the cylinders of the task stand up from the ground to 300 m above the highest glider,
  translucent, with the line of the task at that height (`taskLayers()`); a sky with haze at the
  horizon (MapLibre `sky`). Translucent volumes do not write the depth buffer, or the tracks drawn
  after them would disappear behind their walls.
- Lift in 3D (roadmap 7h1): the thermals climbed so far are thin cylinders at the average position
  of their circles, from the entry to the altitude reached, darker with the climb (under 1, 1 to 2,
  2 to 3, over 3 m/s), the one being climbed growing with the glider (`thermalLayers()`; the colour
  of the pilot in a comparison; the thermals come with the replay data,
  `FlightSession::thermalsOf()`). Arrows for the thermals were tried on 2026-10-10 and dropped at
  the user's request: the cylinders read the centring better. Other lift uses arrows
  (`arrowLayer()`: a line and a head of four barbs in two planes, readable from any side, thinner
  when zoomed out). The slope or wave lift phases get an orange band over the track and orange arrows every 400 m coming
  up to it from 150 m below on the side of the wind of the flight (`liftLayers()`). Roadmap 7i2:
  lift crossed without turning gets a lighter arrow rising to the track as long as the height
  gained (`crossingLayers()`), strong sink a blue arrow going down 150 m (`sinkLayers()`), and each
  thermal left a white windsock above its top pointing downwind, longer with its wind, with the
  speed when close (`windLayers()`). The shadow of the track lies on the relief (a MapLibre line,
  draped, `addShadow()`).
- Lift of the race (roadmap 7i7, `SessionLift`, in `analyse.json` as `sessionLift`): Condor does
  not record its thermals, but the pilots of one network race (same network session, or else same
  Condor race id; not the same flight plan another day) fly in the same air, so where one of them
  climbed there was a thermal. Their thermals, grouped within 600 m, are wide translucent cylinders
  (150 m radius) from the lowest entry to the highest exit, their lift crossed thin ones; in 2D,
  discs. A thermal is **missed** when the flight passed within 500 m of its centre, at an altitude
  where it worked (from 200 m under its entry to its top), climbing 1 m/s or more, without circling
  in it nor crossing lift within 30 s (dolphin flight through it counts as used): yellow, labelled
  from zoom 9 ("Manqué à 129 m · +2,1 m/s · HL4, JC6"), and a yellow triangle above the timeline at
  the closest moment. "Course : thermiques des autres / manqués / masquée" chooses what shows. The
  recordings do not share a clock: a thermal may have died, or not be born yet, when the flight
  passed. Advice when two or more missed thermals climbed at least the average of the flight.
- Curtain (roadmap 7h2): in 3D a line down to the ground every 2 s, in the colour of the track, so
  the curtain reads altitude and height above the relief. The track (and the curtain) can be
  coloured by vario, altitude or, with the polar of the glider, netto (grey out of the glides).
- Timeline (roadmap 7h3): above the seek bar a tick at each turnpoint reached (start, points,
  finish); under it each thermal as a band coloured by its climb, the slope lift in orange, the
  final glide as a line, and the low points (the lowest of a glide ending in a thermal, 250 m or
  more under the average entry of the thermals) as red triangles. Roadmap 7i1/7i2: the centring
  and the weak top of a thermal (20 s or more) are hatched over its band, lift crossed is a short
  tick at the top in the colour of its climb, strong sink a blue tick at the bottom: the total
  energy netto over 10 s under −2 m/s in a glide, or the total energy vario under −3 m/s without
  the polar (`strongSink()`). A click moves the replay there;
  hovering gives the values. The list of phases gives the same moments to the keyboard.
- Centring radar (roadmap 7h5), beside the map: for the thermal at the moment shown (or the last
  one before it), the eight sides of its circles as wedges, longer and darker the better they
  climbed, north up; hovering a side gives its vario, and a sentence says which way to shift the
  circles (or that the thermal was centred: less than 0.5 m/s between the best and the worst
  side). Under it, how long the thermal took to centre and how long the glider stayed at its weak
  top; the phase table and the comparison of two thermals give the same figures.
- Above the map, a tinted bar "Légende" (closed by default, opens on a click) draws and explains
  every symbol of the 3D map and of the timeline. Its colours are tokens (`--color-climb-1` to `-4`,
  `--color-slope-arrow`, `--color-windsock`...) that repeat the values of `scene.js`: change both.
- Replay comfort (roadmap 7i5): "Spirales × 5" plays the thermals five times faster; while
  following in 3D, "Caméra : derrière" (the chase camera), "orbite" (turning around the glider at
  8° a second) or "cinéma" (a new shot every 12 s: behind, side, high, front, other side, the
  camera easing to it); "Instruments" over the map: artificial horizon (the bank and pitch
  recorded, else the bank of a steady turn), vario needle with the average of the last 30 s in
  green, ground speed and, in the glides with the polar, whether to slow down or speed up for the
  MacCready speed of the climb met (within 20 km/h: "✓"). The pilot view banks with the recorded
  bank too.
- Comments at a moment (roadmap 7i6): a blue bubble under the timeline (hover to read, click to
  go there) and a label "Commentaire" on the track, with the author from zoom 11 (no emoji: the
  font of the map has none). Only for logged-in users, who see the comments.
- Switching to 3D picks the "Satellite" basemap unless the pilot chose one.
- Replay ×1 to ×300, "Suivre" keeps the glider in the centre; in 3D the camera comes close and
  flies behind it (on the glider between two seconds, as it is drawn: on whole seconds the camera
  jumped every second), turned along its track of the last minute (`chaseBearing()`: the course, not
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
- **Story of the race** (`SessionComparison::story()`, roadmap 7j3): as with every pilot starting
  together, the leader at each 250 m of the task is the one who got there in the shortest time
  since their start; leads held for less than 5 km join the run before them. The page says who led
  where and the lead at the finish; each change of leader is a chapter that sets the replay to
  "Départ du circuit ensemble" at the leader's time and follows them.
- **Comparer toute la session**: the name of each pilot at the end of their line, "Isoler" puts one
  pilot forward on both charts (the others fade).

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
  distance), the camera behind the first one in 3D. Between two steps of 250 m the gliders glide
  (fractional index between the seconds of the two steps): moving step by step looked like two
  frames a second.
  The colors of the two pilots are the tokens `--color-pilot-a` and `--color-pilot-b`, read by the
  JavaScript.

- **Waterfall** (roadmap 7i4, `FlightComparison::waterfall()`): leg by leg, completed by both, the
  gap before and after as a floating bar (red lost, green gained), split into the time circling and
  the time flying straight, with the thermals, climb and glide speed of each pilot on the leg.
  An axis in minutes under the bars; the name of a leg shows its stretch on the map (each row
  knows where its leg lies along the task, `start` and `end`).
- **Headline** (roadmap 7j2): who wins and by how much, then the leg of the waterfall that made
  most of the gap, said "surtout en ligne droite" or "surtout en spirale" when two thirds of it
  come from there. The table of figures colours each gap for the first pilot and says it in words
  ("mieux", "moins bien"; neither for the number of thermals and the distance).

Roadmap 7i4 on the flight page:

- **Ghost** (`Ghost`, in `analyse.json`): the best other pilot of the same race (`findSameAir()`;
  the second when the flight won) replayed from the same start (both cross the start line
  together), with a trail of 90 s, in 2D, 3D and the pilot view, and the live gap along the task
  (`FlightComparison` at each 250 m) on its flag and beside the map. Menu "Fantôme": the best of
  the race, or the pilot's own best flight on the same flight plan another day (`Ghost::best()`:
  the fastest task finished, not the same race uploaded twice), or none. Another flight of the same
  pilot in the same race is the same track uploaded twice: left out of the race
  (`findSameAir()`).
- **Track by speed against MacCready** ("Trace : vitesse / MacCready", with the polar): each
  glide measured by `GliderPerformance` (60 s and 2 km at least) in blue when its estimated
  airspeed is 20 km/h or more under the MacCready speed of the average climb of the flight, orange
  30 km/h or more over it (the thresholds of the advice), green between; the rest in grey.

## Lift of the club

Roadmap 7i6. `/vols/ascendances` (logged in; linked from the flight list and from each flight,
"Ascendances du club sur <landscape>") maps, landscape by landscape (`?paysage=`, the most flown
by default), every thermal and every slope lift phase of the club flights on it (`ClubLift`, data
at `/vols/ascendances/{landscape}.json`):

- thermals: from afar a heat map weighted by their climb (MapLibre `heatmap`); from zoom 11 the
  thermals themselves, those closer than 700 m as one point, sized by the number of climbs and
  coloured by their average climb, with the altitudes on hover;
- slopes ("lift" phases that are not wave): orange lines, thicker with the climb, filtered by the
  direction of the wind of their flight (eight sectors): which slopes work in which wind. The wind
  is the task's (base weather zone of the session), else the one the relief was read with, else
  the drift of the thermals.

Computed from the stored analyses at each request (cached 10 min by the browser): fine for the
hundreds of flights of the club; a stored aggregate will be needed beyond.

## Progression

- **Advice** on the flight page (`FlightAdvisor`, from the stored analysis, so it applies to every
  flight already uploaded; the glide speed uses the polar of the glider when it is recorded), each
  tied to a step of the training path (`TrainingStep`): too much
  time circling (over 40 %) or little (under 25 %, task done); weak thermals taken (a minute or more
  under 1 m/s or under half the average climb); circling without gain (three or more under 50 m);
  always turning the same way (85 %, four thermals or more); slow glides (20 km/h under the
  MacCready speed of a 15 m glider, about 95 km/h + 20 km/h per m/s of climb); marks of the Silver
  badge (1 h, 1,000 m gained from a low point, 50 km from the take-off); roadmap 7i1: slow centring
  (45 s or more on average before the first good circle, three thermals or more; 20 s or less is
  good), leaving late (2 min or more in all at the weak tops), dolphin flight (200 m or more gained
  in lift crossed) and lift left for a weaker thermal (twice or more, lift crossed at twice the
  climb of the next thermal of a minute or more). No advice on flying too fast:
  straight flight includes ridge running, and ballasted gliders fly much faster. Kinds: something
  to work on, something done well, or where the flight stands (badge marks reached in part).
- **Skills** (roadmap 7i6, in the logbook): centring (seconds before the first good circle), climb
  in thermals, time circling, leaving the thermals (seconds an hour at their weak tops), dolphin
  flight (metres an hour gained without circling) and glide ratio, each tied to its step, averaged
  on the last 3 flights that measure it against the 3 before, with the trend (better, worse,
  steady within 5 %).
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
