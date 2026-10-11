<?php

declare(strict_types=1);

namespace App\Session;

/**
 * The penalties of a flight against the penalty settings of its task, listed like Condor Club does:
 * every rule of the task with its points, and the points lost on those the track shows (start speed
 * above the limit, start too high, finish too low), measured where the flight crossed the line.
 *
 * Only an estimate: the dedicated server measures its own way (the ground speed of the track does
 * not give the airspeed, and the airspeed of the records is not readable), and counts what the track
 * does not show (cloud flying, thermal helpers, recoveries, airspace, windows). The official total
 * of the results, when known, comes with the gap between the two.
 */
final class Penalties
{
    /** Rules the track can measure. */
    private const array MEASURED = ['StartSpeed', 'HighStart', 'LowFinish'];

    /**
     * @param array<string, mixed> $analysis the analysis of the flight (FlightAnalysis::toArray())
     *
     * @return array{rules: list<array{key: string, label: string, points: int, limit: ?int, measured: ?float, lost: ?float, at: ?int}>, estimated: float, official: ?float, other: ?float}
     */
    public static function of(array $analysis, TaskSettings $settings, ?float $official = null): array
    {
        $points = $analysis['task']['turnpoints'] ?? [];
        $start = $points[1] ?? null;
        $finish = \count($points) > 2 && ($analysis['task']['completed'] ?? false) ? end($points) : null;

        $rules = [];
        $estimated = 0.0;
        foreach (TaskSettings::PENALTIES as $key => $label) {
            $rate = $settings->penalties[$key] ?? 0;
            if ($rate <= 0) {
                continue;
            }
            [$limit, $measured, $at, $excess] = match ($key) {
                'StartSpeed' => self::over($settings->maxStartSpeed, $start['reachedSpeed'] ?? null, $start['reached'] ?? null),
                'HighStart' => self::over($settings->startMaxHeight, $start['reachedAltitude'] ?? null, $start['reached'] ?? null),
                'LowFinish' => self::under($settings->finishMinHeight, $finish['reachedAltitude'] ?? null, $finish['reached'] ?? null),
                default => [null, null, null, null],
            };
            $lost = \in_array($key, self::MEASURED, true) && null !== $excess ? round($excess * $rate, 2) : null;
            $estimated += $lost ?? 0.0;
            $rules[] = ['key' => $key, 'label' => $label, 'points' => $rate, 'limit' => $limit, 'measured' => $measured, 'lost' => $lost, 'at' => $lost > 0 ? $at : null];
        }

        return [
            'rules' => $rules,
            'estimated' => round($estimated, 2),
            'official' => $official,
            // What the server counted beyond the estimate: rules the track does not show
            'other' => null !== $official ? round($official - $estimated, 2) : null,
        ];
    }

    /**
     * @return array{?int, ?float, ?int, ?float} limit, measured value, second, excess over the limit
     */
    private static function over(?int $limit, int|float|null $measured, ?int $at): array
    {
        if (null === $limit || $limit <= 0 || null === $measured) {
            return [$limit, null === $measured ? null : (float) $measured, $at, null];
        }

        return [$limit, (float) $measured, $at, max(0.0, $measured - $limit)];
    }

    /**
     * @return array{?int, ?float, ?int, ?float} limit, measured value, second, shortfall under the limit
     */
    private static function under(?int $limit, int|float|null $measured, ?int $at): array
    {
        if (null === $limit || $limit <= 0 || null === $measured) {
            return [$limit, null === $measured ? null : (float) $measured, $at, null];
        }

        return [$limit, (float) $measured, $at, max(0.0, $limit - $measured)];
    }
}
