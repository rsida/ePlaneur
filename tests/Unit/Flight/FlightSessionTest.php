<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\FlightSession;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\TestCase;

/**
 * Ranking and podium of a session, on the synthetic flight of FlightSamples flown by two pilots:
 * "Glide" as sampled (2 m/s climbs, glide ratio 30), "Climb" with heights 1.5 times larger (3 m/s
 * climbs, glide ratio 20), and "Outlanded", who does not reach the finish.
 */
final class FlightSessionTest extends TestCase
{
    public function testFinishersComeFirstByTaskSpeedThenTheOthersByDistance(): void
    {
        $session = FlightSession::of([$this->outlanded(), $this->flight('Glide'), $this->flight('Climb', 1.5)]);

        self::assertSame('Tâche test', $session->flightPlan);
        self::assertSame(['Glide', 'Climb', 'Outlanded'], array_map(static fn (Flight $flight): string => $flight->getPilot(), $session->flights));
    }

    public function testThePodiumRewardsMoreThanSpeed(): void
    {
        $podium = FlightSession::of([$this->flight('Glide'), $this->flight('Climb', 1.5)])->podium();
        $winners = array_column(array_map(static fn (array $title): array => [$title['title'], $title['flight']->getPilot()], $podium), 1, 0);

        self::assertSame('Climb', $winners['Meilleur grimpeur']);
        self::assertSame('Glide', $winners['Meilleure finesse']);
        self::assertEqualsWithDelta(3.0, $podium[1]['value'], 0.2);
    }

    private function flight(string $pilot, float $heightScale = 1.0, float $finishX = 7000.0): Flight
    {
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time, $fix->latitude, $fix->longitude, 1500.0 + ($fix->altitude - 1500.0) * $heightScale), FlightSamples::glideThermalGlide());
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000), $point('Arrivée', $finishX, 500)];
        $track = new Track('igc', 'Condor 3', $pilot, null, null, null, null, 'Tâche test', $task, $fixes, true);

        return new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', null, new \DateTimeImmutable());
    }

    private function outlanded(): Flight
    {
        return $this->flight('Outlanded', 1.0, 20000.0);
    }
}
