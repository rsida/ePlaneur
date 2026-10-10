<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Glider;

use App\Entity\Glider;
use App\Flight\Glider\CondorGliders;
use App\Flight\Glider\Polar;

/**
 * A made-up 15 m glider in the formats of Condor (not the files of the simulator): its sheet and
 * its polar, a minimum sink of 0.5 m/s at 80 km/h and a best glide of about 45.
 */
final class GliderSamples
{
    public const array NODES = [
        [65.0, -0.75, 65.0, -1.2, 65.0, -0.5],
        [80.0, -0.5, 70.0, -0.5, 90.0, -0.5],
        [130.0, -0.95, 110.0, -0.65, 150.0, -1.25],
        [180.0, -2.2, 165.0, -1.7, 195.0, -2.7],
        [250.0, -5.0, 235.0, -4.2, 265.0, -5.9],
    ];

    public const string SHEET = "ACME TESTER Glider-15\r\n\r\nWing span, 15 m, 49.21 ft, 15 m\r\nWing area, 10 m2, 107.6 sqft, 10 m2\r\nLength, 6.5 m, 21.3 ft, 6.5 m\r\n\r\nEmpty weight, 220 kg, 485 lbs, 220 kg\r\nMax weight, 500 kg, 1102 lbs, 500 kg\r\nWater ballast, 180 l, 47.6 US gal, 180 l\r\nMin wing loading, 30.0 kg/m2, 6.14 lbs/sqft, 30.0 kg/m2\r\nMax wing loading, 48.0 kg/m2, 9.83 lbs/sqft, 48.0 kg/m2\r\n\r\nMax speed, 270 km/h, 146 kts, 146 kts\r\nManeuvering speed, 190 km/h, 103 kts, 103 kts\r\nMin speed, 66 km/h, 36 kts, 36 kts\r\n\r\nMin sink, 0.5 m/s, 98 ft/min, 0.5 m/s\r\nBest glide ratio, 45 at 100 km/h, 45 at 54 kts, 45 at 54 kts\r\n\r\nDAeC index, 110, 110, 110\r\n";

    /** The polar as a Condor .pol file: the count, then six floats per node. */
    public static function pol(): string
    {
        return pack('l', \count(self::NODES)).pack('g*', ...array_merge(...self::NODES));
    }

    public static function glider(string $name = 'Glider-15'): Glider
    {
        $glider = new Glider($name, new \DateTimeImmutable('2026-10-10'));
        $sheet = CondorGliders::sheet(self::SHEET);
        \assert(null !== $sheet);
        $glider->update($sheet, new Polar(self::NODES), new \DateTimeImmutable('2026-10-10'));

        return $glider;
    }
}
