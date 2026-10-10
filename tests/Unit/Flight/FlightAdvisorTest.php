<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Session\TaskSettings;
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

    public function testWanderingTransitionsWideSpiralsAndAHighFinish(): void
    {
        $settings = TaskSettings::fromCondorClub(json_decode((string) file_get_contents(__DIR__.'/../../fixtures/condor/condor-club-task-27105.json'), true));
        $advice = $this->advise(
            ['glideDetour' => 0.31, 'spiralRadius' => 160.0, 'spiralTarget' => 120.0],
            [],
            finalGlide: ['arrivalAltitude' => 638.0, 'climb' => 3.7],
            settings: $settings,
        );
        $titles = array_map(static fn (Advice $item): string => $item->title, $advice);

        self::assertSame(['Des transitions qui serpentent', 'Une spirale large', 'Une arrivée trop haute'], $titles);
        self::assertStringContainsString('31 %', $advice[0]->text);
        // Condor Club: 308 m above the minimum of 330 m, about 84 s of climb at 3.7 m/s
        self::assertStringContainsString('308 m au-dessus du minimum', $advice[2]->text);
        self::assertStringContainsString('1 min 23 s', $advice[2]->text);
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

    public function testCentringLeavingAndLiftCrossed(): void
    {
        $thermals = [[300, 600, 2.0, 'left', 50, 60, 200], [300, 300, 1.0, 'left', 60, 40, 700], [300, 600, 2.0, 'right', 40, 30, 1200]];
        // Before each thermal: 2.4 m/s before a 2 m/s one, 2.1 and 3.0 m/s before a 1 m/s one
        $crossings = [['start' => 100, 'end' => 120, 'vario' => 2.4, 'gain' => 30.0], ['start' => 400, 'end' => 420, 'vario' => 2.1, 'gain' => 40.0], ['start' => 600, 'end' => 620, 'vario' => 3.0, 'gain' => 50.0]];
        $advice = $this->advise(['climbAverage' => 2.0], $thermals, crossings: $crossings);
        $titles = array_map(static fn (Advice $item): string => $item->title, $advice);

        self::assertSame(['Un centrage lent', 'Des sorties tardives', 'Des ascendances laissées pour un thermique plus faible'], $titles);
        self::assertStringContainsString('En moyenne 50 s', $advice[0]->text);
        self::assertSame(TrainingStep::Flaps, $advice[0]->step);
        self::assertStringContainsString('2 min 10 s au total en haut de 3 thermiques', $advice[1]->text);
        self::assertStringContainsString('2 fois', $advice[2]->text);
        self::assertStringContainsString('jusqu’à 3,0 m/s', $advice[2]->text);

        $crossings = [['start' => 100, 'end' => 120, 'vario' => 4.0, 'gain' => 120.0], ['start' => 400, 'end' => 420, 'vario' => 3.5, 'gain' => 90.0]];
        $advice = $this->advise(['climbAverage' => 2.0], [[300, 600, 2.0, 'left', 10, 0, 200], [300, 600, 2.0, 'right', 15, 0, 700], [300, 600, 2.0, 'left', 20, null, 1200]], crossings: $crossings);
        self::assertSame(['Un centrage rapide', 'Du vol en dauphin'], array_map(static fn (Advice $item): string => $item->title, $advice));
        self::assertStringContainsString('210 m gagnés en ligne droite dans 2 ascendances', $advice[1]->text);
    }

    /**
     * @param array<string, float>                                                           $statistics
     * @param list<array{0: int, 1: float, 2: float, 3: string, 4?: int, 5?: ?int, 6?: int}> $thermals   duration, gain, climb, direction, and seconds to centre, stayed at the top and start
     * @param list<array<string, int|float>>                                                 $crossings
     * @param array<string, float>|null                                                      $finalGlide
     *
     * @return list<Advice>
     */
    private function advise(array $statistics, array $thermals, bool $completed = false, int $duration = 1800, float $speed = 20.0, float $climb = 0.0, ?array $finalGlide = null, ?TaskSettings $settings = null, array $crossings = []): array
    {
        $series = ['time' => [], 'latitude' => [], 'longitude' => [], 'altitude' => []];
        for ($t = 0; $t <= $duration; ++$t) {
            ['latitude' => $latitude, 'longitude' => $longitude] = FlightSamples::toLatLon($speed * $t, 0);
            $series['time'][] = $t;
            $series['latitude'][] = $latitude;
            $series['longitude'][] = $longitude;
            $series['altitude'][] = 500.0 + $climb * $t / $duration;
        }
        $phases = array_map(static fn (array $thermal): array => ['type' => 'thermal', 'duration' => $thermal[0], 'gain' => $thermal[1], 'vario' => $thermal[2], 'direction' => $thermal[3], 'entry' => $thermal[4] ?? null, 'exit' => $thermal[5] ?? null, 'start' => $thermal[6] ?? 0], $thermals);

        return (new FlightAdvisor())->advise([
            'statistics' => $statistics + ['duration' => $duration, 'takeoff' => 0, 'landing' => $duration],
            'series' => $series,
            'phases' => $phases,
            'crossings' => $crossings,
            'task' => ['completed' => $completed || null !== $finalGlide, 'finalGlide' => $finalGlide],
        ], $settings);
    }
}
