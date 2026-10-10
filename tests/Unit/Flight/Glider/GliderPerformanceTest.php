<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Glider;

use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\Glider\GliderPerformance;
use App\Training\TrainingStep;
use PHPUnit\Framework\TestCase;

/**
 * A flight against the polar of its glider: an analysis built by hand, a thermal drifting with a
 * wind from the west and glides to the north.
 */
final class GliderPerformanceTest extends TestCase
{
    public function testTheMassComesFromTheWaterOfTheTrack(): void
    {
        $glider = GliderSamples::glider();
        $dry = GliderPerformance::of($glider, $this->analysis(water: 0, glideSpeed: 120.0));
        $wet = GliderPerformance::of($glider, $this->analysis(water: 150, glideSpeed: 120.0));

        // 30 kg/m² × 10 m², plus a kilogram per litre
        self::assertSame(300.0, $dry->mass);
        self::assertSame(450.0, $wet->mass);
        self::assertSame(45.0, $wet->wingLoading);
        self::assertEqualsWithDelta($dry->bestGlide['ratio'], $wet->bestGlide['ratio'], 0.01);
        self::assertEqualsWithDelta($dry->bestGlide['speed'] * sqrt(1.5), $wet->bestGlide['speed'], 0.01);
        self::assertGreaterThan($dry->speedToFly['speed'] ?? 0, $wet->speedToFly['speed'] ?? 0);
        self::assertSame(500.0, $glider->massWith(400), 'At most the maximum mass');
    }

    public function testTheAirspeedOfAGlideTakesTheWindAway(): void
    {
        // Glides to the north at 120 km/h over the ground, a wind of 20 km/h from the west
        $performance = GliderPerformance::of(GliderSamples::glider(), $this->analysis(water: 0, glideSpeed: 120.0));

        self::assertNotNull($performance->wind);
        self::assertEqualsWithDelta(20.0, $performance->wind['speed'], 0.01);
        self::assertEqualsWithDelta(270.0, $performance->wind['from'], 0.01);
        self::assertCount(3, $performance->glides);
        self::assertEqualsWithDelta(sqrt(120 ** 2 + 20 ** 2), $performance->glides[0]['airspeed'], 0.5);
        // 300 m lost in 180 s
        self::assertEqualsWithDelta(300 / 180, $performance->glides[0]['sink'], 0.001);
        self::assertSame([], array_filter($performance->chartData()['curve'], static fn (array $point): bool => $point[0] < 66 || $point[0] > 270));
    }

    public function testTheNettoOfAGlideIsTheAirAgainstThePolar(): void
    {
        $performance = GliderPerformance::of(GliderSamples::glider(), $this->analysis(water: 0, glideSpeed: 120.0));
        $glide = $performance->glides[0];
        $polar = GliderSamples::glider()->getPolar();
        // Lost 1.67 m/s where the polar sinks less: sinking air
        $expected = ($polar->sinkAt($glide['airspeed']) ?? 0.0) - 300 / 180;

        self::assertNotNull($glide['netto']);
        self::assertLessThan(0.0, $glide['netto']);
        self::assertEqualsWithDelta($expected, $glide['netto'], 0.001);
        self::assertEqualsWithDelta($expected, $performance->glideNetto, 0.001);
        // Second by second, in the glides only
        self::assertEqualsWithDelta($expected, $performance->netto[90], 0.01);
        self::assertNull($performance->netto[300], 'Circling');
        self::assertSame($polar->macCreadyFor($glide['airspeed']), $glide['macCready']);
    }

    public function testTheMacCreadySettingOfTheGlides(): void
    {
        $glider = GliderSamples::glider();
        $polar = $glider->getPolar();
        // Glides at the speed to fly of 2.5 m/s, without wind
        $speed = $polar->speedToFly(2.5)['speed'];
        $performance = GliderPerformance::of($glider, $this->analysis(water: 0, glideSpeed: $speed, wind: false));

        self::assertSame(2.5, $performance->glides[0]['macCready']);
        self::assertEqualsWithDelta(2.5, $performance->glideMacCready, 0.001);
        self::assertSame(2.5, $performance->chartData()['glides'][0][4]);
    }

    public function testTheCoachComparesTheGlidesWithTheMacCreadySpeed(): void
    {
        $glider = GliderSamples::glider();
        $advisor = new FlightAdvisor();
        $slow = GliderPerformance::of($glider, $this->analysis(water: 0, glideSpeed: 90.0));
        $best = (int) round($slow->speedToFly['speed'] ?? 0);

        $advice = array_values(array_filter($advisor->advise($this->analysis(water: 0, glideSpeed: 90.0), null, $slow), static fn (Advice $item): bool => TrainingStep::MacCready === $item->step));
        self::assertSame('Transitions lentes', $advice[0]->title);
        self::assertStringContainsString('polaire du Acme Tester Glider-15 à 30,0 kg/m²', $advice[0]->text);
        self::assertStringContainsString(\sprintf('vitesse de transition de %d km/h (MacCready 2,0)', $best), $advice[0]->text);

        $good = GliderPerformance::of($glider, $this->analysis(water: 0, glideSpeed: $best - 2.0));
        $titles = array_map(static fn (Advice $item): string => $item->title, $advisor->advise($this->analysis(water: 0, glideSpeed: $best - 2.0), null, $good));
        self::assertContains('Vitesse de transition adaptée', $titles);
        self::assertNotContains('Transitions lentes', $titles);
    }

    /**
     * @return array<string, mixed>
     */
    private function analysis(int $water, float $glideSpeed, bool $wind = true): array
    {
        // Three glides of 3 minutes due north, 300 m lost each, around one thermal
        $series = ['time' => [], 'latitude' => [], 'longitude' => [], 'altitude' => [], 'speed' => [], 'vario' => []];
        for ($i = 0; $i <= 900; ++$i) {
            $circling = $i > 180 && $i < 480;
            $series['time'][] = $i;
            $series['latitude'][] = 45.0 + $i * 0.0003;
            $series['longitude'][] = 6.0;
            $series['altitude'][] = 1500.0;
            $series['speed'][] = $circling ? 90.0 : $glideSpeed;
            $series['vario'][] = $circling ? 2.0 : -300 / 180;
        }
        $glide = static fn (int $start): array => ['type' => 'glide', 'start' => $start, 'end' => $start + 180, 'duration' => 180, 'gain' => -300.0, 'distance' => $glideSpeed / 3.6 * 180, 'speed' => $glideSpeed, 'glideRatio' => $glideSpeed / 3.6 * 180 / 300];

        return [
            'water' => $water,
            'statistics' => ['duration' => 900, 'climbAverage' => 2.0, 'glideSpeed' => $glideSpeed],
            'series' => $series,
            'phases' => [
                $glide(0),
                ['type' => 'thermal', 'start' => 180, 'end' => 480, 'duration' => 300, 'gain' => 600.0, 'distance' => 0.0, 'speed' => 90.0, 'vario' => 2.0, 'direction' => 'left', 'windSpeed' => $wind ? 20.0 : null, 'windFrom' => $wind ? 270 : null],
                $glide(480),
                $glide(700),
            ],
        ];
    }
}
