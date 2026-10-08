<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Training\TrainingStep;
use PHPUnit\Framework\TestCase;

/**
 * Advice from the analysis of a flight (FlightAnalysis::toArray()), built by hand: statistics,
 * thermals and a straight track of one point per second.
 */
final class FlightAdvisorTest extends TestCase
{
    public function testAGoodFlightIsSaidSo(): void
    {
        $advice = $this->advise(['circlingShare' => 0.2, 'climbAverage' => 2.5, 'leftShare' => 0.5, 'glideSpeed' => 150.0], [[120, 300, 2.5, 'left'], [120, 300, 2.5, 'right']], completed: true);

        self::assertSame(['Peu de temps en spirale'], array_map(static fn (Advice $item): string => $item->title, $advice));
        self::assertSame(Advice::GOOD, $advice[0]->kind);
    }

    public function testWhatToWorkOnIsTiedToTheTrainingPath(): void
    {
        $thermals = [[300, 600, 2.0, 'left'], [120, 60, 0.5, 'left'], [120, 70, 0.6, 'left'], [40, 20, 0.5, 'left'], [40, 10, 0.2, 'left'], [40, 30, 0.7, 'left']];
        $advice = $this->advise(['circlingShare' => 0.45, 'climbAverage' => 1.8, 'leftShare' => 1.0, 'glideSpeed' => 100.0], $thermals);
        $titles = array_map(static fn (Advice $item): string => $item->title, $advice);

        self::assertSame(['Beaucoup de temps en spirale', 'Des thermiques faibles acceptés', 'Des spirales sans gain', 'Toujours du même côté', 'Transitions lentes'], $titles);
        self::assertSame(TrainingStep::MacCready, $advice[0]->step);
        self::assertStringContainsString('45 %', $advice[0]->text);
        self::assertStringContainsString('2 thermiques à moins de 1,0 m/s', $advice[1]->text);
        self::assertStringContainsString('100 % des spirales à gauche', $advice[3]->text);
        self::assertStringContainsString('autour de 131 km/h', $advice[4]->text, '95 + 20 × 1.8');
    }

    public function testSilverBadgeMarks(): void
    {
        // 1 h 10 straight to the east at 20 m/s (84 km), climbing 1,200 m
        $advice = $this->advise([], [], duration: 4200, speed: 20.0, climb: 1200.0);

        self::assertCount(1, $advice);
        self::assertSame(Advice::GOOD, $advice[0]->kind);
        self::assertStringContainsString('✓ 50 km du départ (84,0 km)', $advice[0]->text);

        $advice = $this->advise([], [], duration: 4200, speed: 5.0, climb: 300.0);
        self::assertSame(Advice::INFO, $advice[0]->kind, 'Only one mark: where the flight stands, not a fault');
        self::assertStringContainsString('✗ 1 000 m de gain (300 m)', $advice[0]->text);
    }

    /**
     * @param array<string, float>                   $statistics
     * @param list<array{int, float, float, string}> $thermals   duration, gain, climb, direction
     *
     * @return list<Advice>
     */
    private function advise(array $statistics, array $thermals, bool $completed = false, int $duration = 1800, float $speed = 20.0, float $climb = 0.0): array
    {
        $series = ['time' => [], 'latitude' => [], 'longitude' => [], 'altitude' => []];
        for ($t = 0; $t <= $duration; ++$t) {
            ['latitude' => $latitude, 'longitude' => $longitude] = FlightSamples::toLatLon($speed * $t, 0);
            $series['time'][] = $t;
            $series['latitude'][] = $latitude;
            $series['longitude'][] = $longitude;
            $series['altitude'][] = 500.0 + $climb * $t / $duration;
        }
        $phases = array_map(static fn (array $thermal): array => ['type' => 'thermal', 'duration' => $thermal[0], 'gain' => $thermal[1], 'vario' => $thermal[2], 'direction' => $thermal[3]], $thermals);

        return (new FlightAdvisor())->advise([
            'statistics' => $statistics + ['duration' => $duration, 'takeoff' => 0, 'landing' => $duration],
            'series' => $series,
            'phases' => $phases,
            'task' => ['completed' => $completed],
        ]);
    }
}
