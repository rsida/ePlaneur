<?php

declare(strict_types=1);

namespace App\Flight\Condor;

/**
 * The airspaces of a Condor landscape, from its `.air` file (Landscapes/<name>/<name>.air): OpenAir
 * text, Windows-1252. Each airspace starts with `AC` (class), then `AN` (name), `AL` and `AH`
 * (floor and ceiling as written: "3300FT AGL", "FL195", "GND"), and its outline: `DP` points, or a
 * circle (`V X=` centre, `DC` radius in nautical miles). Only the name, the limits and the centre are
 * kept: enough to name an airspace in a flight plan (`DisabledAirspaces`) and to place it in a region.
 */
final class CondorAirspaces
{
    /**
     * @return list<array{name: string, class: string, floor: string, ceiling: string, latitude: float, longitude: float}>
     */
    public static function read(string $content): array
    {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, Windows-1252');
        $airspaces = [];
        $current = null;
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if (1 === preg_match('/^AC\s+(.*)$/', $line, $match)) {
                $airspaces[] = $current;
                $current = ['name' => '', 'class' => trim($match[1]), 'floor' => '', 'ceiling' => '', 'points' => [], 'centre' => null];
            } elseif (null === $current) {
                continue;
            } elseif (1 === preg_match('/^A([NLH])\s+(.*)$/', $line, $match)) {
                $current[['N' => 'name', 'L' => 'floor', 'H' => 'ceiling'][$match[1]]] = trim($match[2]);
            } elseif (1 === preg_match('/^DP\s+(.*)$/', $line, $match) && null !== $point = self::point($match[1])) {
                $current['points'][] = $point;
            } elseif (1 === preg_match('/^V\s+X\s*=\s*(.*)$/', $line, $match)) {
                $current['centre'] = self::point($match[1]);
            }
        }
        $airspaces[] = $current;

        $result = [];
        foreach ($airspaces as $airspace) {
            if (null === $airspace || '' === $airspace['name']) {
                continue;
            }
            $points = [] !== $airspace['points'] ? $airspace['points'] : array_filter([$airspace['centre']]);
            if ([] === $points) {
                continue;
            }
            $result[] = [
                'name' => mb_substr($airspace['name'], 0, 120),
                'class' => mb_substr($airspace['class'], 0, 30),
                'floor' => mb_substr($airspace['floor'], 0, 30),
                'ceiling' => mb_substr($airspace['ceiling'], 0, 30),
                'latitude' => round(array_sum(array_column($points, 0)) / \count($points), 4),
                'longitude' => round(array_sum(array_column($points, 1)) / \count($points), 4),
            ];
        }

        return $result;
    }

    /**
     * "44:17:41 N 006:36:22 E" (seconds may have decimals).
     *
     * @return array{0: float, 1: float}|null
     */
    private static function point(string $text): ?array
    {
        if (1 !== preg_match('/(\d+):(\d+):(\d+(?:\.\d+)?)\s*([NS])\s*(\d+):(\d+):(\d+(?:\.\d+)?)\s*([EW])/', $text, $match)) {
            return null;
        }
        $latitude = (int) $match[1] + (int) $match[2] / 60 + (float) $match[3] / 3600;
        $longitude = (int) $match[5] + (int) $match[6] / 60 + (float) $match[7] / 3600;

        return ['S' === $match[4] ? -$latitude : $latitude, 'W' === $match[8] ? -$longitude : $longitude];
    }
}
