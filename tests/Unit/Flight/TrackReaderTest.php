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
 * Discontinuities: Condor's moves cut the track, a height recovery is recorded and the track goes
 * on, a hard pull-up is neither.
 */
final class TrackReaderTest extends TestCase
{
    public function testAHeightRecoveryKeepsTheTrackWholeAndAPullUpIsNotOne(): void
    {
        $fixes = FlightSamples::glideThermalGlide();
        // A pull-up of 45 m in one second at 60 s, and a height recovery of 500 m at 300 s
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time, $fix->latitude, $fix->longitude, $fix->altitude + ($fix->time >= 60 ? 45 : 0) + ($fix->time >= 300 ? 500 : 0)), $fixes);

        $track = $this->reader()->read(FlightSamples::igc($fixes));

        self::assertCount(\count($fixes), $track->fixes, 'the flight goes on after the recovery');
        self::assertCount(1, $track->recoveries, 'the pull-up is not a recovery');
        self::assertSame(300.0, $track->recoveries[0]['time']);
        self::assertEqualsWithDelta(500.0, $track->recoveries[0]['height'], 3.0, 'with the climb of that second');
    }

    public function testAMoveOfCondorCutsTheTrack(): void
    {
        $fixes = FlightSamples::glideThermalGlide();
        // The glider put 5 km away at 300 s (a plane recovery): the longest part is kept
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time, $fix->latitude + ($fix->time >= 300 ? 0.05 : 0), $fix->longitude, $fix->altitude), $fixes);

        $track = $this->reader()->read(FlightSamples::igc($fixes));

        self::assertSame(max(300, \count($fixes) - 300), \count($track->fixes));
        self::assertSame([], $track->recoveries);
    }

    private function reader(): TrackReader
    {
        return new TrackReader(new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')), new IgcReader());
    }
}
