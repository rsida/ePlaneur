<?php

declare(strict_types=1);

namespace App\Flight\Advice;

/**
 * The guided debrief of a flight page: the flight told in chapters (launch and start, each leg of
 * the task, final glide, finish), each with its figures and the advice of FlightAdvisor whose
 * moment falls in it. The last chapter takes the advice that has no moment. Read from the
 * analysis of the flight (FlightAnalysis::toArray()); the template words the figures.
 */
final class FlightDebrief
{
    public const string LAUNCH = 'launch';
    public const string LEG = 'leg';
    public const string FINAL = 'final';
    public const string FLIGHT = 'flight';
    public const string FINISH = 'finish';

    /** A chapter shorter than this, seconds, is merged into the one before (a start right after release). */
    private const int MIN_CHAPTER = 20;

    /**
     * @param array<string, mixed> $analysis
     * @param list<Advice>         $advice
     *
     * @return list<array{kind: string, title: string, from: int, to: int, figures: array<string, mixed>, advice: list<Advice>}>
     */
    public static function chapters(array $analysis, array $advice): array
    {
        $statistics = $analysis['statistics'] ?? [];
        $takeoff = (int) ($statistics['takeoff'] ?? 0);
        $landing = (int) ($statistics['landing'] ?? \count($analysis['series']['time'] ?? []) - 1);
        $phases = $analysis['phases'] ?? [];
        $task = $analysis['task'] ?? null;
        $points = $task['turnpoints'] ?? [];
        $start = $points[1]['reached'] ?? null;

        // End of the launch: release from the tow or the winch, or the takeoff for an airborne start
        $release = $takeoff;
        foreach ($phases as $phase) {
            if ('launch' === $phase['type']) {
                $release = (int) $phase['end'];
            }
        }

        $chapters = [];
        $launchEnd = null !== $start ? (int) $start : $release;
        $chapters[] = [
            'kind' => self::LAUNCH,
            'title' => null !== $start ? 'Le départ' : 'Le lancement',
            'from' => $takeoff,
            'to' => max($launchEnd, $takeoff),
            'figures' => [
                'launch' => $analysis['launch'] ?? null,
                'release' => $release > $takeoff ? $release : null,
                'releaseAltitude' => $release > $takeoff ? round($analysis['series']['altitude'][$release]) : null,
                'start' => $start,
                'startAltitude' => $points[1]['reachedAltitude'] ?? null,
                'startSpeed' => $points[1]['reachedSpeed'] ?? null,
            ],
        ];

        $alone = false;
        if (null !== $start) {
            $final = $task['finalGlide'] ?? null;
            $lastLeg = \count($task['legs']) - 1;
            // The final glide is a chapter of its own when it begins in the last leg; begun earlier,
            // the leg where it begins says so and the review gives the arrival
            $alone = null !== $final && $final['from'] >= ($points[$lastLeg + 1]['reached'] ?? \PHP_INT_MAX);
            $legEnd = null;
            foreach ($task['legs'] as $k => $leg) {
                $from = $points[$k + 1]['reached'];
                if (null === $from) {
                    break;
                }
                $to = $points[$k + 2]['reached'] ?? null;
                $legEnd = $to ?? $landing;
                $end = $alone && $k === $lastLeg ? (int) $final['from'] : $legEnd;
                $begins = null !== $final && !$alone && $final['from'] >= $from && $final['from'] < $legEnd;
                $chapters[] = [
                    'kind' => self::LEG,
                    'title' => \sprintf('Branche %d · %s → %s', $k + 1, $leg['from'], $leg['to']),
                    'from' => (int) $from,
                    'to' => $end,
                    'figures' => ['reached' => null !== $to, 'distance' => $leg['distance'], 'duration' => $leg['duration'], 'speed' => $leg['speed'], 'finalGlide' => $begins ? $final : null] + self::figures($analysis, (int) $from, $end),
                ];
                if (null === $to) {
                    break;
                }
            }
            if ($alone) {
                $chapters[] = [
                    'kind' => self::FINAL,
                    'title' => 'La transition finale',
                    'from' => (int) $final['from'],
                    'to' => (int) $legEnd,
                    'figures' => $final + self::figures($analysis, (int) $final['from'], (int) $legEnd),
                ];
            }
        } elseif ($landing > $release) {
            $chapters[] = [
                'kind' => self::FLIGHT,
                'title' => 'Le vol',
                'from' => $release,
                'to' => $landing,
                'figures' => self::figures($analysis, $release, $landing),
            ];
        }

        $reached = array_values(array_filter(array_column($points, 'reached'), static fn ($at): bool => null !== $at));
        $finish = null !== $task && $task['completed'] ? (int) end($reached) : null;
        $last = end($chapters)['to'];
        $chapters[] = [
            'kind' => self::FINISH,
            'title' => null !== $finish ? 'L’arrivée et le bilan' : 'Le bilan',
            'from' => $finish ?? min($last, $landing),
            'to' => $landing,
            'figures' => [
                'completed' => null !== $task ? $task['completed'] : null,
                'duration' => $task['duration'] ?? null,
                'speed' => $task['speed'] ?? null,
                'lastPoint' => null !== $task && !$task['completed'] && \count($reached) > 1 ? $points[\count($reached) - 1]['name'] : null,
                'distance' => $statistics['distance'] ?? null,
                'flightTime' => $statistics['duration'] ?? null,
                // Arrival of a final glide begun before the last leg (no chapter of its own)
                'finalGlide' => null !== $task && !$alone ? $task['finalGlide'] : null,
            ],
        ];

        $chapters = self::mergeShort($chapters);

        return array_map(static function (array $chapter, int $c) use ($advice, $chapters): array {
            $isLast = $c === \count($chapters) - 1;
            $chapter['advice'] = array_values(array_filter($advice, static fn (Advice $item): bool => null === $item->at
                ? $isLast
                : ($item->at >= $chapter['from'] && ($item->at < $chapter['to'] || $isLast)) || (0 === $c && $item->at < $chapter['from'])));

            return $chapter;
        }, $chapters, array_keys($chapters));
    }

    /**
     * Thermals, climb, share of time circling and lowest height above the ground between two
     * seconds of the flight.
     *
     * @param array<string, mixed> $analysis
     *
     * @return array{thermals: int, climb: ?float, circling: ?float, lowest: ?float, lowestAt: ?int}
     */
    private static function figures(array $analysis, int $from, int $to): array
    {
        $time = 0;
        $gain = 0.0;
        $count = 0;
        foreach ($analysis['phases'] ?? [] as $phase) {
            if ('thermal' !== $phase['type'] || $phase['end'] <= $from || $phase['start'] >= $to) {
                continue;
            }
            // Counted in the chapter where it starts, measured inside the chapter only
            $count += $phase['start'] >= $from ? 1 : 0;
            $seconds = min($to, $phase['end']) - max($from, $phase['start']);
            $time += $seconds;
            $gain += $phase['duration'] > 0 ? $phase['gain'] * $seconds / $phase['duration'] : 0.0;
        }
        $lowest = null;
        $lowestAt = null;
        $ground = $analysis['series']['ground'] ?? null;
        if (null !== $ground) {
            for ($i = $from; $i <= $to; ++$i) {
                if (null === ($ground[$i] ?? null)) {
                    continue;
                }
                $height = $analysis['series']['altitude'][$i] - $ground[$i];
                if (null === $lowest || $height < $lowest) {
                    [$lowest, $lowestAt] = [$height, $i];
                }
            }
        }

        return [
            'thermals' => $count,
            'climb' => $time > 0 ? round($gain / $time, 1) : null,
            'circling' => $to > $from ? round($time / ($to - $from), 2) : null,
            'lowest' => null !== $lowest ? round($lowest) : null,
            'lowestAt' => $lowestAt,
        ];
    }

    /**
     * A chapter of a few seconds (the start crossed right after the release) joins the one before.
     *
     * @param list<array{kind: string, title: string, from: int, to: int, figures: array<string, mixed>}> $chapters
     *
     * @return list<array{kind: string, title: string, from: int, to: int, figures: array<string, mixed>}>
     */
    private static function mergeShort(array $chapters): array
    {
        $kept = [];
        foreach ($chapters as $chapter) {
            $last = array_key_last($kept);
            if (null !== $last && self::FINISH !== $chapter['kind'] && $chapter['to'] - $chapter['from'] < self::MIN_CHAPTER) {
                $kept[$last]['to'] = max($kept[$last]['to'], $chapter['to']);
                continue;
            }
            $kept[] = $chapter;
        }

        return $kept;
    }
}
