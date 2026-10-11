<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightDebrief;
use PHPUnit\Framework\TestCase;

/**
 * The guided debrief from an analysis built by hand (FlightAnalysis::toArray()): a towed flight of
 * 950 s, three thermals, and a task of two legs finished at 900 s.
 */
final class FlightDebriefTest extends TestCase
{
    public function testATaskIsToldLegByLegWithItsAdvice(): void
    {
        $advice = [
            new Advice(Advice::TIP, 'Des thermiques faibles acceptés', '…', at: 250, phase: 1),
            new Advice(Advice::GOOD, 'Une arrivée juste', '…', at: 850),
            new Advice(Advice::INFO, 'Toujours du même côté', '…'),
        ];
        $chapters = FlightDebrief::chapters($this->analysis(task: true), $advice);

        self::assertSame(['Le départ', 'Branche 1 · Départ → Col', 'Branche 2 · Col → Arrivée', 'La transition finale', 'L’arrivée et le bilan'], array_column($chapters, 'title'));
        self::assertSame([[0, 100], [100, 500], [500, 800], [800, 900], [900, 950]], array_map(static fn (array $chapter): array => [$chapter['from'], $chapter['to']], $chapters));
        self::assertSame(['Des thermiques faibles acceptés'], array_map(static fn (Advice $item): string => $item->title, $chapters[1]['advice']));
        self::assertSame(['Une arrivée juste'], array_map(static fn (Advice $item): string => $item->title, $chapters[3]['advice']));
        self::assertSame(['Toujours du même côté'], array_map(static fn (Advice $item): string => $item->title, $chapters[4]['advice']), 'advice without a moment closes the debrief');

        // Released at 60 s at 600 m, start at 100 s
        self::assertSame(60, $chapters[0]['figures']['release']);
        self::assertSame(600.0, $chapters[0]['figures']['releaseAltitude']);
        // One thermal of 100 s at 2 m/s in a leg of 400 s
        self::assertSame(1, $chapters[1]['figures']['thermals']);
        self::assertSame(2.0, $chapters[1]['figures']['climb']);
        self::assertSame(0.25, $chapters[1]['figures']['circling']);
        self::assertTrue($chapters[4]['figures']['completed']);
    }

    public function testAFinalGlideBegunBeforeTheLastLegHasNoChapterOfItsOwn(): void
    {
        $analysis = $this->analysis(task: true);
        $analysis['task']['finalGlide']['from'] = 300;
        $chapters = FlightDebrief::chapters($analysis, []);

        self::assertSame(['Le départ', 'Branche 1 · Départ → Col', 'Branche 2 · Col → Arrivée', 'L’arrivée et le bilan'], array_column($chapters, 'title'));
        self::assertSame(300, $chapters[1]['figures']['finalGlide']['from'], 'the leg where it begins says so');
        self::assertNull($chapters[2]['figures']['finalGlide']);
        self::assertSame(100.0, $chapters[3]['figures']['finalGlide']['height'], 'the review gives the arrival');
    }

    public function testAFlightWithoutATaskHasALaunchTheFlightAndAReview(): void
    {
        $chapters = FlightDebrief::chapters($this->analysis(task: false), [new Advice(Advice::TIP, 'Beaucoup de temps en spirale', '…')]);

        self::assertSame(['Le lancement', 'Le vol', 'Le bilan'], array_column($chapters, 'title'));
        self::assertSame(3, $chapters[1]['figures']['thermals']);
        self::assertSame([], $chapters[1]['advice']);
        self::assertCount(1, $chapters[2]['advice']);
    }

    /**
     * @return array<string, mixed>
     */
    private function analysis(bool $task): array
    {
        $altitude = array_map(static fn (int $i): float => 600.0 + $i, range(0, 950));
        $phase = static fn (string $type, int $start, int $end, float $gain): array => ['type' => $type, 'start' => $start, 'end' => $end, 'duration' => $end - $start, 'gain' => $gain];
        $point = static fn (string $name, ?int $reached): array => ['name' => $name, 'reached' => $reached, 'reachedAltitude' => null !== $reached ? 900.0 : null, 'reachedSpeed' => null !== $reached ? 110.0 : null];

        return [
            'launch' => 'aerotow',
            'statistics' => ['takeoff' => 0, 'landing' => 950, 'duration' => 950, 'distance' => 50000.0],
            'series' => ['time' => range(0, 950), 'altitude' => [60 => 600.0] + $altitude, 'ground' => null],
            'phases' => [
                $phase('launch', 0, 60, 300.0),
                $phase('thermal', 200, 300, 200.0),
                $phase('thermal', 600, 700, 100.0),
                $phase('thermal', 750, 800, 50.0),
            ],
            'task' => $task ? [
                'turnpoints' => [$point('Terrain', null), $point('Départ', 100), $point('Col', 500), $point('Arrivée', 900)],
                'legs' => [
                    ['from' => 'Départ', 'to' => 'Col', 'distance' => 20000.0, 'duration' => 400, 'speed' => 180.0],
                    ['from' => 'Col', 'to' => 'Arrivée', 'distance' => 20000.0, 'duration' => 400, 'speed' => 180.0],
                ],
                'finalGlide' => ['from' => 800, 'distance' => 8000.0, 'duration' => 100, 'speed' => 288.0, 'startAltitude' => 1400.0, 'arrivalAltitude' => 1500.0, 'height' => 100.0, 'heightTime' => 50, 'climb' => 1.0],
                'completed' => true,
                'duration' => 800,
                'speed' => 180.0,
            ] : null,
        ];
    }
}
