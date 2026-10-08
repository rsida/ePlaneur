<?php

declare(strict_types=1);

namespace App\Flight\Geo;

/**
 * UTM to latitude and longitude on the WGS 84 ellipsoid (inverse transverse Mercator, series to the
 * sixth order: millimetre accuracy within a zone, and still a few metres for Condor landscapes that
 * spread beyond theirs).
 */
final class Utm
{
    private const float A = 6378137.0;
    private const float F = 1 / 298.257223563;
    private const float K0 = 0.9996;

    /**
     * @return array{float, float} latitude and longitude in degrees
     */
    public static function toLatLon(float $easting, float $northing, int $zone, bool $northern = true): array
    {
        $e2 = self::F * (2 - self::F);
        $ep2 = $e2 / (1 - $e2);
        $x = $easting - 500000.0;
        $y = $northern ? $northing : $northing - 10000000.0;

        $mu = $y / self::K0 / (self::A * (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256));
        $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));
        $phi1 = $mu
            + (3 * $e1 / 2 - 27 * $e1 ** 3 / 32) * sin(2 * $mu)
            + (21 * $e1 ** 2 / 16 - 55 * $e1 ** 4 / 32) * sin(4 * $mu)
            + (151 * $e1 ** 3 / 96) * sin(6 * $mu)
            + (1097 * $e1 ** 4 / 512) * sin(8 * $mu);

        $sin = sin($phi1);
        $n1 = self::A / sqrt(1 - $e2 * $sin ** 2);
        $t1 = tan($phi1) ** 2;
        $c1 = $ep2 * cos($phi1) ** 2;
        $r1 = self::A * (1 - $e2) / (1 - $e2 * $sin ** 2) ** 1.5;
        $d = $x / ($n1 * self::K0);

        $lat = $phi1 - ($n1 * tan($phi1) / $r1) * (
            $d ** 2 / 2
            - (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 ** 2 - 9 * $ep2) * $d ** 4 / 24
            + (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 ** 2 - 252 * $ep2 - 3 * $c1 ** 2) * $d ** 6 / 720
        );
        $lon = ($d
            - (1 + 2 * $t1 + $c1) * $d ** 3 / 6
            + (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 ** 2 + 8 * $ep2 + 24 * $t1 ** 2) * $d ** 5 / 120
        ) / cos($phi1);

        return [rad2deg($lat), ($zone - 1) * 6 - 180 + 3 + rad2deg($lon)];
    }
}
