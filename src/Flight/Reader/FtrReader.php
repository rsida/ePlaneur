<?php

declare(strict_types=1);

namespace App\Flight\Reader;

use App\Flight\Condor\Landscape;
use App\Flight\Condor\LandscapeRegistry;
use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;

/**
 * Condor 3 flight track (.ftr), as saved by Condor and uploaded to Condor Club. The format is not
 * documented; what is known (measured on Condor 3.1 files):
 *
 * - header of fixed size: "FTR", Condor version (int at 3), pilot first name, last name, country,
 *   registration, competition id and landscape (Pascal short strings at 0x9b, 0xac, 0xbd, 0xce, 0xd6,
 *   0xdf; registration "F-CFDU" and competition id "FDU" on a Condor Club track); task: count (int at 0xf3), then 84 bytes per point from 0xf7 (name, x, y, z as floats,
 *   radius and angle as ints at +44 and +48: 360° for a cylinder, 180° for a start or finish line); glider class and model (short strings at 0x52eb and 0x52fc), the
 *   litres of water ballast (int at 0x532f: 146 on a Diana 2 flight, as Condor Club shows), then
 *   the flight plan name;
 * - the settings of the task (penalties, realism, start time, weather) are between the task and
 *   the glider but do not read as plain numbers: only the name of a custom weather shows;
 * - at 0x549b: six ints (1, -1, race id, 0, number of records, ?), then the records;
 * - records of 64 bytes from 0x54b3: the time at +0, x, y, altitude as floats at +4, +8, +12
 *   (landscape metres, converted with the landscape's projection), the attitude, and 24 bytes that
 *   look encrypted.
 *
 * The time is a float in units of 32 hours (115,200 s), stored with 0x500000 subtracted from its
 * bits: adding it back gives a value growing linearly with the time (found on 2026-10-08 against
 * the IGC export of the same flight: 5,911 s, every IGC fix within 1.5 m of the record of its
 * time). Records come every 0.06 to 1.2 s, about 0.1 s. Should a file not decode to growing times,
 * an even interval (RECORD_INTERVAL) is used and the times are flagged as estimates.
 */
final readonly class FtrReader
{
    /** Average seconds between records, for files whose times do not decode (0.1013 to 0.1062 s on a 1 h 38 flight). */
    public const float RECORD_INTERVAL = 0.104;

    /** Seconds in one unit of the time of the records. */
    private const float TIME_UNIT = 115200.0;

    /** What the bits of the time of the records lack. */
    private const int TIME_BITS_OFFSET = 0x500000;

    private const int TASK_COUNT = 0xF3;
    private const int TASK_POINTS = 0xF7;
    private const int TASK_POINT_SIZE = 0x54;
    private const int WATER = 0x532F;
    private const int RECORDS_HEADER = 0x549B;
    private const int RECORDS = 0x54B3;
    private const int RECORD_SIZE = 64;

    public function __construct(
        private LandscapeRegistry $landscapes,
    ) {
    }

    public function supports(string $content): bool
    {
        return str_starts_with($content, 'FTR');
    }

    public function read(string $content): Track
    {
        if (\strlen($content) < self::RECORDS + self::RECORD_SIZE || !$this->supports($content)) {
            throw new InvalidTrackException('Ce fichier n’est pas une trace Condor (.ftr).');
        }
        $version = self::int($content, 3);
        if ($version < 3000) {
            throw new InvalidTrackException('Seules les traces de Condor 3 sont lues. Pour Condor 2, exportez le vol en IGC.');
        }

        $landscapeName = self::shortString($content, 0xDF);
        $landscape = $this->landscapes->find($landscapeName)
            ?? throw new InvalidTrackException(\sprintf('Le paysage « %s » n’est pas encore connu du site : demandez à un administrateur de l’ajouter.', $landscapeName));

        $count = self::int($content, self::RECORDS_HEADER + 16);
        $count = min($count, intdiv(\strlen($content) - self::RECORDS, self::RECORD_SIZE));
        $times = self::times($content, $count);
        $fixes = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = self::RECORDS + $i * self::RECORD_SIZE;
            [$x, $y, $altitude] = array_values(unpack('g3', $content, $offset + 4) ?: []);
            if (!is_finite($x) || !is_finite($y) || !is_finite($altitude)) {
                continue;
            }
            [$latitude, $longitude] = $landscape->toLatLon($x, $y);
            [$bank, $pitch] = self::attitude($content, $offset);
            $fixes[] = new Fix($times[$i] ?? $i * self::RECORD_INTERVAL, $latitude, $longitude, $altitude, $bank, $pitch);
        }
        if (\count($fixes) < 2) {
            throw new InvalidTrackException('La trace ne contient aucune position.');
        }

        // Unsigned: Condor race ids go beyond 2^31
        $raceId = (int) (unpack('V', $content, self::RECORDS_HEADER + 8)[1] ?? 0);

        return new Track(
            format: 'ftr',
            simulator: 'Condor 3',
            pilot: trim(self::shortString($content, 0x9B).' '.self::shortString($content, 0xAC)),
            competitionId: self::shortString($content, 0xD6) ?: null,
            glider: self::shortString($content, 0x52FC) ?: null,
            landscape: $landscape->name,
            raceId: 0 !== $raceId ? $raceId : null,
            flightPlan: self::flightPlan($content),
            turnpoints: self::turnpoints($content, $landscape),
            fixes: $fixes,
            exactTimes: null !== $times,
            water: self::water($content),
        );
    }

    /**
     * Bank (positive to the right) and pitch (positive nose up) of a record, in degrees, from the
     * quaternion at +20 (x, y, z, w; x east, y north, z up). It turns the world into the glider: its
     * conjugate turns the nose (body x) and the left wing (body y) into the world. Found on
     * 2026-10-10 on C3-s117: the heading of the nose follows the track within 2.4° (median), the
     * pitch stays within a few degrees in the circles, which bank 16° to 45°.
     *
     * @return array{?float, ?float}
     */
    private static function attitude(string $content, int $offset): array
    {
        $values = unpack('g4', $content, $offset + 20);
        if (false === $values) {
            return [null, null];
        }
        [$x, $y, $z, $w] = array_values($values);
        // Not always of length 1 (up to 2 % off on C3-s117): normalised, unless it is not a rotation
        $norm = sqrt($x * $x + $y * $y + $z * $z + $w * $w);
        if (!is_finite($norm) || $norm < 0.5 || $norm > 1.5) {
            return [null, null];
        }
        [$x, $y, $z, $w] = [$x / $norm, $y / $norm, $z / $norm, $w / $norm];
        // Height (world z) of the nose and of the left wing, rotated by the conjugate (−x, −y, −z, w)
        $noseUp = 2 * ($x * $z + $w * $y);
        $wingUp = 2 * ($y * $z - $w * $x);
        $angle = static fn (float $value): float => round(rad2deg(asin(max(-1.0, min(1.0, $value)))), 1);

        return [$angle($wingUp), $angle($noseUp)];
    }

    /**
     * Seconds of each record since the first one, or null when they do not decode to times growing
     * by less than 5 s between two records.
     *
     * @return list<float>|null
     */
    private static function times(string $content, int $count): ?array
    {
        $times = [];
        $first = null;
        for ($i = 0; $i < $count; ++$i) {
            $bits = (int) (unpack('V', $content, self::RECORDS + $i * self::RECORD_SIZE)[1] ?? 0);
            $value = (float) (unpack('g', pack('V', ($bits + self::TIME_BITS_OFFSET) & 0xFFFFFFFF))[1] ?? \NAN);
            $first ??= $value;
            $time = ($value - $first) * self::TIME_UNIT;
            $previous = $times[$i - 1] ?? 0.0;
            if (!is_finite($time) || $time < $previous || $time - $previous > 5.0) {
                return null;
            }
            $times[] = $time;
        }

        return $times;
    }

    /**
     * @return list<Turnpoint>
     */
    private static function turnpoints(string $content, Landscape $landscape): array
    {
        $turnpoints = [];
        $count = max(0, min(self::int($content, self::TASK_COUNT), 20));
        for ($i = 0; $i < $count; ++$i) {
            $offset = self::TASK_POINTS + $i * self::TASK_POINT_SIZE;
            [$x, $y, $altitude] = array_values(unpack('g3', $content, $offset + 17) ?: []);
            [$latitude, $longitude] = $landscape->toLatLon($x, $y);
            $turnpoints[] = new Turnpoint(self::shortString($content, $offset), $latitude, $longitude, $altitude, (float) self::int($content, $offset + 44), (float) self::int($content, $offset + 48));
        }

        return $turnpoints;
    }

    /** Litres of water ballast, or null when the value is out of what a glider carries. */
    private static function water(string $content): ?int
    {
        $litres = self::int($content, self::WATER);

        return $litres >= 0 && $litres <= 500 ? $litres : null;
    }

    /** Name of the flight plan: the first short string after the glider skin. */
    private static function flightPlan(string $content): ?string
    {
        for ($offset = 0x531E; $offset < self::RECORDS_HEADER - 4; ++$offset) {
            $length = \ord($content[$offset]);
            if ($length >= 4 && 1 === preg_match('/^[\x20-\x7e]+$/', substr($content, $offset + 1, $length))) {
                return substr($content, $offset + 1, $length);
            }
        }

        return null;
    }

    private static function shortString(string $content, int $offset): string
    {
        $value = substr($content, $offset + 1, \ord($content[$offset]));

        return trim(mb_convert_encoding($value, 'UTF-8', 'Windows-1252'));
    }

    private static function int(string $content, int $offset): int
    {
        return (int) (unpack('l', $content, $offset)[1] ?? 0);
    }
}
