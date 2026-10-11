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
        self::assertSame(146, $track->water, 'Litres of water ballast');
        self::assertStringStartsWith('C3-c060_Laragne-Ventoux-Fayence', (string) $track->flightPlan);
        self::assertTrue($track->exactTimes, 'Times decoded from the records');

        self::assertSame(['NewTP', 'Laragne Chp', 'Ventoux', 'Le Laser', 'Lachens Mont Ant', 'Fayence'], array_map(static fn ($point): string => $point->name, $track->turnpoints));
        $ventoux = $track->turnpoints[2];
        self::assertLessThan(200, Geo::distance($ventoux->latitude, $ventoux->longitude, 44.1739, 5.2786), 'Mont Ventoux');
        self::assertSame(1896.0, $ventoux->altitude);
        self::assertSame(3000.0, $track->turnpoints[0]->radius);
        self::assertTrue($track->turnpoints[1]->isLine(), 'Start: 180°');
        self::assertSame(360.0, $ventoux->angle);

        self::assertCount(3000, $track->fixes);
        // Times decoded from the records: the IGC export of the flight has these 3,000 records over
        // its first 305 s; records are 0.06 to 0.6 s apart
        self::assertSame(0.0, $track->fixes[0]->time);
        self::assertEqualsWithDelta(305.0, $track->fixes[2999]->time, 1.0);
        foreach ($track->fixes as $i => $fix) {
            if ($i > 0) {
                self::assertGreaterThan($track->fixes[$i - 1]->time, $fix->time);
            }
        }
        self::assertEqualsWithDelta(1730.3, $track->fixes[0]->altitude, 0.1);
        self::assertLessThan(500, Geo::distance($track->fixes[0]->latitude, $track->fixes[0]->longitude, $track->turnpoints[0]->latitude, $track->turnpoints[0]->longitude), 'The flight starts at the departure point');
    }

    public function testTheAttitudeIsRead(): void
    {
        $track = $this->reader->read($this->content);
        $banks = array_map(static fn ($fix): ?float => $fix->bank, $track->fixes);
        $pitches = array_map(static fn ($fix): ?float => $fix->pitch, $track->fixes);

        self::assertNotContains(null, $banks);
        self::assertNotContains(null, $pitches);
        // Five minutes of an airborne start: some turning, the nose within a sensible range
        self::assertGreaterThan(15.0, max(array_map('abs', $banks)));
        self::assertLessThan(60.0, max(array_map('abs', $banks)));
        self::assertLessThan(45.0, max(array_map('abs', $pitches)));

        // Banked to the side the track turns to: right positive
        $fixes = $track->fixes;
        $agree = 0;
        $turning = 0;
        for ($i = 20; $i < \count($fixes) - 20; $i += 10) {
            $before = Geo::bearing($fixes[$i - 20]->latitude, $fixes[$i - 20]->longitude, $fixes[$i]->latitude, $fixes[$i]->longitude);
            $after = Geo::bearing($fixes[$i]->latitude, $fixes[$i]->longitude, $fixes[$i + 20]->latitude, $fixes[$i + 20]->longitude);
            $turn = Geo::turn($before, $after);
            if (abs($turn) > 15.0 && abs((float) $fixes[$i]->bank) > 10.0) {
                ++$turning;
                $agree += ($turn > 0) === ($fixes[$i]->bank > 0) ? 1 : 0;
            }
        }
        self::assertGreaterThan(10, $turning);
        self::assertGreaterThan(0.9, $agree / $turning);
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
