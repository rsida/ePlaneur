<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

/**
 * A part of the flight: circling in a thermal, or gliding between thermals.
 */
final readonly class Phase
{
    public const string THERMAL = 'thermal';
    public const string GLIDE = 'glide';

    /**
     * @param int         $start      index in the 1 s series
     * @param int         $end        index in the 1 s series (included)
     * @param float       $gain       metres gained (negative: lost)
     * @param float       $distance   metres flown
     * @param float|null  $glideRatio distance over height lost (glides that lose height)
     * @param string|null $direction  "left" or "right" (thermals)
     * @param float       $straight   metres between its first and last points (a glide flown
     *                                straight has its distance)
     */
    public function __construct(
        public string $type,
        public int $start,
        public int $end,
        public float $gain,
        public float $distance,
        public ?float $glideRatio,
        public ?string $direction,
        public float $straight = 0.0,
    ) {
    }

    /** Seconds. */
    public function duration(): int
    {
        return $this->end - $this->start;
    }

    /** Average vertical speed, m/s. */
    public function vario(): float
    {
        return $this->duration() > 0 ? $this->gain / $this->duration() : 0.0;
    }

    /** Average ground speed, km/h. */
    public function speed(): float
    {
        return $this->duration() > 0 ? $this->distance / $this->duration() * 3.6 : 0.0;
    }
}
