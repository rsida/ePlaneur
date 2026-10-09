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

    public function testEachFlightKnowsItsRankAndTheThermalsOfTheRace(): void
    {
        $glide = $this->flight('Glide');
        $climb = $this->flight('Climb', 1.5);
        $session = FlightSession::of([$glide, $climb]);

        // Same glides, better climbs (3 m/s): ties share their rank
        self::assertSame(['count' => 2, 'climb' => 1, 'glide' => 1], $session->ranks($climb));
        self::assertSame(2, $session->ranks($glide)['climb']);

        $map = $session->thermalMap();
        self::assertCount(2, $map['thermals'], 'One thermal per flight');
        self::assertEqualsWithDelta(3.0, max(array_column($map['thermals'], 'climb')), 0.2);
        self::assertEqualsWithDelta(120 - 34, $map['thermals'][0]['from'], 12, 'Seconds after crossing the start line (34 s)');
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
