<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Terrain;

use App\Flight\Analysis\FlightAnalysis;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\Terrain\FlightTerrain;
use App\Flight\Terrain\Terrain;
use App\Flight\Track;
use App\Tests\Unit\Flight\FlightSamples;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The relief under a flight: a slope rising to the north (30 %, 17°) under the straight climbs of
 * FlightSamples, the wind blowing from the south or the north.
 */
final class FlightTerrainTest extends TestCase
{
    public function testHeightAboveTheGroundAndSlopesFacingTheWind(): void
    {
        $analysis = (new FlightTerrain(self::slope()))->apply(self::straightLift(1100.0), ['from' => 180.0, 'speed' => 36.0])->toArray();

        // Ground 0.3 m a metre north of 45° N: under the track, at 0 m north
        self::assertEqualsWithDelta(1000.0, $analysis['series']['ground'][0], 1.0);
        $cells = $analysis['slopes']['cells'];
        self::assertNotEmpty($cells);
        // 10 m/s × sin(16.7°): about 2.9 m/s, on every cell (the slope is the same everywhere)
        self::assertEqualsWithDelta(2.9, $cells[0][2], 0.1);
        self::assertSame(['from' => 180.0, 'speed' => 36.0], $analysis['slopes']['wind']);
        // Low over the slope: slope lift
        self::assertFalse($analysis['phases'][1]['wave']);

        // The wind from the north blows down the slope: nothing expected
        self::assertSame([], (new FlightTerrain(self::slope()))->apply(self::straightLift(1100.0), ['from' => 0.0, 'speed' => 36.0])->toArray()['slopes']['cells']);
    }

    public function testStraightLiftHighAndAwayFromSlopesIsWave(): void
    {
        // Wind from the north: no slope works; 1,500 m above the ground
        $analysis = (new FlightTerrain(self::slope()))->apply(self::straightLift(2500.0), ['from' => 0.0, 'speed' => 36.0])->toArray();

        self::assertTrue($analysis['phases'][1]['wave']);
    }

    public function testWithoutTilesNothingChanges(): void
    {
        $terrain = new Terrain(new MockHttpClient(), new NullLogger(), sys_get_temp_dir(), '');
        $analysis = (new FlightTerrain($terrain))->apply(self::straightLift(1100.0))->toArray();

        self::assertNull($analysis['series']['ground']);
        self::assertNull($analysis['phases'][1]['wave']);
    }

    /** Ground at 1,000 m at 45° N, rising 0.3 m a metre to the north. */
    private static function slope(): Terrain
    {
        return new class extends Terrain {
            public function __construct()
            {
            }

            public function elevation(float $latitude, float $longitude): float
            {
                return 1000.0 + 0.3 * ($latitude - FlightSamples::LATITUDE) * 111320.0;
            }
        };
    }

    /** A glide then straight flight to the east climbing 1.5 m/s (a "lift" phase), from `$altitude`. */
    private static function straightLift(float $altitude): FlightAnalysis
    {
        $fixes = [];
        for ($t = 0; $t < 300; ++$t) {
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon(25.0 * $t, 0), altitude: $altitude + ($t < 150 ? -$t : -150 + 1.5 * ($t - 150)));
        }

        return (new FlightAnalyzer())->analyze(new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, [], $fixes, true));
    }
}
