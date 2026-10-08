<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Geo\Geo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Condor positions to latitude and longitude: turnpoints of a flight plan on the AA3 landscape
 * against their coordinates in the landscape's SeeYou file (AA3.cup).
 */
final class LandscapeTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, float, float}>
     */
    public static function turnpoints(): iterable
    {
        yield 'Rocher Chanteme' => [857474.625, 201970.84375, 44 + 59.267 / 60, 5 + 13.467 / 60];
        yield 'Pic de Bure' => [803036.0625, 159497.609375, 44 + 37.603 / 60, 5 + 56.053 / 60];
    }

    #[DataProvider('turnpoints')]
    public function testCondorPositionsAreConvertedWithTheLandscapeProjection(float $x, float $y, float $latitude, float $longitude): void
    {
        $landscape = (new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml'))->find('aa3');
        self::assertNotNull($landscape);

        [$lat, $lon] = $landscape->toLatLon($x, $y);

        self::assertLessThan(5, Geo::distance($lat, $lon, $latitude, $longitude), 'Within the precision of the .cup file');
    }

    public function testUnknownLandscapes(): void
    {
        self::assertNull((new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml'))->find('Atlantis'));
    }
}
