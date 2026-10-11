<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Condor\CondorReport;
use App\Flight\Condor\LandscapeRegistry;
use App\Flight\FlightClouds;
use App\Flight\Track;
use PHPUnit\Framework\TestCase;

/**
 * The cumulus over the thermals: at the cloud base of the weather zone above the ground under the
 * thermal, none when the inversion is under it. The sample thermal climbs 360 m at 45° N, 5° E
 * (AA3), over a ground of 500 m.
 */
final class FlightCloudsTest extends TestCase
{
    public function testACloudFormsAtTheCloudBaseAboveTheGround(): void
    {
        // 20 °C and 12 °C at the ground: base 1,000 m above it, under the inversion
        $clouds = $this->clouds($this->zone('Base', 20, 12, 2500));

        self::assertCount(1, $clouds);
        self::assertSame(1500, $clouds[0]['base']);
        self::assertSame('Base', $clouds[0]['zone']);
        self::assertEqualsWithDelta(120, $clouds[0]['at'], 15, 'when the thermal begins');
    }

    public function testNoCloudWhenTheInversionIsUnderTheCloudBase(): void
    {
        self::assertSame([], $this->clouds($this->zone('Base', 20, 12, 1400)), 'a blue thermal');
    }

    public function testTheThermalTakesTheWeatherOfTheZoneAroundIt(): void
    {
        $around = [[700000, 0], [1000000, 0], [1000000, 300000], [700000, 300000]];
        $elsewhere = [[950000, 0], [1000000, 0], [1000000, 300000], [950000, 300000]];

        self::assertSame(['Plaine', 1000], $this->first($this->clouds($this->zone('Base', 20, 12, 2500), $this->zone('Plaine', 20, 16, 2500, $around))));
        self::assertSame(['Base', 1500], $this->first($this->clouds($this->zone('Base', 20, 12, 2500), $this->zone('Ailleurs', 20, 16, 2500, $elsewhere))));
    }

    /**
     * @param list<array{at: int, latitude: float, longitude: float, base: int, zone: string}> $clouds
     *
     * @return array{string, int}
     */
    private function first(array $clouds): array
    {
        self::assertNotEmpty($clouds);

        return [$clouds[0]['zone'], $clouds[0]['base']];
    }

    /**
     * @param list<array{int, int}> $polygon
     *
     * @return array<string, int|float|string>
     */
    private function zone(string $name, float $temperature, float $dew, float $inversion, array $polygon = []): array
    {
        $zone = ['Name' => $name, 'ThermalsTemp' => $temperature, 'ThermalsDew' => $dew, 'ThermalsInversionheight' => $inversion, 'PointCount' => \count($polygon)];
        foreach ($polygon as $n => [$x, $y]) {
            $zone['Polygon'.$n.'X'] = $x;
            $zone['Polygon'.$n.'Y'] = $y;
        }

        return $zone;
    }

    /**
     * The clouds of the sample flight exported in IGC by Condor with these weather zones.
     *
     * @param array<string, int|float|string> ...$zones the base one first
     *
     * @return list<array{at: int, latitude: float, longitude: float, base: int, zone: string}>
     */
    private function clouds(array ...$zones): array
    {
        $plan = ['[Task]', 'Landscape=AA3', 'Count=0', '[GameOptions]', 'StartTime=12', '[Weather]', 'WZCount='.\count($zones)];
        foreach ($zones as $k => $zone) {
            $plan[] = '[WeatherZone'.$k.']';
            foreach ($zone as $key => $value) {
                $plan[] = $key.'='.$value;
            }
        }
        $report = new CondorReport(null, null, null, null, null, null, null, null, null, [], implode("\n", $plan)."\n");
        $track = new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, 'AA3', null, null, [], FlightSamples::glideThermalGlide(), true, report: $report);
        $analysis = (new FlightAnalyzer())->analyze($track);
        $analysis = $analysis->withTerrain(array_fill(0, \count($analysis->series['time']), 500.0), []);
        $flight = new Flight($analysis, 'sample.igc', 'sample.igc', null, new \DateTimeImmutable('2026-10-11'));

        return (new FlightClouds(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')))->of($flight);
    }
}
