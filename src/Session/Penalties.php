<?php

declare(strict_types=1);

namespace App\Session;

/**
 * The penalties of a flight against the penalty settings of its task, listed like Condor Club does:
 * every rule of the task with its points, and the points lost on those the track shows (start speed
 * above the limit, start too high, finish too low, measured where the flight crossed the line; height
 * recoveries, counted from the track).
 *
 * Only an estimate: Condor measures the start ground speed at the exact crossing, which the 1 s
 * series does not match within 1 or 2 km/h, and counts what the track does not show (cloud flying,
 * thermal helpers, plane recoveries, airspace, windows). The official total of the results, when
 * known, comes with the gap between the two.
 *
 * A track exported in IGC by Condor carries the points Condor counted for each rule (CondorReport):
 * the penalties are then exact, and so is the measure of a rule that cost points (its limit plus the
 * points over the rate: 0.2 point at 2 points per km/h above 170 km/h is 170.1 km/h).
 */
final class Penalties
{
    /** Rules the track can measure. */
    private const array MEASURED = ['StartSpeed', 'HighStart', 'LowFinish', 'HeightRecovery'];

    /**
     * @param array<string, mixed> $analysis the analysis of the flight (FlightAnalysis::toArray())
     *
     * @return array{rules: list<array{key: string, label: string, points: int, limit: ?int, measured: ?float, lost: ?float, official: ?float, at: ?int}>, estimated: float, official: ?float, other: ?float, exact: bool}
     */
    public static function of(array $analysis, TaskSettings $settings, ?float $official = null): array
    {
        $points = $analysis['task']['turnpoints'] ?? [];
        $start = $points[1] ?? null;
        $finish = \count($points) > 2 && ($analysis['task']['completed'] ?? false) ? end($points) : null;
        // Points per rule computed by Condor (IGC exported by Condor)
        $condor = $analysis['condor'] ?? null;
        $exact = \is_array($condor);
        // Condor's total is exact; the results file of the server rounds it (0 for 0.2 on C3-c060)
        $official = $exact ? $condor['penaltyPoints'] ?? $official : $official;

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
                // Each one costs the points of the rule; analyses older than the recoveries do not tell
                'HeightRecovery' => isset($analysis['recoveries']) ? [null, (float) \count($analysis['recoveries']), $analysis['recoveries'][0]['at'] ?? null, (float) \count($analysis['recoveries'])] : [null, null, null, null],
                default => [null, null, null, null],
            };
            $lost = \in_array($key, self::MEASURED, true) && null !== $excess ? round($excess * $rate, 2) : null;
            $estimated += $lost ?? 0.0;
            $counted = $exact ? (float) ($condor['penalties'][$key] ?? 0.0) : null;
            if ($counted > 0 && null !== $limit && \in_array($key, self::MEASURED, true)) {
                $measured = round('LowFinish' === $key ? $limit - $counted / $rate : $limit + $counted / $rate, 1);
            }
            $rules[] = ['key' => $key, 'label' => $label, 'points' => $rate, 'limit' => $limit, 'measured' => $measured, 'lost' => $lost, 'official' => $counted, 'at' => ($lost ?? 0) > 0 || ($counted ?? 0) > 0 ? $at : null];
        }

        return [
            'rules' => $rules,
            'estimated' => round($estimated, 2),
            'official' => $official,
            // What the server counted beyond the estimate: rules the track does not show
            'other' => null !== $official ? round($official - $estimated, 2) : null,
            'exact' => $exact,
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
