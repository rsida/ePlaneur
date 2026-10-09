<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Fix;
use App\Flight\Reader\FtrReader;
use App\Flight\Reader\IgcReader;
use App\Flight\Reader\TrackReader;
use PHPUnit\Framework\TestCase;

/**
 * Discontinuities: Condor's jumps of hundreds of metres cut the track, a hard pull-up does not.
 */
final class TrackReaderTest extends TestCase
{
    public function testAJumpOfCondorCutsTheTrackAndAPullUpDoesNot(): void
    {
        $fixes = FlightSamples::glideThermalGlide();
        // A pull-up of 45 m in one second at 60 s, and a jump of 500 m at 300 s (after a crash)
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time, $fix->latitude, $fix->longitude, $fix->altitude + ($fix->time >= 60 ? 45 : 0) + ($fix->time >= 300 ? 500 : 0)), $fixes);

        $track = $this->reader()->read(FlightSamples::igc($fixes));

        // The part before the jump, pull-up included; an upward jump ends it at the altitude it had
        // (Condor lifts the glider at the very fix where it crosses the finish)
        self::assertCount(301, $track->fixes);
        $last = $track->fixes[300];
        self::assertSame(300.0, $last->time);
        self::assertEqualsWithDelta($track->fixes[299]->altitude, $last->altitude, 0.01);
    }

    private function reader(): TrackReader
    {
        return new TrackReader(new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')), new IgcReader());
    }
}
