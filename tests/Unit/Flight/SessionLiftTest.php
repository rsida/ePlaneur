<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\SessionLift;
use App\Flight\Track;
use PHPUnit\Framework\TestCase;

/**
 * The thermal another pilot of the race climbed in (FlightSamples: centred 3,570 m east and 100 m
 * north, from 1,381 to 1,741 m), seen from flights that pass by.
 */
final class SessionLiftTest extends TestCase
{
    public function testAThermalPassedCloseWithoutTurningIsMissed(): void
    {
        $other = self::flight(FlightSamples::glideThermalGlide(), 'FDU');
        // Straight to the east 400 m north of the track of the other pilot: 300 m from the thermal
        $lift = SessionLift::around(self::analysis(self::straight(400.0, 1500.0)), [$other, self::flight(FlightSamples::glideThermalGlide(), 'HL4')]);

        self::assertCount(1, $lift['thermals'], 'The same thermal for both pilots');
        $thermal = $lift['thermals'][0];
        self::assertSame(['FDU', 'HL4'], $thermal['pilots']);
        self::assertEqualsWithDelta(2.0, $thermal['climb'], 0.15);
        self::assertEqualsWithDelta(1381.0, $thermal['bottom'], 15.0);
        self::assertFalse($thermal['used']);
        self::assertTrue($thermal['missed']);
        self::assertEqualsWithDelta(300.0, $thermal['closest']['distance'], 5.0);
        self::assertEqualsWithDelta(119, $thermal['closest']['at'], 1, 'Abeam 3,570 m east at 30 m/s');
    }

    public function testAThermalFarOrTooHighOrUsedIsNotMissed(): void
    {
        $other = [self::flight(FlightSamples::glideThermalGlide(), 'FDU')];

        self::assertFalse(SessionLift::around(self::analysis(self::straight(1500.0, 1500.0)), $other)['thermals'][0]['missed'], 'Too far');
        self::assertFalse(SessionLift::around(self::analysis(self::straight(400.0, 2500.0)), $other)['thermals'][0]['missed'], 'Far above its top');
        // Flown straight through it, climbing 1.5 m/s for 15 s: dolphin flight, not missed
        $through = self::straight(100.0, 1500.0);
        foreach ($through as $t => $fix) {
            $through[$t] = new Fix($fix->time, $fix->latitude, $fix->longitude, 1500.0 - 0.5 * $t + 2.0 * max(0, min(15, $t - 112)));
        }
        $crossed = SessionLift::around(self::analysis($through), $other)['thermals'][0];
        self::assertTrue($crossed['crossed']);
        self::assertFalse($crossed['missed']);
        $used = SessionLift::around(self::analysis(FlightSamples::glideThermalGlide()), $other)['thermals'][0];
        self::assertTrue($used['used']);
        self::assertFalse($used['missed']);
    }

    /**
     * @return list<Fix>
     */
    private static function straight(float $north, float $altitude): array
    {
        $fixes = [];
        for ($t = 0; $t < 300; ++$t) {
            $fixes[] = new Fix((float) $t, ...FlightSamples::toLatLon(30.0 * $t, $north), altitude: $altitude - 0.5 * $t);
        }

        return $fixes;
    }

    /**
     * @param list<Fix> $fixes
     *
     * @return array<string, mixed>
     */
    private static function analysis(array $fixes): array
    {
        return (new FlightAnalyzer())->analyze(new Track('igc', 'Condor 3', 'Jeanne Pilote', null, null, null, null, null, [], $fixes, true))->toArray();
    }

    /**
     * @param list<Fix> $fixes
     */
    private static function flight(array $fixes, string $competitionId): Flight
    {
        $track = new Track('igc', 'Condor 3', 'Pilote '.$competitionId, $competitionId, null, null, null, null, [], $fixes, true);

        return new Flight((new FlightAnalyzer())->analyze($track), 'f.igc', 'f.igc', null, new \DateTimeImmutable());
    }
}
