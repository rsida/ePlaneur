<?php

declare(strict_types=1);

namespace App\Session;

/**
 * Reads the results of a race on the Condor dedicated server: the CSV file it writes
 * ("DSRace-<race id>-on-<date>.csv"), or the same table as eplaneur.fr shows it.
 *
 * Columns: Rank, Status, Player, CN, RN, Plane, Dist, Time, Speed, Penalty, Score; values may carry
 * their unit ("205.5 km", "01:19:41", "154.7 km/h", "0.0 p").
 */
final class SessionResults
{
    /** Status of a pilot in the results, in French. */
    public const array STATUSES = [
        'finished' => 'Arrivé',
        'racing' => 'Non arrivé',
        'landed' => 'Posé',
        'crashed' => 'Crashé',
        'disconnected' => 'Déconnecté',
        'disqualified' => 'Disqualifié',
    ];

    private const array COLUMNS = [
        'rank' => 'rank', 'status' => 'status', 'player' => 'player', 'pilot' => 'player', 'cn' => 'competitionId',
        'rn' => 'registration', 'plane' => 'glider', 'glider' => 'glider', 'dist' => 'distance', 'distance' => 'distance',
        'time' => 'time', 'speed' => 'speed', 'penalty' => 'penalty', 'score' => 'score', 'points' => 'score',
    ];

    /**
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     *
     * @throws \InvalidArgumentException when the text has no results table
     */
    public static function fromCsv(string $csv): array
    {
        $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $first = $lines[0] ?? '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];
        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $rows[] = array_map(static fn (?string $cell): string => trim((string) $cell), str_getcsv($line, $delimiter, '"', ''));
            }
        }

        return self::fromRows($rows);
    }

    /**
     * The table of the results page of eplaneur.fr (`lire-tableau-csv-resultats-session.php`).
     *
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     *
     * @throws \InvalidArgumentException when the page has no results table
     */
    public static function fromHtml(string $html): array
    {
        $rows = [];
        preg_match_all('#<tr[^>]*>(.*?)</tr>#si', $html, $lines);
        foreach ($lines[1] as $line) {
            preg_match_all('#<t[hd][^>]*>(.*?)</t[hd]>#si', $line, $cells);
            $rows[] = array_map(static fn (string $cell): string => trim(html_entity_decode(strip_tags($cell), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')), $cells[1]);
        }

        return self::fromRows($rows);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[strtolower($status)] ?? $status;
    }

    /**
     * @param list<list<string>> $rows the header first
     *
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    private static function fromRows(array $rows): array
    {
        $columns = null;
        $results = [];
        foreach ($rows as $row) {
            if (null === $columns) {
                $names = array_map(static fn (string $cell): ?string => self::COLUMNS[strtolower($cell)] ?? null, $row);
                if (\in_array('player', $names, true) && \in_array('rank', $names, true)) {
                    $columns = $names;
                }
                continue;
            }
            $values = [];
            foreach ($columns as $index => $name) {
                if (null !== $name) {
                    $values[$name] = $row[$index] ?? '';
                }
            }
            if ('' === ($values['player'] ?? '') || !ctype_digit($values['rank'] ?? '')) {
                continue;
            }
            $results[] = [
                'rank' => (int) $values['rank'],
                'status' => $values['status'] ?? '',
                'player' => str_replace('.', ' ', $values['player']),
                'competitionId' => self::text($values['competitionId'] ?? ''),
                'registration' => self::text($values['registration'] ?? ''),
                'glider' => self::text($values['glider'] ?? ''),
                'distance' => null !== ($km = self::number($values['distance'] ?? '')) ? (int) round($km * 1000) : null,
                'time' => self::duration($values['time'] ?? ''),
                'speed' => self::number($values['speed'] ?? ''),
                'penalty' => self::number($values['penalty'] ?? ''),
                'score' => self::number($values['score'] ?? ''),
            ];
        }
        if (null === $columns) {
            throw new \InvalidArgumentException('No results table (Rank, Status, Player...) was found.');
        }

        return $results;
    }

    private static function text(string $value): ?string
    {
        return '' !== $value ? mb_substr($value, 0, 40) : null;
    }

    private static function number(string $value): ?float
    {
        return preg_match('/-?\d+(?:[.,]\d+)?/', $value, $match) ? (float) str_replace(',', '.', $match[0]) : null;
    }

    private static function duration(string $value): ?int
    {
        if (!preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }

        return isset($match[3]) ? (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) $match[3] : (int) $match[1] * 60 + (int) $match[2];
    }
}
