<?php

declare(strict_types=1);

namespace App\Flight\Geo;

/**
 * Distances and bearings on the Earth, as a sphere of mean radius: under 0.5 % of error, enough for
 * speeds and distances of a flight.
 */
final class Geo
{
    private const float EARTH_RADIUS = 6371008.8;

    /** Metres between two points (haversine). */
    public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos($phi1) * cos($phi2) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 2 * self::EARTH_RADIUS * asin(min(1.0, sqrt($a)));
    }

    /** Initial bearing from the first point to the second, in degrees from north (0 to 360). */
    public static function bearing(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);
        $y = sin($deltaLon) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($deltaLon);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /** Signed difference between two bearings, in -180 to 180 degrees (positive: to the right). */
    public static function turn(float $from, float $to): float
    {
        return fmod($to - $from + 540, 360) - 180;
    }
}
