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

        // Measured over 4 s: no noise between close points
        self::assertEqualsWithDelta(-1.0, $analysis->series['vario'][60], 0.01);
        self::assertEqualsWithDelta(108.0, $analysis->series['speed'][60], 0.5);
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

    public function testTheTaskIsFollowed(): void
    {
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000), $point('Arrivée', 7000, 500)];
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide(), $task));

        self::assertNotNull($analysis->task);
        // Out of the start cylinder at x = 1,000 m (first point out: 34 s), enters the finish one at
        // x = 6,500 m (398 s)
        self::assertSame([null, 34, 398], $analysis->task->reached);
        self::assertEqualsWithDelta(7000, $analysis->task->distance, 1);

        $task = $analysis->toArray()['task'];
        self::assertTrue($task['completed']);
        self::assertSame(364, $task['duration']);
        self::assertEqualsWithDelta(69.2, $task['speed'], 0.1, '7 km in 364 s');
    }

    public function testHalfDiscStartAndFinishOfCondor(): void
    {
        // Start and finish of 180°: half discs behind the line across the leg. The start zone lies
        // west of x = 1,000 m, left at once when crossing it; the finish zone lies west of x = 6,500 m,
        // entered 400 m before the point
        $point = static fn (string $name, float $x, float $radius, float $angle = 360.0): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius, angle: $angle);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 1000, 2000, 180), $point('Arrivée', 6500, 400, 180)];
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide(), $task));

        self::assertNotNull($analysis->task);
        // x = 1,000 m at 33.3 s; x = 6,100 m at 384.3 s
        self::assertSame([null, 34, 385], $analysis->task->reached);
    }

    public function testAFlightBeginningInTheAirIsAnAirborneStart(): void
    {
        $analysis = (new FlightAnalyzer())->analyze(self::track(FlightSamples::glideThermalGlide()));

        self::assertSame(Launch::Airborne, $analysis->launch);
        self::assertSame('airborne', $analysis->toArray()['launch']);
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

    /**
     * @param list<Fix>       $fixes
     * @param list<Turnpoint> $turnpoints
     */
    private static function track(array $fixes, array $turnpoints = []): Track
    {
        return new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, $turnpoints, $fixes, true);
    }
}
