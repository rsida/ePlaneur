<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Session\Penalties;
use App\Session\TaskSettings;
use PHPUnit\Framework\TestCase;

/**
 * The penalties of a flight measured from its track: a start 14.5 km/h too fast and 20 m too
 * high, a finish 30 m too low, against the official total of the results.
 */
final class PenaltiesTest extends TestCase
{
    public function testThePenaltiesTheTrackShowsAreMeasuredAndTheRestIsLeftToTheServer(): void
    {
        $penalties = Penalties::of($this->analysis(completed: true), $this->settings(), 100.0);

        $lost = array_column($penalties['rules'], 'lost', 'key');
        self::assertSame(29.0, $lost['StartSpeed']);
        self::assertSame(20.0, $lost['HighStart']);
        self::assertSame(30.0, $lost['LowFinish']);
        self::assertNull($lost['CloudFlying'], 'cloud flying does not show in the track');
        self::assertArrayNotHasKey('WindowCollision', $lost, 'a rule without points is left out');
        self::assertSame(79.0, $penalties['estimated']);
        self::assertSame(21.0, $penalties['other'], 'what the server counted beyond the track');
        self::assertSame(100, array_column($penalties['rules'], 'at', 'key')['StartSpeed'], 'the moment of the start');
    }

    public function testAFlightThatDidNotFinishHasNoFinishPenalty(): void
    {
        $penalties = Penalties::of($this->analysis(completed: false), $this->settings());

        self::assertNull(array_column($penalties['rules'], 'lost', 'key')['LowFinish']);
        self::assertSame(49.0, $penalties['estimated']);
        self::assertNull($penalties['other'], 'no official total without results');
    }

    public function testThePointsCondorCountedAreExact(): void
    {
        // IGC exported by Condor: 0.2 point for the start speed, 200 for cloud flying
        $analysis = $this->analysis(completed: true) + ['condor' => ['penaltyPoints' => 200.2, 'penalties' => ['StartSpeed' => 0.2, 'CloudFlying' => 200.0]]];
        $penalties = Penalties::of($analysis, $this->settings(), 200.0);

        self::assertTrue($penalties['exact']);
        self::assertSame(200.2, $penalties['official'], 'the total of Condor, not the rounded one of the results');
        $rules = array_column($penalties['rules'], null, 'key');
        self::assertSame(0.2, $rules['StartSpeed']['official']);
        self::assertSame(170.1, $rules['StartSpeed']['measured'], 'the limit plus the points over the rate');
        self::assertSame(200.0, $rules['CloudFlying']['official']);
        self::assertSame(0.0, $rules['HighStart']['official'], 'no line: no point lost');
        self::assertSame(1520.0, $rules['HighStart']['measured'], 'the measure of the track is kept');
    }

    private function settings(): TaskSettings
    {
        return TaskSettings::fromArray([
            'source' => 'fpl',
            'startMaxHeight' => 1500,
            'finishMinHeight' => 330,
            'maxStartSpeed' => 170,
            'penalties' => ['CloudFlying' => 200, 'WindowCollision' => 0, 'StartSpeed' => 2, 'HighStart' => 1, 'LowFinish' => 1],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function analysis(bool $completed): array
    {
        $point = static fn (string $name, ?int $reached, ?float $altitude, ?float $speed): array => ['name' => $name, 'reached' => $reached, 'reachedAltitude' => $altitude, 'reachedSpeed' => $speed];

        return ['task' => [
            'turnpoints' => [$point('Terrain', null, null, null), $point('Départ', 100, 1520.0, 184.5), $point('Col', 500, 1400.0, 150.0), $point('Arrivée', $completed ? 900 : null, $completed ? 300.0 : null, $completed ? 160.0 : null)],
            'completed' => $completed,
        ]];
    }
}
