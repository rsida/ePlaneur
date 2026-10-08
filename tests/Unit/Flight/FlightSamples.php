<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Flight\Fix;

/**
 * Synthetic flights for the flight tests, built in metres east (x) and north (y) of 45° N 5° E:
 *
 * - 0 to 119 s: glide to the east at 30 m/s, losing 1 m/s;
 * - 120 to 299 s: six left turns of 100 m radius (12°/s, 75 km/h), climbing 2 m/s;
 * - 300 to 419 s: glide to the east again.
 */
final class FlightSamples
{
    public const float LATITUDE = 45.0;
    public const float LONGITUDE = 5.0;

    /**
     * @return list<Fix>
     */
    public static function glideThermalGlide(): array
    {
        $fixes = [];
        $altitude = 1500.0;
        for ($t = 0; $t < 420; ++$t) {
            if ($t < 120) {
                [$x, $y] = [30.0 * $t, 0.0];
                $altitude = 1500.0 - $t;
            } elseif ($t < 300) {
                // Centre on the left (north) of the track: counter-clockwise seen from above
                $angle = deg2rad(12.0 * ($t - 120));
                [$x, $y] = [3570.0 + 100.0 * sin($angle), 100.0 - 100.0 * cos($angle)];
                $altitude = 1381.0 + 2.0 * ($t - 120);
            } else {
                [$x, $y] = [3570.0 + 30.0 * ($t - 300), 0.0];
                $altitude = 1741.0 - ($t - 300);
            }
            $fixes[] = new Fix((float) $t, ...self::toLatLon($x, $y), altitude: $altitude);
        }

        return $fixes;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function toLatLon(float $x, float $y): array
    {
        return [
            'latitude' => self::LATITUDE + $y / 111194.9,
            'longitude' => self::LONGITUDE + $x / (111194.9 * cos(deg2rad(self::LATITUDE))),
        ];
    }

    /**
     * IGC file of fixes (one B record each, from `$start` seconds after midnight UTC).
     *
     * @param list<Fix> $fixes
     */
    public static function igc(array $fixes, int $start = 36000, string $extraHeaders = ''): string
    {
        $lines = ['AXCSCondor', 'HFDTEDATE:190226,01', 'HFPLTPILOTINCHARGE:Jeanne Pilote', 'HFGTYGLIDERTYPE:Duo Discus', 'HFCIDCOMPETITIONID:JP1', 'HFFTYFRTYPE:Condor 3', ...array_filter([$extraHeaders])];
        foreach ($fixes as $fix) {
            $seconds = ($start + (int) $fix->time) % 86400;
            $lines[] = \sprintf(
                'B%02d%02d%02d%s%sA%05d%05d',
                intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60,
                self::coordinate($fix->latitude, 2, 'N', 'S'),
                self::coordinate($fix->longitude, 3, 'E', 'W'),
                (int) round($fix->altitude), (int) round($fix->altitude),
            );
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * C records of a task named `$name` along the x axis: take-off, start and the other points at
     * these distances east, in metres (IGC points get a 500 m radius).
     *
     * @param list<float> $distances
     */
    public static function igcTask(string $name, array $distances): string
    {
        $lines = [\sprintf('C1902261000001902260001%02d%s', \count($distances) - 2, $name)];
        foreach ($distances as $k => $x) {
            ['latitude' => $latitude, 'longitude' => $longitude] = self::toLatLon($x, 0);
            $lines[] = 'C'.self::coordinate($latitude, 2, 'N', 'S').self::coordinate($longitude, 3, 'E', 'W').'Point '.$k;
        }

        return implode("\r\n", $lines);
    }

    private static function coordinate(float $value, int $degreeDigits, string $positive, string $negative): string
    {
        $absolute = abs($value);
        $degrees = (int) floor($absolute);
        $thousandths = (int) round(($absolute - $degrees) * 60000);

        return \sprintf('%0'.$degreeDigits.'d%05d%s', $degrees, $thousandths, $value >= 0 ? $positive : $negative);
    }
}
