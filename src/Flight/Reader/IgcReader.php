<?php

declare(strict_types=1);

namespace App\Flight\Reader;

use App\Flight\Condor\CondorReport;
use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;

/**
 * IGC flight recorder file (FAI standard), as exported by Condor ("Analyse du vol" > "Export IGC")
 * or by a real logger: B records give one fix per second with its time, C records the task (the
 * first one declares it, with its name: the flight plan; then take-off, start, turnpoints, finish
 * and landing), LSEEYOU lines the zones of the task points (500 m cylinders otherwise), H records the
 * pilot, glider and date. GNSS altitude is used, pressure altitude when it is missing.
 *
 * Condor's export also has LCON lines (CondorReport): its flight plan, from which come the race id,
 * the landscape, the water ballast and the name of the flight plan, and the official result.
 */
final readonly class IgcReader
{
    public function supports(string $content): bool
    {
        return 1 === preg_match('/^A[A-Z0-9]{3}/', ltrim($content, "\xEF\xBB\xBF"))
            || 1 === preg_match('/^B\d{6}\d{7}[NS]\d{8}[EW]/m', $content);
    }

    public function read(string $content): Track
    {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, Windows-1252');
        $headers = [];
        $fixes = [];
        $turnpoints = [];
        $taskName = null;
        $zones = [];
        $firstTime = null;
        $previousTime = null;
        $dayOffset = 0;
        $lines = preg_split('/\R/', $content) ?: [];

        foreach ($lines as $line) {
            if (1 === preg_match('/^H[FOP]([A-Z]{3})[^:]*:?(.*)$/', $line, $match)) {
                $headers[$match[1]] ??= trim($match[2]);
            } elseif (1 === preg_match('/^B(\d{2})(\d{2})(\d{2})(\d{7}[NS])(\d{8}[EW])([AV])(\d{5}|-\d{4})(\d{5}|-\d{4})/', $line, $match)) {
                $time = (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) $match[3];
                // Flights across midnight (UTC)
                if (null !== $previousTime && $time + $dayOffset < $previousTime - 3600) {
                    $dayOffset += 86400;
                }
                $time += $dayOffset;
                $firstTime ??= $time;
                $previousTime = $time;
                $altitude = (int) $match[8] ?: (int) $match[7];
                $seconds = (float) ($time - $firstTime);
                // Times are whole seconds: Condor, recording a bit more often, writes two fixes in the
                // same second now and then; the second one is put half a second later
                if ([] !== $fixes && $seconds <= $fixes[\count($fixes) - 1]->time) {
                    $seconds = $fixes[\count($fixes) - 1]->time + 0.5;
                }
                $fixes[] = new Fix($seconds, self::latitude($match[4]), self::longitude($match[5]), (float) $altitude);
            } elseif (1 === preg_match('/^C\d{6}\d{6}\d{6}\d{4}[-\d]\d(.*)$/', $line, $match)) {
                // Declaration: date and time, flight date, task number, number of points, then the name
                // (kept when it has letters: some loggers write digits only)
                $taskName = 1 === preg_match('/\p{L}/u', $match[1]) ? trim($match[1]) : null;
            } elseif (1 === preg_match('/^LSEEYOU OZ=(-?\d+),.*?R1=(\d+(?:\.\d+)?)m(?:,A1=(\d+(?:\.\d+)?))?/', $line, $match)) {
                // Zone of a task point (SeeYou extension, written by Condor): OZ=-1 is the start;
                // A1 is half the angle, MaxAlt the maximum altitude
                $maxAltitude = 1 === preg_match('/MaxAlt=(\d+(?:\.\d+)?)m/', $line, $max) ? (float) $max[1] : null;
                $zones[(int) $match[1]] = [(float) $match[2], isset($match[3]) ? 2 * (float) $match[3] : 360.0, $maxAltitude];
            } elseif (1 === preg_match('/^C(\d{7}[NS])(\d{8}[EW])(.*)$/', $line, $match)) {
                $latitude = self::latitude($match[1]);
                $longitude = self::longitude($match[2]);
                if (0.0 !== $latitude || 0.0 !== $longitude) {
                    $turnpoints[] = new Turnpoint(trim($match[3]), $latitude, $longitude, 0.0, 500.0);
                }
            }
        }

        if (\count($fixes) < 2) {
            throw new InvalidTrackException('Ce fichier IGC ne contient aucune position.');
        }

        // C records: take-off, start, turnpoints, finish, landing; the task ends at the finish
        if (\count($turnpoints) >= 4) {
            array_pop($turnpoints);
        }
        $report = CondorReport::fromIgcLines($lines);
        $plan = null !== $report?->flightPlan ? @parse_ini_string($report->flightPlan, true, \INI_SCANNER_RAW) : false;
        $plan = \is_array($plan) ? $plan : [];
        $task = $plan['Task'] ?? [];
        foreach ($turnpoints as $k => $point) {
            // C records have no altitude: the one of the flight plan of Condor's export (TPPosZ)
            $altitude = isset($task['TPPosZ'.$k]) ? (float) $task['TPPosZ'.$k] : $point->altitude;
            if (isset($zones[$k - 2])) {
                [$radius, $angle, $maxAltitude] = $zones[$k - 2];
                // Minimum and maximum altitude: from the flight plan (TPWidth and TPHeight of a
                // classic sector), the maximum from the zone otherwise
                $classic = 0 === (int) ($task['TPSectorType'.$k] ?? 0);
                $min = $classic ? (float) ($task['TPWidth'.$k] ?? 0) : 0.0;
                $max = $classic && isset($task['TPHeight'.$k]) ? (float) $task['TPHeight'.$k] : ($maxAltitude ?? 0.0);
                $turnpoints[$k] = new Turnpoint($point->name, $point->latitude, $point->longitude, $altitude, $radius, $angle, $min > 0 ? $min : null, $max > 0 ? $max : null);
            } elseif ($altitude !== $point->altitude) {
                $turnpoints[$k] = new Turnpoint($point->name, $point->latitude, $point->longitude, $altitude, $point->radius, $point->angle);
            }
        }
        $taskName ??= ($plan['Description']['Text'] ?? '') ?: null;

        $type = $headers['FTY'] ?? '';
        $date = null;
        if (1 === preg_match('/(\d{2})(\d{2})(\d{2})/', $headers['DTE'] ?? '', $match)) {
            $date = \DateTimeImmutable::createFromFormat('!dmy', $match[1].$match[2].$match[3]) ?: null;
        }

        return new Track(
            format: 'igc',
            simulator: str_contains(strtolower($type), 'condor') ? (preg_match('/condor\s*(\d)/i', $type, $version) ? 'Condor '.$version[1] : 'Condor') : 'Enregistreur IGC',
            pilot: $headers['PLT'] ?? '',
            competitionId: ($headers['CID'] ?? '') ?: null,
            glider: ($headers['GTY'] ?? '') ?: null,
            landscape: ($plan['Task']['Landscape'] ?? '') ?: null,
            raceId: $report?->raceId,
            flightPlan: null !== $taskName ? mb_substr($taskName, 0, 160) : null,
            turnpoints: $turnpoints,
            fixes: $fixes,
            exactTimes: true,
            date: $date,
            water: isset($plan['Plane']['Water']) ? (int) $plan['Plane']['Water'] : null,
            report: $report,
            // B records are on the clock of the task in Condor's export, UTC for a real logger
            clock: null !== $firstTime ? (float) ($firstTime % 86400) : null,
        );
    }

    /** "4523456N": degrees, minutes and thousandths of minute. */
    private static function latitude(string $value): float
    {
        $degrees = (int) substr($value, 0, 2) + (int) substr($value, 2, 5) / 60000;

        return 'S' === $value[7] ? -$degrees : $degrees;
    }

    /** "00512345E". */
    private static function longitude(string $value): float
    {
        $degrees = (int) substr($value, 0, 3) + (int) substr($value, 3, 5) / 60000;

        return 'W' === $value[8] ? -$degrees : $degrees;
    }
}
