<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Orders the flights that passed an exercise by its ranking measure (higher or lower is better); a
 * flight without the measure comes last.
 */
final class ExerciseRanking
{
    /**
     * @param iterable<ExerciseResult> $results
     *
     * @return list<ExerciseResult>
     */
    public static function sort(iterable $results, ExerciseCriterion $rankBy): array
    {
        $sorted = [...$results];
        usort($sorted, static function (ExerciseResult $a, ExerciseResult $b) use ($rankBy): int {
            if (null === $a->measure || null === $b->measure) {
                return (null === $a->measure) <=> (null === $b->measure);
            }

            return $rankBy->higherIsBetter() ? $b->measure <=> $a->measure : $a->measure <=> $b->measure;
        });

        return $sorted;
    }

    /**
     * @param iterable<ExerciseResult> $results
     */
    public static function best(iterable $results, ExerciseCriterion $rankBy): ?ExerciseResult
    {
        return self::sort($results, $rankBy)[0] ?? null;
    }
}
