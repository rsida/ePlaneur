# Flight viewer

Roadmap step 7: Condor Club-like flight analysis, focused on training. Logged-in users upload a
Condor flight track at `/vols`; any logged-in user sees the flights of the club on a map, in 3D at
their real altitude over the relief, with their charts, phases, task legs and a replay.

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
| Progression | `App\Flight\Advice\FlightAdvisor` (advice), `App\Training\TrainingStep`, `FlightComment` (comments), `/vols/carnet` and `/vols/carnet/{user}` (logbook, `logbook_chart_controller.js`), `FlightVoter` (`FLIGHT_COMMENT`, `FLIGHT_COMMENT_DELETE`, `LOGBOOK_VIEW`) |

## Files

### Condor 3 track (`.ftr`)

The file Condor saves in `Documents\Condor3\FlightTracks` and that pilots upload to Condor Club. It is
not documented; what follows was measured on Condor 3.1 files (2026-10-08):

| Offset | Content |
|---|---|
| 0 | `FTR`, then the Condor version (int at 3: 3100) |
| 0x9b, 0xac, 0xbd, 0xce, 0xd6, 0xdf | Pascal short strings: first name, last name, country, competition id, registration, landscape |
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
jump would read as a 5,000 m/s climb, so `TrackReader` cuts the track where the speed between two
fixes is beyond any glider (150 m/s horizontally, 40 m/s vertically) and keeps the longest part.

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
- Replay ×10 to ×300, "Suivre" keeps the glider in the centre.

## Session

All the flights on the same flight plan (the name of the Condor task file, e.g.
"C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur") form a session, the way the club names its
network flights. The race id of the `.ftr` header is not used yet: whether two pilots of the same
network race get the same id has not been checked (no tracks of two pilots of one race yet). A flight
plan flown again another day joins the same session, which still compares the same task.

- **Ranking**: the pilots who finished by task speed, then the others by distance.
- **Podium**, beyond speed: fastest, best average climb in thermals, best glide ratio in glides,
  least time circling (share of the flight).
- **Replay**: every pilot in a color, the whole track faded and the last two minutes bold, a label
  with the competition id; from above or in 3D. Two clocks: "Heure de course", each recording from
  its beginning (in a network race everybody starts at once), or "Départ du circuit ensemble", the
  start lines crossed at the same moment (ghost race between flights of different days). The side
  list gives the altitude and vario of each pilot at the current time; clicking a pilot centres the
  map on them, highlights their track and follows them during the replay.

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
  The colors of the two pilots are the tokens `--color-pilot-a` and `--color-pilot-b`, read by the
  JavaScript.

## Progression

- **Advice** on the flight page (`FlightAdvisor`, from the stored analysis, so it applies to every
  flight already uploaded), each tied to a step of the training path (`TrainingStep`): too much
  time circling (over 40 %) or little (under 25 %, task done); weak thermals taken (a minute or more
  under 1 m/s or under half the average climb); circling without gain (three or more under 50 m);
  always turning the same way (85 %, four thermals or more); slow glides (20 km/h under the
  MacCready speed of a 15 m glider, about 95 km/h + 20 km/h per m/s of climb); marks of the Silver
  badge (1 h, 1,000 m gained from a low point, 50 km from the take-off). No advice on flying too fast:
  straight flight includes ridge running, and ballasted gliders fly much faster. Kinds: something
  to work on, something done well, or where the flight stands (badge marks reached in part).
- **Logbook** (`/vols/carnet`, linked from the flight list and from each flight): the pilot's
  flights from the oldest, totals, the advice that comes back most, and one chart per value (task
  speed, average climb, glide ratio, time circling) with the date, flight plan and value of each
  flight under the cursor. Seen by the pilot and by trainers (`/vols/carnet/{user}`).
- **Comments** (`FlightComment`): by the pilot of the flight or a trainer (`FLIGHT_REVIEW`), on the
  whole flight or on the moment shown by the viewer (the form takes it from the map, the charts or
  the slider), about a step of the training path or not. Every member who sees the flight reads
  them; clicking the moment of a comment moves the glider there. Their author (or an administrator)
  deletes them.

## Next

See the roadmap, steps 7e and 7f: comparison with another flight, progression (advice, logbook,
instructor comments), viewer extras, club life.
