<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Geo\Geo;
use App\Flight\Reader\FtrReader;
use App\Flight\Reader\InvalidTrackException;
use PHPUnit\Framework\TestCase;

/**
 * Condor 3 track: the first 3,000 records of a race on AA3 (Laragne, Ventoux, Le Laser, Lachens,
 * Fayence), tests/fixtures/flights/aa3-laragne-start.ftr.
 */
final class FtrReaderTest extends TestCase
{
    private FtrReader $reader;
    private string $content;

    protected function setUp(): void
    {
        $this->reader = new FtrReader(new LandscapeRegistry(\dirname(__DIR__, 3).'/config/condor/landscapes.yaml'));
        $this->content = (string) file_get_contents(\dirname(__DIR__, 2).'/fixtures/flights/aa3-laragne-start.ftr');
    }

    public function testTheHeaderAndTheRecordsAreRead(): void
    {
        $track = $this->reader->read($this->content);

        self::assertSame('ftr', $track->format);
        self::assertSame('Condor 3', $track->simulator);
        self::assertSame('Romain SIDA', $track->pilot);
        self::assertSame('RS2', $track->competitionId);
        self::assertSame('Diana2', $track->glider);
        self::assertSame('AA3', $track->landscape);
        self::assertSame(1930306707, $track->raceId);
        self::assertStringStartsWith('C3-c060_Laragne-Ventoux-Fayence', (string) $track->flightPlan);
        self::assertFalse($track->exactTimes, 'Condor encrypts the time of each record');

        self::assertSame(['NewTP', 'Laragne Chp', 'Ventoux', 'Le Laser', 'Lachens Mont Ant', 'Fayence'], array_map(static fn ($point): string => $point->name, $track->turnpoints));
        $ventoux = $track->turnpoints[2];
        self::assertLessThan(200, Geo::distance($ventoux->latitude, $ventoux->longitude, 44.1739, 5.2786), 'Mont Ventoux');
        self::assertSame(1896.0, $ventoux->altitude);
        self::assertSame(3000.0, $track->turnpoints[0]->radius);

        self::assertCount(3000, $track->fixes);
        self::assertEqualsWithDelta(1730.3, $track->fixes[0]->altitude, 0.1);
        self::assertEqualsWithDelta(FtrReader::RECORD_INTERVAL, $track->fixes[1]->time, 1e-9);
        self::assertLessThan(500, Geo::distance($track->fixes[0]->latitude, $track->fixes[0]->longitude, $track->turnpoints[0]->latitude, $track->turnpoints[0]->longitude), 'The flight starts at the departure point');
    }

    public function testUnknownLandscapesAreReported(): void
    {
        $content = substr_replace($this->content, "\x08Atlantis", 0xDF, 9);

        $this->expectException(InvalidTrackException::class);
        $this->expectExceptionMessage('« Atlantis »');
        $this->reader->read($content);
    }

    public function testCondor2TracksAskForAnIgcExport(): void
    {
        $content = substr_replace($this->content, pack('V', 2200), 3, 4);

        $this->expectException(InvalidTrackException::class);
        $this->expectExceptionMessage('exportez le vol en IGC');
        $this->reader->read($content);
    }
}
