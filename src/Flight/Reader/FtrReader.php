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
 *   competition id, registration and landscape (Pascal short strings at 0x9b, 0xac, 0xbd, 0xce, 0xd6,
 *   0xdf); task: count (int at 0xf3), then 84 bytes per point from 0xf7 (name, x, y, z as floats,
 *   radius as int at +44); glider class and model (short strings at 0x52eb and 0x52fc), then the
 *   flight plan name;
 * - at 0x549b: six ints (1, -1, race id, 0, number of records, ?), then the records;
 * - records of 64 bytes from 0x54b3: x, y, altitude as floats at +4, +8, +12 (landscape metres,
 *   converted with the landscape's projection), the attitude, and 24 bytes that look encrypted,
 *   holding the time. Records are taken about every 0.104 s (RECORD_INTERVAL): times are estimates.
 */
final readonly class FtrReader
{
    /** Average seconds between records: Condor race times over records between start and finish, on four flights (0.1037 to 0.1068 s). */
    public const float RECORD_INTERVAL = 0.104;

    private const int TASK_COUNT = 0xF3;
    private const int TASK_POINTS = 0xF7;
    private const int TASK_POINT_SIZE = 0x54;
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
        $fixes = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = self::RECORDS + $i * self::RECORD_SIZE;
            [$x, $y, $altitude] = array_values(unpack('g3', $content, $offset + 4) ?: []);
            if (!is_finite($x) || !is_finite($y) || !is_finite($altitude)) {
                continue;
            }
            [$latitude, $longitude] = $landscape->toLatLon($x, $y);
            $fixes[] = new Fix($i * self::RECORD_INTERVAL, $latitude, $longitude, $altitude);
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
            competitionId: self::shortString($content, 0xCE) ?: null,
            glider: self::shortString($content, 0x52FC) ?: null,
            landscape: $landscape->name,
            raceId: 0 !== $raceId ? $raceId : null,
            flightPlan: self::flightPlan($content),
            turnpoints: self::turnpoints($content, $landscape),
            fixes: $fixes,
            exactTimes: false,
        );
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
            $turnpoints[] = new Turnpoint(self::shortString($content, $offset), $latitude, $longitude, $altitude, (float) self::int($content, $offset + 44));
        }

        return $turnpoints;
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
