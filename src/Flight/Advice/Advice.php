<?php

declare(strict_types=1);

namespace App\Flight\Advice;

use App\Training\TrainingStep;

/**
 * One piece of advice on a flight (FlightAdvisor): something to work on, something done well, or
 * where the flight stands, with the step of the training path it belongs to, and the moment of the
 * flight that shows it best (a second of the series) and its phase (index in the phases), when
 * there is one.
 */
final readonly class Advice
{
    public const string TIP = 'tip';
    public const string GOOD = 'good';
    /** Neither a fault nor a success: where the flight stands (Silver badge marks). */
    public const string INFO = 'info';

    /**
     * @param self::TIP|self::GOOD|self::INFO $kind
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $text,
        public ?TrainingStep $step = null,
        public ?int $at = null,
        public ?int $phase = null,
    ) {
    }

    /** The same advice, shown at a moment of the flight and in a phase. */
    public function at(?int $at, ?int $phase = null): self
    {
        return new self($this->kind, $this->title, $this->text, $this->step, $at, $phase);
    }

    /**
     * What the flight viewer needs to tie the advice to the replay.
     *
     * @return array{kind: string, title: string, at: ?int, phase: ?int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'at' => $this->at, 'phase' => $this->phase];
    }
}
