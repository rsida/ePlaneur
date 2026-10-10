<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Condor\CondorAirfields;
use PHPUnit\Framework\TestCase;

/**
 * Records of a Condor `.apt` file built as Condor writes them (72 bytes: Pascal name, latitude,
 * longitude and altitude as floats at 0x24, runway direction, length and width as ints at 0x30).
 */
final class CondorAirfieldsTest extends TestCase
{
    public function testTheAirfieldsAreRead(): void
    {
        $content = self::record('Le-Cannet', 43.3847, 6.3835, 78.0, 810).self::record('Fayence', 43.6104, 6.6965, 230.0, 800);

        $airfields = CondorAirfields::read($content);

        self::assertCount(2, $airfields);
        self::assertSame('Le-Cannet', $airfields[0]['name']);
        self::assertEqualsWithDelta(43.3847, $airfields[0]['latitude'], 0.0001);
        self::assertEqualsWithDelta(6.3835, $airfields[0]['longitude'], 0.0001);
        self::assertSame(78.0, $airfields[0]['altitude']);
        self::assertSame(810, $airfields[0]['length']);
        self::assertSame('Fayence', $airfields[1]['name']);
    }

    public function testRecordsThatAreNotAirfieldsAreLeftOut(): void
    {
        self::assertSame([], CondorAirfields::read(str_repeat("\0", 72)));
        self::assertSame([], CondorAirfields::read(self::record('Nowhere', 300.0, 6.0, 0.0, 0)));
    }

    private static function record(string $name, float $latitude, float $longitude, float $altitude, int $length): string
    {
        $record = \chr(\strlen($name)).str_pad($name, 35, "\0").pack('g3', $latitude, $longitude, $altitude).pack('V3', 90, $length, 30);

        return str_pad($record, 72, "\0");
    }
}
