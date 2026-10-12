<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\CondorAirspaces;
use PHPUnit\Framework\TestCase;

/**
 * Airspaces of an OpenAir `.air` file as Condor's landscapes ship them (AA3.air, Slovenia3.air).
 */
final class CondorAirspacesTest extends TestCase
{
    public function testPolygonsAndCirclesAreRead(): void
    {
        $content = implode("\r\n", [
            '*** CONDOR AIRSPACE AND PENALTY ZONES ***',
            'AC UNCLASSIFIED',
            'AN R196A1 Est GAP (Notam)',
            'AH FL195',
            'AL 3300FT AGL',
            'DP  44:30:00 N  006:30:00 E',
            'DP  44:40:00 N  006:30:00 E',
            'DP  44:40:00 N  006:50:00 E',
            'DP  44:30:00 N  006:50:00 E',
            '',
            'AC D',
            'AN CTR '.mb_convert_encoding('Genève', 'Windows-1252', 'UTF-8'),
            'AL GND',
            'AH 4500FT MSL',
            'V X=46:14:18 N 006:06:34 E',
            'DC 5',
        ]);

        $airspaces = CondorAirspaces::read($content);

        self::assertCount(2, $airspaces);
        self::assertSame(['name' => 'R196A1 Est GAP (Notam)', 'class' => 'UNCLASSIFIED', 'floor' => '3300FT AGL', 'ceiling' => 'FL195', 'latitude' => 44.5833, 'longitude' => 6.6667], $airspaces[0]);
        self::assertSame('CTR Genève', $airspaces[1]['name']);
        self::assertSame('GND', $airspaces[1]['floor']);
        self::assertEqualsWithDelta(46.2383, $airspaces[1]['latitude'], 0.0001);
        self::assertEqualsWithDelta(6.1094, $airspaces[1]['longitude'], 0.0001);
    }

    public function testAirspacesWithoutNameOrOutlineAreLeftOut(): void
    {
        self::assertSame([], CondorAirspaces::read("AC D\r\nAN Nowhere\r\nAL GND\r\nAH FL100\r\n"));
        self::assertSame([], CondorAirspaces::read("AC D\r\nDP 44:30:00 N 006:30:00 E\r\n"));
        self::assertSame([], CondorAirspaces::read(''));
    }
}
