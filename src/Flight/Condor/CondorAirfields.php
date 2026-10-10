<?php

declare(strict_types=1);

namespace App\Flight\Condor;

/**
 * The airfields of a Condor landscape, from its `.apt` file (Landscapes/<name>/<name>.apt). Not
 * documented; measured on AA3.apt on 2026-10-10: records of 72 bytes, the name as a Pascal string
 * of 35 characters at most, then latitude, longitude and altitude as floats at 0x24, 0x28 and 0x2c
 * (degrees, metres), the runway direction, length and width as ints at 0x30, 0x34 and 0x38 (the
 * direction of some is not a plain heading), and further fields (surface, radio frequency). Le
 * Cannet reads 43.385 N, 6.384 E, 78 m, 810 m of runway; Fayence 43.610 N, 6.697 E, 230 m.
 */
final class CondorAirfields
{
    private const int RECORD = 72;

    /**
     * @return list<array{name: string, latitude: float, longitude: float, altitude: float, length: int}>
     */
    public static function read(string $content): array
    {
        $airfields = [];
        $count = intdiv(\strlen($content), self::RECORD);
        for ($k = 0; $k < $count; ++$k) {
            $record = substr($content, $k * self::RECORD, self::RECORD);
            $name = trim(mb_convert_encoding(substr($record, 1, min(35, \ord($record[0]))), 'UTF-8', 'Windows-1252'));
            [$latitude, $longitude, $altitude] = array_values(unpack('g3', $record, 0x24) ?: []);
            $length = (int) (unpack('V', $record, 0x34)[1] ?? 0);
            if ('' === $name || !is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                continue;
            }
            $airfields[] = ['name' => $name, 'latitude' => round($latitude, 6), 'longitude' => round($longitude, 6), 'altitude' => round($altitude, 1), 'length' => $length];
        }

        return $airfields;
    }
}
