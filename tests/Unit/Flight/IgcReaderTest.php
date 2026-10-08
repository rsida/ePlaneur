<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Fix;
use App\Flight\Reader\FtrReader;
use App\Flight\Reader\IgcReader;
use App\Flight\Reader\InvalidTrackException;
use App\Flight\Reader\TrackReader;
use PHPUnit\Framework\TestCase;

/**
 * IGC files: headers, fixes with their time (across midnight), task, and the longest continuous
 * part kept by TrackReader.
 */
final class IgcReaderTest extends TestCase
{
    public function testFixesHeadersAndTaskAreRead(): void
    {
        $fixes = \array_slice(FlightSamples::glideThermalGlide(), 0, 30);
        $task = "C1902261000000000000000004\r\nC4500000N00500000ETakeoff\r\nC4501000N00510000EStart\r\nC4502000N00520000EFinish";
        $track = (new IgcReader())->read(FlightSamples::igc($fixes, extraHeaders: $task));

        self::assertSame('igc', $track->format);
        self::assertSame('Condor 3', $track->simulator);
        self::assertSame('Jeanne Pilote', $track->pilot);
        self::assertSame('Duo Discus', $track->glider);
        self::assertSame('JP1', $track->competitionId);
        self::assertSame('2026-02-19', $track->date?->format('Y-m-d'));
        self::assertTrue($track->exactTimes);
        self::assertCount(30, $track->fixes);
        self::assertSame(29.0, $track->fixes[29]->time);
        self::assertEqualsWithDelta($fixes[29]->latitude, $track->fixes[29]->latitude, 0.00002);
        self::assertEqualsWithDelta($fixes[29]->longitude, $track->fixes[29]->longitude, 0.00002);
        self::assertSame(['Takeoff', 'Start', 'Finish'], array_map(static fn ($point): string => $point->name, $track->turnpoints), 'The C record without position is the task header');
    }

    public function testFlightsAcrossMidnight(): void
    {
        $track = (new IgcReader())->read(FlightSamples::igc(\array_slice(FlightSamples::glideThermalGlide(), 0, 20), start: 86390));

        self::assertSame(19.0, $track->fixes[19]->time);
    }

    public function testTheLongestContinuousPartIsKept(): void
    {
        $fixes = \array_slice(FlightSamples::glideThermalGlide(), 0, 120);
        // Condor puts the glider 500 m higher after a crash
        foreach ($fixes as $i => $fix) {
            if ($i >= 100) {
                $fixes[$i] = new Fix($fix->time, $fix->latitude, $fix->longitude, $fix->altitude + 500);
            }
        }
        $reader = new TrackReader(new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')), new IgcReader());

        self::assertCount(100, $reader->read(FlightSamples::igc($fixes))->fixes);
    }

    public function testOtherFilesAreRefused(): void
    {
        $reader = new TrackReader(new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')), new IgcReader());

        $this->expectException(InvalidTrackException::class);
        $reader->read('%PDF-1.7 not a flight');
    }
}
