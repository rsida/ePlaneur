<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Analysis\Launch;
use App\Flight\Analysis\Phase;
use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Analysis of the synthetic flight of FlightSamples: a glide at 108 km/h losing 1 m/s, six left
 * turns climbing 2 m/s, another glide; and a task flown through it.
 */
final class FlightAnalyzerTest extends TestCase
{
    public function testPhasesAndStatistics(): void
    {
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide()));
        $phases = $analysis->phases;

        self::assertSame([Phase::GLIDE, Phase::THERMAL, Phase::GLIDE], array_map(static fn (Phase $phase): string => $phase->type, $phases));
        $thermal = $phases[1];
        self::assertEqualsWithDelta(180, $thermal->duration(), 12, 'The thermal is found within a few seconds');
        self::assertEqualsWithDelta(2.0, $thermal->vario(), 0.15);
        self::assertSame('left', $thermal->direction);
        // Six turns of 100 m at 12°/s (75 km/h): bank atan(v·ω/g) = 24°, no drift
        self::assertEqualsWithDelta(6.0, $thermal->turns, 0.6);
        self::assertEqualsWithDelta(100.0, $thermal->radius, 8.0);
        self::assertEqualsWithDelta(24.0, $thermal->bank, 2.0);
        self::assertEqualsWithDelta(0.0, $thermal->windSpeed, 3.0);
        self::assertEqualsWithDelta(30.0, $phases[0]->glideRatio, 1.5, '30 m/s for 1 m/s of sink');
        self::assertEqualsWithDelta(108.0, $phases[2]->speed(), 2.0);

        $statistics = $analysis->statistics;
        self::assertSame(419, $statistics['duration']);
        self::assertSame(1, $statistics['thermals']);
        self::assertEqualsWithDelta(108.0, $statistics['speedMax'], 1.0);
        self::assertEqualsWithDelta(2.0, $statistics['climbAverage'], 0.15);
        self::assertEqualsWithDelta(0.43, $statistics['circlingShare'], 0.03);
        self::assertSame(1.0, $statistics['leftShare']);
        self::assertEqualsWithDelta(1741.0, $statistics['altitudeMax'], 1.0);

        // Turns by direction, height gained and climbed, glides straight, no stop on the way
        self::assertSame([0, null], [$statistics['rightSpirals'], $statistics['rightClimb']]);
        self::assertSame(1, $statistics['leftSpirals'], 'One spiral of six turns');
        self::assertEqualsWithDelta(2.0, $statistics['leftClimb'], 0.15);
        self::assertEqualsWithDelta(360.0, $statistics['bestGain'], 2.0, 'From 1,381 m to 1,741 m');
        self::assertEqualsWithDelta(360.0, $statistics['totalClimb'], 2.0);
        self::assertSame(0, $statistics['release'], 'Airborne: nothing to leave out');
        self::assertEqualsWithDelta(0.0, $statistics['glideDetour'], 0.01);
        self::assertSame(0, $statistics['stops']);
        self::assertEqualsWithDelta(1381.0, $statistics['bandBottom'], 15.0);
        self::assertEqualsWithDelta(1741.0, $statistics['bandTop'], 15.0);

        // Measured over 4 s: no noise between close points
        self::assertEqualsWithDelta(-1.0, $analysis->series['vario'][60], 0.01);
        self::assertEqualsWithDelta(108.0, $analysis->series['speed'][60], 0.5);
    }

    public function testTheWindIsReadFromTheDriftOfTheCircles(): void
    {
        // The thermal drifts 5 m/s to the north (18 km/h of southerly wind)
        $fixes = array_map(static function (Fix $fix): Fix {
            $drift = 5.0 * max(0.0, min($fix->time, 300.0) - 120.0);

            return new Fix($fix->time, $fix->latitude + $drift / 111194.9, $fix->longitude, $fix->altitude);
        }, FlightSamples::glideThermalGlide());
        $analysis = (new FlightAnalyzer())->analyze(self::track($fixes));
        $thermal = $analysis->phases[1];

        self::assertEqualsWithDelta(18.0, $thermal->windSpeed, 2.0);
        self::assertEqualsWithDelta(180.0, $thermal->windFrom, 10.0, 'From the south');
        self::assertEqualsWithDelta(18.0, $analysis->statistics['windSpeed'], 2.0);
        self::assertEqualsWithDelta(180.0, $analysis->statistics['windFrom'], 10.0);
    }

    public function testTheCentringTellsWhereTheThermalIsStronger(): void
    {
        // The same six turns, climbing 2 m/s plus 1 m/s on the north-east side of the circle and
        // minus 1 m/s on the south-west side
        $fixes = FlightSamples::glideThermalGlide();
        $altitude = 1381.0;
        foreach ($fixes as $t => $fix) {
            if ($t < 120 || $t >= 300) {
                continue;
            }
            $angle = deg2rad(12.0 * ($t - 120));
            // Bearing of the glider from the centre of the circle
            $around = atan2(sin($angle), -cos($angle));
            $fixes[$t] = new Fix($fix->time, $fix->latitude, $fix->longitude, $altitude);
            $altitude += 2.0 + cos($around - deg2rad(45.0));
        }
        $thermal = (new FlightAnalyzer())->analyze(self::track($fixes))->phases[1];

        self::assertNotNull($thermal->centring);
        self::assertCount(8, $thermal->centring);
        $best = array_keys($thermal->centring, max($thermal->centring))[0];
        $worst = array_keys($thermal->centring, min($thermal->centring))[0];
        self::assertSame(1, $best, 'North-east');
        self::assertSame(5, $worst, 'South-west');
        self::assertGreaterThan(1.5, $thermal->centring[1] - $thermal->centring[5]);
    }

    public function testTheEntryAndTheExitOfAThermal(): void
    {
        // The same six turns of 30 s: 0.5 m/s for a minute, 3 m/s, then 0.5 m/s for the last 40 s
        $fixes = FlightSamples::glideThermalGlide();
        $altitude = 1381.0;
        foreach ($fixes as $t => $fix) {
            if ($t >= 120) {
                $fixes[$t] = new Fix($fix->time, $fix->latitude, $fix->longitude, $altitude);
                $altitude += $t >= 300 ? -1.0 : ($t < 180 || $t >= 260 ? 0.5 : 3.0);
            }
        }
        $analysis = (new FlightAnalyzer())->analyze(self::track($fixes));
        $thermal = $analysis->phases[1];

        // Centred once the circle climbs 70 % of 3 m/s, and above the average climb of the flight
        // (about 1.6 m/s) until a little after 260 s
        self::assertEqualsWithDelta(50, $thermal->entry, 8);
        self::assertEqualsWithDelta(24, $thermal->exit, 8);
        self::assertSame([], $analysis->crossings);
    }

    public function testLiftCrossedInAGlide(): void
    {
        // Climbing 1.5 m/s for 15 s in the second glide, flown straight at the same speed
        $fixes = FlightSamples::glideThermalGlide();
        $altitude = 1741.0;
        foreach ($fixes as $t => $fix) {
            if ($t >= 300) {
                $fixes[$t] = new Fix($fix->time, $fix->latitude, $fix->longitude, $altitude);
                $altitude += $t >= 350 && $t < 365 ? 1.5 : -1.0;
            }
        }
        $crossings = (new FlightAnalyzer())->analyze(self::track($fixes))->crossings;

        self::assertCount(1, $crossings);
        self::assertEqualsWithDelta(350, $crossings[0]['start'], 3);
        self::assertEqualsWithDelta(1.5, $crossings[0]['vario'], 0.1);
        self::assertEqualsWithDelta(20.0, $crossings[0]['gain'], 4.0, '22.5 m gained, less the sink after it');
    }

    public function testAPullUpIsNotLift(): void
    {
        // Slowing from 50 to 30 m/s in 10 s turns speed into about 80 m of height: no lift
        $fixes = [];
        [$x, $altitude] = [0.0, 1500.0];
        for ($t = 0; $t < 200; ++$t) {
            $speed = $t < 100 ? 50.0 : ($t < 110 ? 50.0 - 2.0 * ($t - 100) : 30.0);
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon($x, 0), altitude: $altitude);
            $x += $speed;
            $altitude += $t >= 100 && $t < 110 ? 7.0 : -1.5;
        }

        self::assertSame([], (new FlightAnalyzer())->analyze(self::track($fixes))->crossings);
    }

    public function testAStraightClimbIsSlopeOrWaveLift(): void
    {
        // Straight flight climbing 1.5 m/s for two and a half minutes, after a glide
        $fixes = [];
        for ($t = 0; $t < 300; ++$t) {
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon(25.0 * $t, 0), altitude: 1500.0 + ($t < 150 ? -$t : -150 + 1.5 * ($t - 150)));
        }
        $analysis = (new FlightAnalyzer())->analyze(self::track($fixes));

        self::assertSame([Phase::GLIDE, Phase::LIFT], array_map(static fn (Phase $phase): string => $phase->type, self::merged($analysis->phases)));
        self::assertSame(1, $analysis->statistics['lifts']);
    }

    /**
     * Phase types without repeats (a glide may be cut in two by the change of slope).
     *
     * @param list<Phase> $phases
     *
     * @return list<Phase>
     */
    private static function merged(array $phases): array
    {
        $merged = [];
        foreach ($phases as $phase) {
            if ([] === $merged || end($merged)->type !== $phase->type) {
                $merged[] = $phase;
            }
        }

        return $merged;
    }

    public function testAHeightRecoveryIsNotAClimb(): void
    {
        // Condor puts the glider 500 m higher in the middle of the thermal, at 200 s
        $plain = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide()));
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time, $fix->latitude, $fix->longitude, $fix->altitude + ($fix->time >= 200 ? 500 : 0)), FlightSamples::glideThermalGlide());
        $track = new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, [], $fixes, true, recoveries: [['time' => 200.0, 'height' => 500.0]]);
        $analysis = (new FlightAnalyzer())->analyze($track);

        self::assertSame(array_map(static fn (Phase $phase): string => $phase->type, $plain->phases), array_map(static fn (Phase $phase): string => $phase->type, $analysis->phases));
        self::assertEqualsWithDelta($plain->phases[1]->vario(), $analysis->phases[1]->vario(), 0.01, 'the thermal climbs as without the recovery');
        foreach (['totalClimb', 'bestGain', 'thermalGain', 'varioMax'] as $key) {
            self::assertEqualsWithDelta($plain->statistics[$key], $analysis->statistics[$key], 0.5, $key);
        }
        self::assertEqualsWithDelta($plain->statistics['altitudeMax'] + 500, $analysis->statistics['altitudeMax'], 1.0, 'the altitude stays the real one');
        self::assertSame([['at' => 200, 'height' => 500.0]], $analysis->toArray()['recoveries']);
        self::assertEqualsWithDelta(
            array_sum(array_column($plain->toArray()['climbByAltitude'], 'seconds')),
            array_sum(array_column($analysis->toArray()['climbByAltitude'], 'seconds')),
            40,
        );
        self::assertLessThan(3.0, max(array_column($analysis->toArray()['climbByAltitude'], 'climb')), 'no band climbs 500 m in a second');
    }

    public function testTheClockOfTheTaskFollowsTheFirstFixKept(): void
    {
        // The track begins at 14:53:20 in Condor; its first 10 s were cut off
        $fixes = \array_slice(FlightSamples::glideThermalGlide(), 10);
        $track = new Track('ftr', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, [], $fixes, true, clock: 53600.0);

        self::assertSame(53610, (new FlightAnalyzer())->analyze($track)->toArray()['clock']);
        self::assertNull((new FlightAnalyzer())->analyze(self::track($fixes))->toArray()['clock']);
    }

    public function testFixesAreResampledToOnePerSecond(): void
    {
        // Ten fixes per second, as in Condor tracks
        $samples = FlightSamples::glideThermalGlide();
        $fixes = [];
        foreach ($samples as $i => $fix) {
            $next = $samples[$i + 1] ?? $fix;
            for ($k = 0; $k < (isset($samples[$i + 1]) ? 10 : 1); ++$k) {
                $ratio = $k / 10;
                $fixes[] = new Fix(
                    $fix->time + $ratio,
                    $fix->latitude + ($next->latitude - $fix->latitude) * $ratio,
                    $fix->longitude + ($next->longitude - $fix->longitude) * $ratio,
                    $fix->altitude + ($next->altitude - $fix->altitude) * $ratio,
                );
            }
        }
        $analysis = (new FlightAnalyzer())->analyze(self::track($fixes));

        self::assertCount(420, $analysis->series['time']);
        self::assertSame(1, $analysis->statistics['thermals']);
    }

    public function testALineFinishIsMeasuredToItsCentre(): void
    {
        $point = static fn (string $name, float $x, float $radius, float $angle = 360.0): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius, angle: $angle);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000), $point('Turnpoint', 3000, 500), $point('Arrivée', 7000, 500, 180.0)];
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide(), $task));

        self::assertNotNull($analysis->task);
        self::assertEqualsWithDelta(3000, $analysis->task->leg(2), 1, 'A turnpoint is measured to its centre');
        self::assertEqualsWithDelta(4000, $analysis->task->leg(3), 1, 'A line finish is not shortened');
        self::assertEqualsWithDelta(7000, $analysis->task->distance, 1);
    }

    public function testTheTaskIsFollowed(): void
    {
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000), $point('Arrivée', 7000, 500)];
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide(), $task));

        self::assertNotNull($analysis->task);
        // Out of the start cylinder at x = 1,000 m (first point out: 34 s), enters the finish one at
        // x = 6,500 m (398 s)
        self::assertSame([null, 34, 398], $analysis->task->reached);
        self::assertEqualsWithDelta(6500, $analysis->task->distance, 1, 'To the edge of the finish cylinder, as Condor counts it');

        $task = $analysis->toArray()['task'];
        self::assertTrue($task['completed']);
        self::assertSame(364, $task['duration']);
        self::assertEqualsWithDelta(64.3, $task['speed'], 0.1, '6.5 km in 364 s');
        self::assertEqualsWithDelta(6500, $task['legs'][0]['distance'], 1);

        // Passage of each point, and the final glide from the end of the thermal to the finish
        self::assertEqualsWithDelta(1466.0, $task['turnpoints'][1]['reachedAltitude'], 1.0, '1,500 m - 34 s at 1 m/s');
        self::assertEqualsWithDelta(108.0, $task['turnpoints'][2]['reachedSpeed'], 1.0);
        $final = $task['finalGlide'];
        self::assertNotNull($final);
        self::assertEqualsWithDelta(2.0, $final['climb'], 0.15);
        self::assertEqualsWithDelta(1643.0, $final['arrivalAltitude'], 2.0, '1,741 m - 98 s at 1 m/s');
        self::assertSame($final['arrivalAltitude'], $final['height'], 'The finish point is at 0 m');
        self::assertEqualsWithDelta(1643 / 2.0, $final['heightTime'], 60);
    }

    public function testHalfDiscStartAndFinishOfCondor(): void
    {
        // Start and finish of 180°: half discs on each side of the line across the leg. The start
        // zone lies west of x = 1,000 m, left when crossing the line; the finish zone lies east of
        // x = 6,500 m, entered when crossing the line (not 400 m before, as Condor's times show)
        $point = static fn (string $name, float $x, float $radius, float $angle = 360.0): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius, angle: $angle);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 1000, 2000, 180), $point('Arrivée', 6500, 400, 180)];
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide(), $task));

        self::assertNotNull($analysis->task);
        // x = 1,000 m at 33.3 s; x = 6,500 m at 397.7 s
        self::assertSame([null, 34, 398], $analysis->task->reached);
    }

    public function testAFlightBeginningInTheAirIsAnAirborneStart(): void
    {
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide()));

        self::assertSame(Launch::Airborne, $analysis->launch);
        self::assertSame('airborne', $analysis->toArray()['launch']);
    }

    public function testAnAirborneStartHeldStillByCondorIsNotOnTheGround(): void
    {
        // Condor 3 holds an airborne start still for about 10 s, 1,100 m above the departure
        // airfield, then the glider accelerates and glides
        $fixes = [];
        for ($t = 0; $t < 160; ++$t) {
            $x = $t < 10 ? 0.0 : 30.0 * ($t - 10);
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon($x, 0), altitude: 1300.0 - max(0, $t - 10));
        }
        $airfield = new Turnpoint('Terrain', ...FlightSamples::toLatLon(-5000, 0), altitude: 200.0, radius: 3000.0);

        self::assertSame(Launch::Airborne, (new FlightAnalyzer())->analyze(self::track($fixes, [$airfield]))->launch);
    }

    /**
     * @return iterable<string, array{float, Launch}>
     */
    public static function groundLaunches(): iterable
    {
        yield 'aerotow: 3 m/s behind the tug' => [3.0, Launch::Aerotow];
        yield 'winch: 15 m/s on the cable' => [15.0, Launch::Winch];
    }

    #[DataProvider('groundLaunches')]
    public function testALaunchFromTheGroundIsReadFromTheClimb(float $climb, Launch $expected): void
    {
        // 30 s still on the airfield at 200 m, 10 s of ground roll, then a straight climb at 30 m/s
        $fixes = [];
        for ($t = 0; $t < 160; ++$t) {
            $x = match (true) {
                $t < 30 => 0.0,
                $t < 40 => 1.5 * ($t - 30) ** 2,
                default => 150.0 + 30.0 * ($t - 40),
            };
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon($x, 0), altitude: 200.0 + max(0, $t - 40) * $climb);
        }
        $airfield = new Turnpoint('Terrain', ...FlightSamples::toLatLon(0, 0), altitude: 200.0, radius: 3000.0);

        self::assertSame($expected, (new FlightAnalyzer())->analyze(self::track($fixes))->launch, 'Still at the beginning');
        self::assertSame($expected, (new FlightAnalyzer())->analyze(self::track(\array_slice($fixes, 28), [$airfield]))->launch, 'At the height of the departure airfield');
    }

    public function testTheTowClimbIsNotSlopeLift(): void
    {
        // 30 s still, 10 s of ground roll, 200 s climbing 3 m/s behind the tug, then a glide
        $fixes = [];
        for ($t = 0; $t < 440; ++$t) {
            $x = match (true) {
                $t < 30 => 0.0,
                $t < 40 => 1.5 * ($t - 30) ** 2,
                default => 150.0 + 30.0 * ($t - 40),
            };
            $altitude = 200.0 + 3.0 * min(200, max(0, $t - 40)) - max(0, $t - 240);
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon($x, 0), altitude: $altitude);
        }
        $analysis = (new FlightAnalyzer())->analyze(self::track($fixes));
        $types = array_map(static fn (Phase $phase): string => $phase->type, $analysis->phases);

        self::assertSame(Launch::Aerotow, $analysis->launch);
        self::assertContains(Phase::LAUNCH, $types);
        self::assertNotContains(Phase::LIFT, $types);
        self::assertSame(0, $analysis->statistics['lifts']);
    }

    /**
     * @param list<Fix>       $fixes
     * @param list<Turnpoint> $turnpoints
     */
    private static function track(array $fixes, array $turnpoints = []): Track
    {
        return new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, $turnpoints, $fixes, true);
    }
}
