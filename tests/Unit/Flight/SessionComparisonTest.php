<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\SessionComparison;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\TestCase;

/**
 * The flight of FlightSamples flown by a fast pilot and by a slow one (the same track, twice as
 * slow after 200 s): the slow one loses time on the virtual leader there, and only there.
 */
final class SessionComparisonTest extends TestCase
{
    public function testEachPilotIsComparedToTheBestAtEachPointOfTheTask(): void
    {
        $fast = $this->flight('Fast', 1.0);
        $slow = $this->flight('Slow', 2.0);
        $comparison = SessionComparison::of([$fast, $slow]);
        self::assertNotNull($comparison);

        $gaps = $comparison->gaps();
        self::assertSame(0, max($gaps[0]), 'The fastest everywhere: no gap');
        self::assertSame(0, $gaps[1][0], 'Both start together');
        self::assertGreaterThan(60, end($gaps[1]), 'Twice as slow after 200 s');

        $legs = $comparison->legs();
        self::assertCount(1, $legs);
        self::assertSame($legs[0]['times'][0], $legs[0]['best']);

        $moments = $comparison->moments();
        self::assertNull($moments[0]);
        self::assertNotNull($moments[1]);
        self::assertSame('Fast', $moments[1]['fastest']->getPilot());

        $data = $comparison->chartData();
        self::assertCount(2, $data['flights']);
        self::assertSame(0.25, $data['km'][1]);
    }

    public function testOneFlightIsNotAComparison(): void
    {
        self::assertNull(SessionComparison::of([$this->flight('Alone', 1.0)]));
    }

    private function flight(string $pilot, float $slowdown): Flight
    {
        // After 200 s the slow pilot takes `slowdown` times longer for the same track
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time <= 200 ? $fix->time : 200 + ($fix->time - 200) * $slowdown, $fix->latitude, $fix->longitude, $fix->altitude), FlightSamples::glideThermalGlide());
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000), $point('Arrivée', 7000, 500)];
        $track = new Track('igc', 'Condor 3', $pilot, null, null, null, null, 'Tâche test', $task, $fixes, true);

        return new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', null, new \DateTimeImmutable());
    }
}
