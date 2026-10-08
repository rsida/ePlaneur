<?php

declare(strict_types=1);

namespace App\Flight\Reader;

use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;

/**
 * IGC flight recorder file (FAI standard), as exported by Condor ("Analyse du vol" > "Export IGC")
 * or by a real logger: B records give one fix per second with its time, C records the task, H
 * records the pilot, glider and date. GNSS altitude is used, pressure altitude when it is missing.
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
        $firstTime = null;
        $previousTime = null;
        $dayOffset = 0;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
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
                $fixes[] = new Fix((float) ($time - $firstTime), self::latitude($match[4]), self::longitude($match[5]), (float) $altitude);
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
            landscape: null,
            raceId: null,
            flightPlan: null,
            turnpoints: $turnpoints,
            fixes: $fixes,
            exactTimes: true,
            date: $date,
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
