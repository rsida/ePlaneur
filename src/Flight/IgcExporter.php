<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * Writes a flight as an IGC file (FAI flight recorder format), for SeeYou, XCSoar and the other
 * flight analysis software: one B record per second from the analysed track, the task as C records
 * with its zones as LSEEYOU lines (as Condor writes them), the pilot and glider as H records.
 *
 * The time of day of a .ftr track is not known: the recording starts at 12:00:00 (said in an L line).
 */
final class IgcExporter
{
    private const int CLOCK_START = 12 * 3600;

    public function export(Flight $flight): string
    {
        $analysis = $flight->getAnalysis();
        $series = $analysis['series'];
        $date = $flight->getDate()->format('dmy');
        $lines = [
            'AXEPePlaneur',
            'HFDTEDATE:'.$date.',01',
            'HFPLTPILOTINCHARGE:'.self::text($flight->getPilot()),
            'HFGTYGLIDERTYPE:'.self::text((string) $flight->getGlider()),
            'HFCIDCOMPETITIONID:'.self::text((string) $flight->getCompetitionId()),
            'HFFTYFRTYPE:'.self::text($flight->getSimulator()).' (export club.eplaneur.fr)',
            'LEPL Times: the recording starts at 12:00:00 when the file of the flight had no time of day',
        ];

        $task = $analysis['task'] ?? null;
        if (\is_array($task) && \count($task['turnpoints']) >= 3) {
            $points = $task['turnpoints'];
            $lines[] = \sprintf('C%s120000%s0001%02d%s', $date, $date, \count($points) - 2, self::text((string) $flight->getFlightPlan()));
            foreach ($points as $point) {
                $lines[] = 'C'.self::latitude($point['latitude']).self::longitude($point['longitude']).self::text($point['name']);
            }
            // Landing: the finish again, as Condor writes it
            $finish = $points[\count($points) - 1];
            $lines[] = 'C'.self::latitude($finish['latitude']).self::longitude($finish['longitude']).self::text($finish['name']);
            foreach (\array_slice($points, 1) as $k => $point) {
                $style = 0 === $k ? -1 : $k;
                $angle = (float) ($point['angle'] ?? 360.0);
                $lines[] = \sprintf('LSEEYOU OZ=%d,Style=1,R1=%dm%s', $style, (int) $point['radius'], $angle < 360.0 ? ',A1='.(int) round($angle / 2) : ',A1=180');
            }
        }

        foreach ($series['time'] as $i => $seconds) {
            $clock = (self::CLOCK_START + (int) $seconds) % 86400;
            $altitude = max(0, (int) round($series['altitude'][$i]));
            $lines[] = \sprintf(
                'B%02d%02d%02d%s%sA%05d%05d',
                intdiv($clock, 3600), intdiv($clock % 3600, 60), $clock % 60,
                self::latitude($series['latitude'][$i]),
                self::longitude($series['longitude'][$i]),
                $altitude, $altitude,
            );
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /** File name: "2026-10-08_FDU_C3-c046a.igc". */
    public function fileName(Flight $flight): string
    {
        $parts = [$flight->getDate()->format('Y-m-d'), $flight->getCompetitionId() ?? $flight->getPilot(), $flight->getFlightPlan() ? explode('_', $flight->getFlightPlan())[0] : null];

        return preg_replace('/[^A-Za-z0-9._-]+/', '-', implode('_', array_filter($parts))).'.igc';
    }

    private static function text(string $value): string
    {
        // IGC files are ASCII
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return trim((string) preg_replace('/[^\x20-\x7e]/', '', false !== $ascii ? $ascii : $value));
    }

    private static function latitude(float $value): string
    {
        return self::coordinate($value, 2, 'N', 'S');
    }

    private static function longitude(float $value): string
    {
        return self::coordinate($value, 3, 'E', 'W');
    }

    /** Degrees, minutes and thousandths of minute: "4523456N". */
    private static function coordinate(float $value, int $degreeDigits, string $positive, string $negative): string
    {
        $absolute = abs($value);
        $degrees = (int) floor($absolute);
        $thousandths = (int) round(($absolute - $degrees) * 60000);
        if ($thousandths >= 60000) {
            ++$degrees;
            $thousandths -= 60000;
        }

        return \sprintf('%0'.$degreeDigits.'d%05d%s', $degrees, $thousandths, $value >= 0 ? $positive : $negative);
    }
}
