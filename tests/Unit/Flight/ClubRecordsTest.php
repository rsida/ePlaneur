<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Club\Badge;
use App\Flight\Club\ClubRecords;
use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\TestCase;

/**
 * Records, season ranking and badges from flights built on the sample flight of FlightSamples
 * (7 km task finished in about 6 min, 1,741 m at most).
 */
final class ClubRecordsTest extends TestCase
{
    public function testRecordsPerTaskAndLandscape(): void
    {
        $fast = $this->flight('Rapide', 'Tâche A');
        $slow = $this->flight('Lent', 'Tâche A', slower: 2.0);
        $other = $this->flight('Autre', 'Tâche B', finish: 20000);

        $tasks = ClubRecords::byTask([$slow, $fast, $other]);
        self::assertCount(1, $tasks, 'Task B was not finished');
        self::assertSame('Tâche A', $tasks[0]['plan']);
        self::assertSame($fast, $tasks[0]['best']);
        self::assertSame(2, $tasks[0]['count']);

        $landscapes = ClubRecords::byLandscape([$slow, $fast]);
        self::assertSame('Condor 3', $landscapes[0]['landscape'], 'No landscape: the simulator');
        self::assertSame($slow, $landscapes[0]['duration']);
    }

    public function testSeasonRankingByKilometresOfFinishedTasks(): void
    {
        $alice = (new User())->setEmail('alice@example.org')->setDisplayName('Alice');
        $bob = (new User())->setEmail('bob@example.org')->setDisplayName('Bob');
        $flights = [
            $this->flight('A', 'Tâche A', owner: $alice),
            $this->flight('A', 'Tâche A', owner: $alice),
            $this->flight('B', 'Tâche A', owner: $bob),
            $this->flight('B', 'Tâche B', owner: $bob, finish: 20000),
            $this->flight('B', 'Tâche A', owner: $bob, date: '2025-06-01'),
        ];

        $ranking = ClubRecords::season($flights, 2026);
        self::assertSame(['Alice', 'Bob'], array_map(static fn (array $row): string => $row['pilot']->getDisplayName(), $ranking));
        self::assertSame(2, $ranking[0]['tasks']);
        self::assertEqualsWithDelta(13.0, $ranking[0]['kilometres'], 0.1);
        self::assertSame(2, $ranking[1]['flights'], 'The 2025 flight is in another season');
        self::assertSame([2026, 2025], ClubRecords::seasons($flights));
    }

    public function testBadgesGiveTheFirstFlightThatEarnedThem(): void
    {
        $unfinished = $this->flight('A', 'Tâche', finish: 20000, date: '2026-01-01');
        $finished = $this->flight('A', 'Tâche', date: '2026-02-01');
        $earned = array_column(array_map(static fn (array $item): array => [$item['badge']->value, $item['flight']], Badge::earned([$unfinished, $finished])), 1, 0);

        self::assertSame($finished, $earned[Badge::FirstTask->value]);
        self::assertNull($earned[Badge::Distance100->value]);
        self::assertNull($earned[Badge::Gain1000->value], '360 m gained at most');
    }

    private function flight(string $pilot, string $plan, float $slower = 1.0, float $finish = 7000, ?User $owner = null, string $date = '2026-03-01'): Flight
    {
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time * $slower, $fix->latitude, $fix->longitude, $fix->altitude), FlightSamples::glideThermalGlide());
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 3', $pilot, null, null, null, null, $plan, [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', $finish, 500)], $fixes, true, new \DateTimeImmutable($date));

        return new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable($date));
    }
}
