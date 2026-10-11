<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\CondorReport;
use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Reader\FtrReader;
use App\Flight\Reader\IgcReader;
use App\Flight\Reader\InvalidTrackException;
use App\Flight\Reader\TrackReader;
use App\Session\TaskSettings;
use PHPUnit\Framework\TestCase;

/**
 * IGC files: headers, fixes with their time (across midnight), task and Condor's report
 * (discontinuities: TrackReaderTest).
 */
final class IgcReaderTest extends TestCase
{
    public function testFixesHeadersAndTaskAreRead(): void
    {
        $fixes = \array_slice(FlightSamples::glideThermalGlide(), 0, 30);
        $task = "C190226100000000000000102C3-s117 Romans-Bure\r\nC4500000N00500000ETakeoff\r\nC4501000N00510000EStart\r\nC4502000N00520000EFinish";
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
        self::assertSame('C3-s117 Romans-Bure', $track->flightPlan, 'Name of the task declaration');
        self::assertSame(['Takeoff', 'Start', 'Finish'], array_map(static fn ($point): string => $point->name, $track->turnpoints), 'The C record without position is the task header');
    }

    public function testZonesAreReadAndTheLandingIsLeftOut(): void
    {
        // As exported by Condor: take-off, start, two turnpoints, finish, landing, then the zones
        $task = implode("\r\n", [
            'C210626145320210626000004',
            'C4420859N00551927ENewTP', 'C4419240N00547509ELaragne Chp', 'C4410417N00516725EVentoux',
            'C4355680N00542738ELe Laser', 'C4336625N00641793EFayence', 'C4336625N00641793EFayence',
            'LSEEYOU OZ=-1,Style=2,R1=2500m,A1=90,MaxAlt=1400.0m',
            'LSEEYOU OZ=0,Style=1,R1=500m,A1=180,MaxAlt=3000.0m',
            'LSEEYOU OZ=1,Style=1,R1=500m,A1=180,MaxAlt=3000.0m',
            'LSEEYOU OZ=2,Style=3,R1=250m,A1=90,MaxAlt=5000.0m',
        ]);
        $track = (new IgcReader())->read(FlightSamples::igc(\array_slice(FlightSamples::glideThermalGlide(), 0, 5), extraHeaders: $task));

        self::assertNull($track->flightPlan, 'A declaration without a name');
        self::assertSame(['NewTP', 'Laragne Chp', 'Ventoux', 'Le Laser', 'Fayence'], array_map(static fn ($point): string => $point->name, $track->turnpoints));
        self::assertSame([500.0, 2500.0, 500.0, 500.0, 250.0], array_map(static fn ($point): float => $point->radius, $track->turnpoints));
        self::assertSame([360.0, 180.0, 360.0, 360.0, 180.0], array_map(static fn ($point): float => $point->angle, $track->turnpoints));
        self::assertSame([null, 1400.0, 3000.0, 3000.0, 5000.0], array_map(static fn ($point): ?float => $point->maxAltitude, $track->turnpoints), 'MaxAlt of the zones');
    }

    public function testTheReportOfCondorIsRead(): void
    {
        // As exported by Condor for C3-c060 (flight plan cut down to the keys read)
        $report = implode("\r\n", [
            'C210626145320210626000004',
            'C4420859N00551927ENewTP', 'C4419240N00547509ELaragne Chp', 'C4410417N00516725EVentoux', 'C4336625N00641793EFayence', 'C4336625N00641793EFayence',
            'LSEEYOU OZ=-1,Style=2,R1=2500m,A1=90,MaxAlt=1400.0m',
            'LSEEYOU OZ=0,Style=1,R1=500m,A1=180,MaxAlt=3000.0m',
            'LSEEYOU OZ=1,Style=3,R1=250m,A1=90,MaxAlt=5000.0m',
            'LCONFPL[Task]', 'LCONFPLLandscape=AA3', 'LCONFPLCount=4',
            'LCONFPLTPSectorType1=0', 'LCONFPLTPWidth1=0', 'LCONFPLTPHeight1=1400',
            'LCONFPLTPSectorType3=0', 'LCONFPLTPWidth3=260', 'LCONFPLTPHeight3=5000',
            'LCONFPL[Plane]', 'LCONFPLWater=146',
            'LCONFPL[GameOptions]', 'LCONFPLMaxStartGroundSpeed=170', 'LCONFPLPenaltyStartSpeed=2',
            'LCONFPL[Description]', 'LCONFPLText=C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur',
            'LCONFlightInfoRaceID=1930306707',
            'LCONFlightInfoStartTime=14:45:00',
            'LCONFlightInfoFlightTime=01:46:51',
            'LCONFlightInfoTaskStart=15:05:00',
            'LCONFlightInfoTaskTime=01:21:49',
            'LCONFlightInfoPlayerStatus=Finished',
            'LCONFlightInfoDistanceFlown=182.83km',
            'LCONFlightInfoAverageSpeed=134.06km/h',
            'LCONFlightInfoPenaltyPoints=0.2',
            'LCONPenaltiesStartSpeed=0.2',
        ]);
        $track = (new IgcReader())->read(FlightSamples::igc(\array_slice(FlightSamples::glideThermalGlide(), 0, 5), extraHeaders: $report));

        self::assertSame(1930306707, $track->raceId, 'the race id joins the track to its session');
        self::assertSame([260.0, 5000.0], [$track->turnpoints[3]->minAltitude, $track->turnpoints[3]->maxAltitude], 'the minimum finish altitude from the flight plan');
        self::assertSame('AA3', $track->landscape);
        self::assertSame(146, $track->water);
        self::assertSame('C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur', $track->flightPlan, 'the description of the flight plan');
        $condor = $track->report;
        self::assertNotNull($condor);
        self::assertSame([53100, 54300, 6411, 4909], [$condor->startTime, $condor->taskStart, $condor->flightTime, $condor->taskTime]);
        self::assertTrue($condor->isFinished());
        self::assertSame([182.83, 134.06, 0.2], [$condor->distance, $condor->speed, $condor->penaltyPoints]);
        self::assertSame(['StartSpeed' => 0.2], $condor->penalties);
        self::assertSame(170, TaskSettings::fromFpl((string) $condor->flightPlan, 'igc')->maxStartSpeed, 'the flight plan reads as a .fpl file');
        self::assertNull(CondorReport::fromIgcLines(['LCONFlightInfoPlayerStatus=/'])?->status, 'later exports write "/" for the status');
    }

    public function testAnIgcWithoutReportHasNone(): void
    {
        self::assertNull((new IgcReader())->read(FlightSamples::igc(\array_slice(FlightSamples::glideThermalGlide(), 0, 5)))->report);
    }

    public function testTwoFixesInTheSameSecondAreKept(): void
    {
        $fixes = \array_slice(FlightSamples::glideThermalGlide(), 0, 10);
        $igc = str_replace('B100005', 'B100004', FlightSamples::igc($fixes));

        $track = (new IgcReader())->read($igc);
        self::assertCount(10, $track->fixes);
        self::assertSame([4.0, 4.5, 6.0], [$track->fixes[4]->time, $track->fixes[5]->time, $track->fixes[6]->time]);
    }

    public function testFlightsAcrossMidnight(): void
    {
        $track = (new IgcReader())->read(FlightSamples::igc(\array_slice(FlightSamples::glideThermalGlide(), 0, 20), start: 86390));

        self::assertSame(19.0, $track->fixes[19]->time);
    }

    public function testOtherFilesAreRefused(): void
    {
        $reader = new TrackReader(new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml')), new IgcReader());

        $this->expectException(InvalidTrackException::class);
        $reader->read('%PDF-1.7 not a flight');
    }
}
