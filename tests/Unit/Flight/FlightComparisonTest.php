<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\FlightComparison;
use App\Flight\Ghost;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\TestCase;

/**
 * Comparison along the task of the synthetic flight of FlightSamples (thermal at x = 3,570 m) with
 * the same flight where the thermal lasts 90 s longer: no gap before the thermal, 90 s after it.
 */
final class FlightComparisonTest extends TestCase
{
    public function testTheGapAppearsWhereTheTimeIsLost(): void
    {
        $comparison = FlightComparison::of($this->flight('Slow', 1.5), $this->flight('Fast'));
        self::assertNotNull($comparison);
        $gaps = $comparison->gaps();

        self::assertSame(0, $gaps[intdiv(3000, FlightComparison::STEP)], 'Same time before the thermal');
        self::assertEqualsWithDelta(90, $gaps[intdiv(6000, FlightComparison::STEP)], 2, 'The thermal was 90 s longer');
        self::assertEqualsWithDelta(90, $comparison->legs()[0]['gap'], 2);
    }

    public function testTheKeyMomentTellsWhatEachPilotDid(): void
    {
        $comparison = FlightComparison::of($this->flight('Slow', 1.5), $this->flight('Fast'));
        self::assertNotNull($comparison);

        $losses = $comparison->moments(true);
        self::assertCount(1, $losses);
        self::assertLessThanOrEqual(3570, $losses[0]['from']);
        self::assertGreaterThanOrEqual(3570, $losses[0]['to']);
        self::assertEqualsWithDelta(90, $losses[0]['flight']['circling'] - $losses[0]['reference']['circling'], 12);
        // Glides at 30 m/s; the first and last seconds of the circling count as glide (turn rate averaged)
        self::assertEqualsWithDelta(108, $losses[0]['flight']['glideSpeed'], 10);
        self::assertSame([], $comparison->moments(false), 'Nothing gained');

        $thermals = $comparison->thermals(0);
        self::assertCount(1, $thermals);
        self::assertEqualsWithDelta(3500, $thermals[0]['at'], FlightComparison::STEP);
    }

    public function testTheWaterfallSaysTheTimeWasLostCircling(): void
    {
        $comparison = FlightComparison::of($this->flight('Slow', 1.5), $this->flight('Fast'));
        self::assertNotNull($comparison);
        $waterfall = $comparison->waterfall();

        self::assertCount(1, $waterfall);
        self::assertEqualsWithDelta(90, $waterfall[0]['gap'], 2);
        self::assertEqualsWithDelta(90, $waterfall[0]['circling'], 12, 'Lost circling');
        self::assertEqualsWithDelta(0, $waterfall[0]['straight'], 12);
        self::assertSame(0, $waterfall[0]['before']);
        self::assertSame($waterfall[0]['gap'], $waterfall[0]['after']);
    }

    public function testTheGhostIsTheBestOtherPilot(): void
    {
        $slow = $this->flight('Slow', 1.5);
        $fast = $this->flight('Fast');
        $ghost = Ghost::of($slow, [$fast, $this->flight('Slower', 2.0)]);

        self::assertNotNull($ghost);
        self::assertSame('Fast', $ghost['pilot']);
        self::assertSame(1, $ghost['rank']);
        self::assertSame(0, $ghost['offset'], 'Both crossed the start at the same second of their tracks');
        self::assertEqualsWithDelta(90, end($ghost['gaps']), 2);
        self::assertCount(\count($ghost['gaps']), \array_slice($ghost['times'], 0, \count($ghost['gaps'])));

        // The winner gets the second as a ghost; alone, none
        self::assertSame('Slow', Ghost::of($fast, [$slow])['pilot'] ?? null);
        self::assertNull(Ghost::of($fast, []));
    }

    public function testFlightsOfDifferentTasksAreNotCompared(): void
    {
        self::assertNull(FlightComparison::of($this->flight('A'), $this->flight('B', plan: 'Autre tâche')));
    }

    /**
     * The sample flight, its thermal lasting `$thermalScale` times longer.
     */
    private function flight(string $pilot, float $thermalScale = 1.0, string $plan = 'Tâche test'): Flight
    {
        $fixes = array_map(static function (Fix $fix) use ($thermalScale): Fix {
            $time = match (true) {
                $fix->time < 120 => $fix->time,
                $fix->time < 300 => 120 + ($fix->time - 120) * $thermalScale,
                default => $fix->time + 180 * ($thermalScale - 1),
            };

            return new Fix($time, $fix->latitude, $fix->longitude, $fix->altitude);
        }, FlightSamples::glideThermalGlide());
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)];
        $track = new Track('igc', 'Condor 3', $pilot, null, null, null, null, $plan, $task, $fixes, true);

        return new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', null, new \DateTimeImmutable());
    }
}
