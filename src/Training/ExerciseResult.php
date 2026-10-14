<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Flight;

/**
 * A flight checked against the criteria of an exercise: each criterion with what was asked and what
 * the flight did, and the measure that ranks the flights that pass.
 */
final readonly class ExerciseResult
{
    /**
     * @param list<array{label: string, asked: string, measured: ?string, met: bool}> $lines
     */
    public function __construct(
        public Flight $flight,
        public array $lines,
        public ?float $measure,
    ) {
    }

    public function passed(): bool
    {
        return array_all($this->lines, static fn (array $line): bool => $line['met']);
    }

    public function metCount(): int
    {
        return \count(array_filter($this->lines, static fn (array $line): bool => $line['met']));
    }
}
