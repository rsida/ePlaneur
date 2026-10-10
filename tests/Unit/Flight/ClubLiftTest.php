<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Club\ClubLift;
use App\Flight\Fix;
use App\Flight\Track;
use PHPUnit\Framework\TestCase;

/**
 * The lift of the club from two flights: the thermal of FlightSamples, and a straight climb (slope
 * lift) whose wind comes from the drift of its thermals (none here: unknown).
 */
final class ClubLiftTest extends TestCase
{
    public function testThermalsAndSlopesOfTheFlights(): void
    {
        $slope = [];
        for ($t = 0; $t < 300; ++$t) {
            $slope[] = new Fix((float) $t, ...FlightSamples::toLatLon(25.0 * $t, 0), altitude: 1500.0 + ($t < 150 ? -$t : -150 + 1.5 * ($t - 150)));
        }
        $lift = ClubLift::of([self::flight(FlightSamples::glideThermalGlide()), self::flight($slope)]);

        self::assertSame(2, $lift['flights']);
        self::assertCount(1, $lift['thermals']);
        [$latitude, $longitude, $climb, $bottom, $top] = $lift['thermals'][0];
        // Centre of the six circles: 3,570 m east, 100 m north
        $centre = FlightSamples::toLatLon(3570.0, 100.0);
        self::assertEqualsWithDelta($centre['latitude'], $latitude, 0.0002);
        self::assertEqualsWithDelta($centre['longitude'], $longitude, 0.0003);
        self::assertEqualsWithDelta(2.0, $climb, 0.15);
        self::assertEqualsWithDelta(1381, $bottom, 15);
        self::assertEqualsWithDelta(1741, $top, 15);

        self::assertCount(1, $lift['slopes']);
        self::assertNull($lift['slopes'][0]['windFrom']);
        self::assertEqualsWithDelta(1.5, $lift['slopes'][0]['climb'], 0.2);
        self::assertGreaterThan(20, \count($lift['slopes'][0]['path']), 'A point every 5 s');
    }

    /**
     * @param list<Fix> $fixes
     */
    private static function flight(array $fixes): Flight
    {
        $track = new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, 'AA3', null, null, [], $fixes, true);

        return new Flight((new FlightAnalyzer())->analyze($track), 'f.igc', 'f.igc', null, new \DateTimeImmutable());
    }
}
