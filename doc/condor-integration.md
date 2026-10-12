# Condor 3 integration study

Study of 2026-10-11, asked by the user: what Condor 3 offers that the club site could use to generate
training flight plans, start Condor from the site, show a live helper during a flight and read
exact figures like Condor Club does. Work in progress: the experiments still to run are at the end.

## Rules of the study

- Only what Condor documents or writes in plain files: its manual (Condor 3 Manual 1.03, for
  Condor 3.0.9), its settings files, the files it saves (flight plans, IGC exports, race results,
  logs) and what Windows declares for it (registry). Observed on the Condor 3.1 install of the user
  (`C:\Condor3`, `Documents\Condor3`).
- No disassembly or patching of `Condor.exe`, and no attempt at the encrypted parts of the `.ftr`
  files (24 bytes of each record, the task settings of the header): they are most likely there so
  that tracks can be trusted in competitions, and the EULA probably forbids it. Everything below
  goes without them.

## What Condor gives

| Source | Where | What | Status |
|---|---|---|---|
| Flight track `.ftr` | `Documents\Condor3\FlightTracks` | Position, altitude, attitude about every 0.1 s; the rest encrypted | Read since 7a ([flights](flights.md)) |
| IGC export | Flight analysis > "IGC export", into `FlightTracks\IGC` | 1 s fixes at the time of day of the flight, the whole flight plan (`LCONFPL` lines), the official figures of the flight (`LCONFlightInfo…`), each penalty by cause (`LCONPenalties…`), a security record (G) checked by `VALI_CON.exe` | Fixes and task read since 7a; the `LCON` lines since 7l1 (`CondorReport`) |
| Race results | `Documents\Condor3\RaceResults`, written after each network race | `Race <id> on <date>.csv` (rank, status, CN, distance, time, speed, penalty, score) and the `.fpl` of the race | Read when uploaded for a session (7b, 9f) |
| Live race state | `Condor3\Logs\Spectate.json` | For every pilot of the race: CN, name, country, glider, position, altitude, speed, heading, vario, status, rank, score, penalty, average speed, distance, time | Not used |
| UDP output | `Condor3\Settings\UDP.ini` (off by default) | A packet every `SendIntervalMs` on 127.0.0.1:55278, see below | Not used |
| NMEA output | Setup > Options > NMEA output (COM port) | Standard NMEA for flight computers (XCSoar, LK8000) | Not used |
| Simkits | `Condor3\Settings\Simkits.ini` | Hardware instruments (airspeed, altimeter, vario, compass) | Not relevant |
| URL protocol | Registry `HKCR\cndr3` → `C:\Condor3\Condor.exe %1` | Links `cndr3:<code>` start Condor; the server list of condorsoaring.com uses them for "Join" (`cndr3:LAMCDFMFABMCFDSRDBAA`, one code per server) | Not used |
| Dedicated server | `CondorDedicated.exe` | Cycles a list of flight plans (`.sfl`), admin password | Used by the club outside the site |
| Flight plans | `.fpl`, INI | Every key listed below | Read since 7g4 |

### Why Condor Club is exact

Condor computes the official figures itself while flying, from the full state of the simulation,
and writes them into its own files: the IGC export carries them in plain text, and the `.ftr` very
likely in its encrypted part. Condor Club is close to the publisher of Condor (the official Condor
updater is signed "Condor Club") and reads its tracks with the publisher's knowledge of the format.
Its IGC download is probably Condor's own export. So the gap does not come from the measure:
it comes from what we can read.

An IGC exported by Condor (C3-c060, flight of the user) has, besides the fixes:

```
LCONFlightInfoRaceID=1930306707
LCONFlightInfoStartTime=14:45:00
LCONFlightInfoFlightTime=01:46:51
LCONFlightInfoTaskStart=15:05:00
LCONFlightInfoTaskTime=01:21:49
LCONFlightInfoPlayerStatus=Finished
LCONFlightInfoDistanceFlown=182.83km
LCONFlightInfoAverageSpeed=134.06km/h
LCONFlightInfoPenaltyPoints=0.2
LCONFlightInfoGhostCount=0
LCONPenaltiesStartSpeed=0.2
```

This settles several questions at once: the official task time of C3-c060 (01:21:49, counted from
the opening of the regatta gate at 15:05, the 63 s of the open question of the roadmap), the cause
of each penalty, the time of day (B records are at the clock of the task: the first fix is
14:53:20) and the settings of the task (the whole `.fpl`), without the session needing its own
`.fpl`.

### The start speed, measured

`MaxStartGroundSpeed` is the name of the rule in the `.fpl`: Condor penalises the ground speed, not
the airspeed. On C3-c060 Condor counts 0.2 point at 2 points per km/h, i.e. 170.1 km/h for a limit
of 170. From the raw records of the `.ftr` around the exact crossing of the start line: 168.8 km/h
over 1 s, 170.6 over 2 s, 171.8 over 4 s (the analysis uses 4 s). The times of the records are only
precise to about 0.03 s, so the speed between two records ranges from 151 to 208 km/h: without the
encrypted speed, the estimate stays within a few km/h, and a few points. The IGC export gives the
exact figure.

### UDP packet data (manual, chapter 14.1)

Text packets `name=value`, one per line. Always: `time` (decimal hours of the game), `altitude`
(m), `vario`, `evario`, `nettovario`, `integrator` (m/s), `compass` (°), `slipball` (rad),
`turnrate` (rad/s), `yawstringangle`, `yaw`, `pitch`, `bank` (rad), `quaternionx/y/z/w`, `vx`,
`vy`, `vz` (speed vector, m/s), `rollrate`, `pitchrate`, `yawrate` (rad/s), `gforce`. With
`ExtendedData=1`: `height` (of the centre of gravity above the ground), `wheelheight`,
`turbulencestrength`, `surfaceroughness`, `hudmessages` (the messages shown on screen, separated by
`;`). With `ExtendedData1=1`: `flaps`, `MC`, `water` (kg). Other sources (Condor 2, x-sim) also
list `airspeed`: to check on a capture. `LogToFile=1` writes the packets to a file.

The install of the user has `SendIntervalMs=100`, `ExtendedData=1`, `ExtendedData1=1`,
`LogToFile=1` and `Enabled=0` (set to 1 for E1, see its findings below).

### Flight plan keys

Keys found in the 25 flight plans of the user's computer (races, examples of Condor, last flight
plan), `#` for a number:

- `[Version]` `Condor`
- `[Task]` `Landscape`, `Count`, `TPName#`, `TPPosX#`, `TPPosY#`, `TPPosZ#`, `TPAirport#`,
  `TPSectorType#`, `TPSectorDirection#`, `TPRadius#`, `TPAngle#`, `TPAltitude#`, `TPWidth#`,
  `TPHeight#`, `TPAzimuth#`, `PZCount` (penalty zones), `DisabledAirspaces`; in flight plans
  downloaded from Condor Club, also `TaskID` (the Condor Club id of the task:
  `TaskID=25216` is `condor.club/api/task/25216`, C3-d010a), `TaskName` and `TaskVersion`
- `[Weather]` `WZCount`, `RandomizeWeatherOnEachFlight`
- `[WeatherZone#]` `Name`, `PointCount`, `Polygon#X`, `Polygon#Y`, `BorderWidth`, `WindDir`,
  `WindSpeed`, `WindUpperSpeed`, `WindDirVariation`, `WindSpeedVariation`, `WindTurbulence`,
  `ThermalsTemp`, `ThermalsTempVariation`, `ThermalsDew`, `ThermalsStrength`,
  `ThermalsStrengthVariation`, `ThermalsInversionheight`, `ThermalsOverdevelopment`,
  `ThermalsActivity`, `ThermalsActivityVariation`, `ThermalsFlatsActivity`, `ThermalsWidth`,
  `ThermalsWidthVariation`, `ThermalsTurbulence`, `ThermalsStreeting`, `ThermalsBugs`,
  `HighCloudsCoverage`, `WavesStability`, `WavesMoisture`, `MoveDir`, `MoveSpeed`
- `[Plane]` `Class`, `Name`, `Skin`, `Water`, `FixedMass`, `CGBias`, `Bugwipers`, `Seat`
- `[GameOptions]` `TaskDate`, `StartTime`, `StartTimeWindow`, `RaceStartDelay`, `StartType`,
  `StartHeight`, `RopeLength`, `MaxStartGroundSpeed`, `MaxWingLoading`, `MaxTeams`, `AAT`,
  `AATTime`, `AcroFlight`, `RandSeed`, `BreakProb`, `IconsVisibleRange`, `ThermalHelpersRange`,
  `TurnpointHelpersRange`, `Allow…` (realism options), `Penalty…` (penalty points)
- `[Description]` `Text` (the briefing shown in Condor)

With `RandomizeWeatherOnEachFlight=0` (the planner's "Randomize weather on each flight" left
unchecked), a flight plan gives the same air on each flight, thermals in the same places (confirmed
by the user, 2026-10-12): repeatable exercises are possible. The launch is also chosen in the flight
plan: `StartType` 0 aerotow, 1 winch (where the airfield has one), 2 airborne (checked on three
flight plans of the club saved with each, 2026-10-12), with `StartHeight` (the aerotow release
height, or the height of the airborne start); `BreakProb` and `RopeLength` for the tow and the
winch.

## What it allows

### 1. Exact official figures (easy, no install) — done (7l1)

Read the `LCON` lines of IGC files exported by Condor: official status, task time, distance and
speed, total penalty and each penalty by cause, start time of day, settings of the task. Members
would upload the IGC export rather than (or besides) the `.ftr`: the `.ftr` keeps the attitude and
0.1 s records, the IGC the official figures. Joined to the same flight (race id, CN), the page
would show both. The score is not in the IGC (it depends on the other pilots): it stays the one of
the race results.

### 2. Flight plans for a skill (medium, no install)

Generate `.fpl` files from the site: a landscape and its airfields (`.apt`, already read for the
glide cone), a task, a weather zone, a start type and height, a briefing in `[Description]`, then
download (or start, see 3).

First step done (2026-10-12, roadmap 7n4): the flight plan workshop of the site
(`/plans-de-vol/atelier`, [sessions](sessions.md#flight-plan-workshop)) gathers these settings and
composes the instructions with which Claude writes the file (`var/condor-study/generated/`, not in
git); the first file written this way was flown, see "A flight plan written by a script" below.

eplaneur.fr already publishes such flight plans (pages "Exercices ePlaneur" and "Fichiers FPL
ePlaneur" under https://eplaneur.fr/menu-condor/menu-plan-vol/, read on 2026-10-12, written for
Condor 2): a library by level (`d` beginners, `s` standard, `c` competition, exercises), the
exercises e001 to e009 (spiral climbs near Le Versoud and Barcelonnette, MacCready, Condor circuits,
the challenges and badges of Condor Club), 159 landing flight plans for the airfields of the FrSW
landscape (wind along the runway) and 53 for the outlanding fields (airborne start over the field).
They are made for Condor 2 and, for the user (2026-10-12), not convincing enough: they are to be
made again for Condor 3, which ones being the user's choice. Examples:

| Exercise | Flight plan | Judged by |
|---|---|---|
| Crosswind landing | Airborne start (`StartType` 2) low near an airfield, wind across its runway, gusts and turbulence | Analysis of the track: drift and speed on final, touchdown point and speed (to build) |
| Fastest climb | Airborne start under a strong, wide thermal day, fixed `RandSeed` | Time to gain N metres, average climb, centring (radar of 7h5) |
| Race against the clock | Short task, regatta start, target time in the briefing | Task time and speed, against the target and the club's best |
| Final glide | Airborne start at a given distance and height of the finish, `LowFinish` penalty | Arrival height, the waste of 7g1 |
| Ridge run | Strong wind against a ridge, few thermals | Height kept, speed along the ridge |

Condor itself judges nothing: the site analyses the track afterwards (or live, see 4). The fixed
weather and the airborne start are confirmed (E2, E3). An airborne start has no direction key: the
manual (Setting a remote airstart point) puts the glider at the takeoff point, which the author may
move anywhere, pointed towards the start point, at `StartHeight` above the ground. The test flight of
2026-10-12 agrees: first UDP heading 245°, bearing from the takeoff point to the start 243°.

### 3. Start Condor from the site (medium)

- Joining the club server: a `cndr3:<code>` link starts Condor and joins a server, like the
  "Join" button of the Condor server list. The code of the club server can be copied from that
  list, or derived from its address once its encoding is known (to check with the known address
  of a server).
- Starting a generated flight plan: no documented command line was found. A browser cannot write
  into `Documents\Condor3\FlightPlans`: the member downloads the `.fpl` and opens it in Condor, or
  a small helper program does it (see 4).

### 4. A live helper during the flight (larger, needs a small program on the member's computer)

A web page cannot read UDP nor files of the computer. A small program ("ePlaneur Connect",
Windows) would listen to the UDP output (and read `Spectate.json`), and serve it to the club page
on `ws://localhost` (allowed from an https page). The page would then show, live:

- instruments and coaching: netto, MacCready speed to fly with the polar of the glider, the
  centring radar of 7h5 in the thermal, the bank and speed in turns, height above the ground and
  glide reach to the airfields (7i3), the messages of Condor (`hudmessages`);
- during a club race, every pilot from `Spectate.json`: a live ranking and map for the members
  watching, or for an instructor following a student.

The same program could save the flight plans of the site into Condor's folder, start Condor
(`cndr3:` or `Condor.exe`), and upload the `.ftr` and the IGC export after the flight.

## Experiments still to run

| # | Experiment | Who | Answers |
|---|---|---|---|
| E1 | Set `Enabled=1` in `C:\Condor3\Settings\UDP.ini`, fly a few minutes, send the log Condor writes (`LogToFile=1`) | User | Done (2026-10-12), see below |
| E2 | Fly the same flight plan twice, `RandomizeWeatherOnEachFlight=0` and a fixed `RandSeed`, upload both tracks | User | Done (2026-10-12): the same thermals when the weather is not randomized |
| E3 | Airborne start: save a flight plan with `StartType` 2 and a given height, fly it | User | Done (2026-10-12): at the takeoff point (movable), `StartHeight` above the ground, pointed towards the start point (manual and test flight) |
| E4 | Copy the "Join" code of the club server from the server list while it runs, with its address | User | The encoding of `cndr3:` codes |
| E5 | During a club race, watch `Logs\Spectate.json` (how often it changes, whether it needs spectator mode) | User | Whether a live race view is possible |
| E6 | Export the IGC of a few races of the dev data (Condor > flight analysis > IGC export) | User | Done (2026-10-11), see below |

## Findings of E6 (2026-10-11)

Five races exported by the user (`var/condor-study/igc/`, own flights, CN RS2), read by
`CondorReport` and compared with the analysis of the same track (`var/condor-study/compare.php`
and `taskstart.php`):

| Race | Condor's penalties | Our estimate | Our task time against Condor's |
|---|---|---|---|
| C3-c058 (regatta) | cloud flying 108.3, height recovery 200 | none: no task found | none |
| C3-s109 (window) | airspace 40 | 0 (airspace not shown in the track) | 4,524 s / 4,527 s |
| C3-c060 (regatta) | start speed 0.2 (170.1 km/h) | 0 (169.3 km/h) | 4,846 s / 4,909 s |
| race 600724601 (window) | start speed 23.8 (161.9 km/h, limit 150) | 24 (162 km/h) | 3,798 s / 3,804 s |
| C3-s117 (window) | height recovery 200 | start speed 3.8 (171.9 km/h) | 4,216 s / 4,219 s |

- `LCONPenalties` lines name the rule of each penalty: the 200 points of flight 2 of the dev data
  (C3-s117) are a height recovery, which the track cannot show.
- The start speed measured on the 1 s series is within 1 to 2 km/h of Condor's: right on one race,
  0.7 km/h under on another, 1.9 km/h over on a third (a penalty Condor did not count).
- `TaskStart` is the opening of the gate in a regatta and the crossing of the start line in a window
  (our crossing within 1 s). Our finish came 2 to 5 s before Condor's: the analysis put the finish
  half disc on the near side of the line. Fixed in 7m2 (half disc beyond the line): start and finish
  within 1 or 2 s, task times within 1 s.
- In both regattas, `TaskStart` is 15 minutes after the gate opening the site computes from the
  flight plan (`StartTime` + `RaceStartDelay`: 14:50 against 15:05 on C3-c060, 13:50 against 14:05
  on C3-c058); on C3-s117 the start at 12:23 is after the end of the window the site computes
  (12:05 to 12:20). Confirmed by the user: the server has a "stop join" time (`JoinTimeLimit` in
  `Host.ini`, not in the flight plan; 10 minutes on the user's own host), after which the race delay
  runs. It is in no file Condor writes (10 flight plans, the results and the IGC exports checked).
  The club's dedicated server uses 15 minutes: `TaskSettings::JOIN_TIME`, the default of the
  "Stop join" field of a session, which an IGC export of a regatta fills by itself.
- C3-c058 has a height recovery: Condor moves the glider at once, `TrackReader` kept the longest
  continuous part and the start of the task was lost. Fixed in 7m1 (recoveries kept and left out of
  the climbs); the two jumps at the finish were recoveries too, not an automatic lift.
- `PlayerStatus` is `/` in these exports, `Finished` in the export of C3-c060 of 2026-06-21: not
  reliable, read as unknown.

## Findings of E1 (2026-10-12)

A solo test flight of 28 min on the flight plan of C3-c060 (airborne start), with `Enabled=1`,
`SendIntervalMs=100`, `ExtendedData=1`, `ExtendedData1=1` and `LogToFile=1`. The track and the log
are in `var/condor-study/test/` and `var/condor-study/udp/` (not in git).

- The log is `C:\Condor3\Logs\UDPlog.txt`, in the install folder (not in `Documents\Condor3`):
  the packets as sent, `name=value` lines, a blank line between packets.
- 16,439 packets in 27.7 min: 101 ms apart on average (409 ms at most), so 10 a second.
- Fields of every packet: `time`, `airspeed` (m/s, true airspeed: present in Condor 3),
  `altitude`, `vario`, `evario`, `nettovario`, `integrator`, `compass` (°), `yawstringangle`,
  `radiofrequency`, `yaw`, `pitch`, `bank` (rad), `quaternionx/y/z/w`, `ax/ay/az`, `vx/vy/vz`,
  `rollrate`, `pitchrate`, `yawrate`, `gforce`, `height` (above the ground: 1,100 m at the
  airborne start), `wheelheight`, `turbulencestrength`, `surfaceroughness`, `flaps`, `MC`,
  `water`. `slipball` and `turnrate` of the manual are not sent.
- **No position**: neither latitude and longitude nor landscape coordinates. A live map needs
  another source (`Spectate.json`, E5); the speed vector gives the path over a short time (the
  circles of a thermal for the centring radar), not over a flight.
- `vx/vy/vz` are in the frame of the landscape, not of the glider: `vy` north, `vx` west (the
  landscape X axis), `vz` up, m/s. The course is `atan2(-vx, vy)`; it differs from `compass` (the
  heading, `yaw` in degrees) by the wind drift.
- `hudmessages` is sent only with an event, with the pilot's short name:
  `R.SIDA started task;`, `R.SIDA entered cloud;`, `R.SIDA left cloud;`, and on the second test
  flight `R.SIDA entered airspace;`, `R.SIDA left airspace;`, `R.SIDA turned Morgon;` (the name of
  the turnpoint) and `R.SIDA completed task;` (a solo finish has its message).
- The task started at 505.0 s after the first fix (14:53:25), as the analysis found; the ground
  speed of the vector then was 232.4 to 232.7 km/h, the speed Condor's penalty gives (125.3 points
  at 2 a km/h over 170). The analysis reads 227.3 km/h from the IGC and 231.4 km/h from the
  `.ftr`: its speed is averaged over a few seconds and the IGC positions are rounded to about 2 m.
- The glider entered a cloud at 2,835 m and left it 0.3 s later at 2,839 m, at the top of a thermal
  whose lowest ground was 1,195 m (open question on the clouds of 7k4 in the roadmap).

What it allows: the live instruments of section 4 (netto, speed to fly with `airspeed`, `MC` and
`water`, centring radar from the heading, the vario and the speed vector, height above the ground),
the moment of the start and cloud entries as they happen. A live map of the race still depends on
E5.

## A flight plan written by a script (2026-10-12)

A first `.fpl` written from scratch for AA3 (`var/condor-study/generated/generate.php`, not in git):
airborne start from a point off the airfields 4.5 km south-east of Barcelonnette, 800 m above the
ground read in `AA3.trn`, start line, Morgon (500 m cylinder), finish on Barcelonnette (1 km
cylinder), fixed weather with cumulus. The user loaded and flew it in Condor 3 (`test2.ftr`,
`test2.igc` and the UDP log in `var/condor-study/test2/`).

- Condor opened it without complaint. It saved it again as `Condor version=3100`, added an empty
  `DisabledAirspaces=` and cut the turnpoint names to 16 characters (`Airstart Barcelo`); the
  coordinates, heights and zones were kept as written.
- The airborne start is exact: first UDP packet at 3,067.3 m, 800.7 m above the ground (the ground
  read in `AA3.trn` was 1.3 m higher than Condor's), course 18.5°, the bearing from the takeoff point
  to the start point.
- The whole task lies under the airspace `R196A1 Est GAP (Notam)` of `AA3.air` (OpenAir text,
  `AL 3300FT AGL`, up to FL195): 14 entries, each at 1,003 to 1,018 m above the ground by the UDP
  `height`, so Condor's floor in feet above the ground uses the same ground as `height`. With
  `PenaltyAirspaceEnterance=0` it cost nothing, but it kept the flight under 3,160 m, so no cloud was
  met. A flight plan that does not want it must list it in `DisabledAirspaces`, or keep away from it.
- Task time: 984 s for the site as for Condor. Start speed: Condor's penalty of 9.9 points (1 a
  km/h over 170) gives 179.9 km/h; the analysis reads 180.0 from the `.ftr`, 184.1 from the IGC.
- Distance: Condor 37.05 km, the site 37.98 km. Between the centres in Condor's coordinates the task
  is 38.05 km; Condor's figure is that less the 1 km radius of the finish cylinder. The site measures
  between the centres (`TaskResult`), so it is 0.9 km over on a cylinder finish, and its speed is
  2.5 % over.

## Landscape files (2026-10-12)

The Condor Landscape Guide (rev. 2, November 2019, French translation:
https://www.condorsoaring.com/wp-content/downloads/clt/Condor%20Landscape%20Guide%20FRENCH%20rev2.pdf,
written for Condor 2) explains how landscapes are made:

- UTM projection on WGS 84, one zone for the whole landscape (the projection of `Landscape`);
  coordinates from the south-east corner, x towards the west (hence `vx` west in the UDP output).
- Patches of 5.76 km grouped in tiles of 23.04 km; no flying within 5 km of the edge.
- A thermal map per landscape: a grey image at 90 m a pixel, the probability and strength of
  thermals by surface (water 0, deciduous trees 64, forests and grass 102, fields 178 out of 255).
  Condor 2 and later combine it with the terrain profile and the sun; the `ThermalsFlatsActivity`
  of the flight plan sets its weight (none: terrain and sun only).

Two files of the installed AA3 landscape are not encrypted and were read for the study (copies in
`var/condor-study/landscape/`, not in git; scripts `grid.php`, `check.php`, `cloud.php`,
`thermals.php`, `channels.php`). They belong to the author of the landscape: reading them on the
member's computer is fine, publishing them or data drawn from them would need the author's agreement.

- **`AA3.trn`, the terrain of Condor**: a 36-byte header (width 10,240 and height 6,400 cells, 90,
  −90 and 90 m, easting 1,059,840 and northing 4,786,560 of the origin, UTM zone 32, `N`), then
  int16 heights in metres column by column (each column holds the 6,400 cells from south to north,
  columns from east to west). Interpolated between cells, it gives the ground of the UDP output
  (altitude − height) within 0.2 m (median of 329 points of the test flight; 90 % within 5 m).
- **`AA3.tm3`, the thermal map**: 3 bytes a cell, on the same grid, row by row (each row holds the
  10,240 cells from east to west, rows from south to north):
  - byte 0: the thermal map of the guide, by surface: 18 values, water 0, forests 112-113, grass
    148-149, fields 170-171 and 181-182, built-up 235-236, rock and snow 255;
  - byte 1: the steepness of the relief: it follows the slope (correlation 0.75) and the height
    (0.68), 45 on average below 200 m and 255 (saturated) above 1,000 m;
  - byte 2: the convexity of the terrain around 900 m, 127 on flat ground, above on ridges and
    tops, below in valleys (correlation 0.97 with the height of a cell minus the mean of the cells
    10 away). Where the club pilots circled, byte 2 is higher (median 158) than where they glided
    (133), over 126 thermals of 15 tracks; bytes 0 and 1 hardly differ.

How Condor combines them into thermals is not documented. The cloud of the test flight is consistent
with a base 1,881 m above the mean of Condor's ground within about 10 km (952 m; 954 m needed).

