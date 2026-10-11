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
`LogToFile=1` and `Enabled=0`.

### Flight plan keys

Keys found in the 25 flight plans of the user's computer (races, examples of Condor, last flight
plan), `#` for a number:

- `[Version]` `Condor`
- `[Task]` `Landscape`, `Count`, `TPName#`, `TPPosX#`, `TPPosY#`, `TPPosZ#`, `TPAirport#`,
  `TPSectorType#`, `TPSectorDirection#`, `TPRadius#`, `TPAngle#`, `TPAltitude#`, `TPWidth#`,
  `TPHeight#`, `TPAzimuth#`, `PZCount` (penalty zones), `DisabledAirspaces`
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

`RandSeed` with `RandomizeWeatherOnEachFlight=0` suggests that a flight plan gives the same air
(same thermals) on each flight: to check, it would make repeatable exercises possible.

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
download (or start, see 3). Examples:

| Exercise | Flight plan | Judged by |
|---|---|---|
| Crosswind landing | Airborne start (`StartType` 2) low near an airfield, wind across its runway, gusts and turbulence | Analysis of the track: drift and speed on final, touchdown point and speed (to build) |
| Fastest climb | Airborne start under a strong, wide thermal day, fixed `RandSeed` | Time to gain N metres, average climb, centring (radar of 7h5) |
| Race against the clock | Short task, regatta start, target time in the briefing | Task time and speed, against the target and the club's best |
| Final glide | Airborne start at a given distance and height of the finish, `LowFinish` penalty | Arrival height, the waste of 7g1 |
| Ridge run | Strong wind against a ridge, few thermals | Height kept, speed along the ridge |

Condor itself judges nothing: the site analyses the track afterwards (or live, see 4). To check:
where an airborne start puts the glider (the first point of the task?), and whether `RandSeed`
fixes the thermals.

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
| E1 | Set `Enabled=1` in `C:\Condor3\Settings\UDP.ini`, fly a few minutes, send the log Condor writes (`LogToFile=1`) | User | The real fields and their rate, `airspeed` or not, the frame of `vx/vy/vz`, `hudmessages` |
| E2 | Fly the same flight plan twice, `RandomizeWeatherOnEachFlight=0` and a fixed `RandSeed`, upload both tracks | User, then analysis | Whether the thermals are the same |
| E3 | Airborne start: save a flight plan with `StartType` 2 and a given height, fly it | User | Where and how high the glider starts |
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
