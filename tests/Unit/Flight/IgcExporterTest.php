<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Entity\Flight;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\IgcExporter;
use App\Flight\Reader\IgcReader;
use App\Flight\Track;
use App\Flight\Turnpoint;
use PHPUnit\Framework\TestCase;

/**
 * The IGC export of a flight reads back as the same flight.
 */
final class IgcExporterTest extends TestCase
{
    public function testTheExportReadsBackWithItsTask(): void
    {
        $point = static fn (string $name, float $x, float $radius, float $angle = 360.0): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius, angle: $angle);
        $task = [$point('Terrain', 0, 3000), $point('Départ', 0, 1000, 180), $point('Arrivée', 7000, 500)];
        $track = new Track('ftr', 'Condor 3', 'Jérôme Pilote', 'JP1', 'Diana2', 'AA3', 1, 'C3-s117_Romans-Bure_15m_AA3 ePlaneur', $task, FlightSamples::glideThermalGlide(), true);
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.ftr', 'file.ftr', null, new \DateTimeImmutable('2026-10-08 20:00'));
        $exporter = new IgcExporter();

        $read = (new IgcReader())->read($exporter->export($flight));

        self::assertSame('Jerome Pilote', $read->pilot, 'ASCII');
        self::assertSame('JP1', $read->competitionId);
        self::assertSame('C3-s117_Romans-Bure_15m_AA3 ePlaneur', $read->flightPlan);
        self::assertCount(420, $read->fixes);
        self::assertEqualsWithDelta($track->fixes[200]->latitude, $read->fixes[200]->latitude, 0.00002);
        self::assertSame(['Terrain', 'Depart', 'Arrivee'], array_map(static fn (Turnpoint $p): string => $p->name, $read->turnpoints), 'ASCII names');
        self::assertSame([1000.0, 180.0], [$read->turnpoints[1]->radius, $read->turnpoints[1]->angle], 'Zones as Condor writes them');
        self::assertSame('2026-10-08_JP1_C3-s117.igc', $exporter->fileName($flight));
    }
}
