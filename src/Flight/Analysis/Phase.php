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
    /** Climbing in straight flight: slope or wave lift. */
    public const string LIFT = 'lift';
    /** The tow or winch climb, before the release: straight and climbing, but not lift. */
    public const string LAUNCH = 'launch';

    /**
     * @param int               $start      index in the 1 s series
     * @param int               $end        index in the 1 s series (included)
     * @param float             $gain       metres gained (negative: lost)
     * @param float             $distance   metres flown
     * @param float|null        $glideRatio distance over height lost (glides that lose height)
     * @param string|null       $direction  "left" or "right" (thermals)
     * @param float             $straight   metres between its first and last points (a glide flown
     *                                      straight has its distance)
     * @param float|null        $turns      full turns (thermals)
     * @param float|null        $radius     metres, from the speed and the turn rate (thermals)
     * @param float|null        $bank       degrees, tan(bank) = v·ω / g (thermals)
     * @param float|null        $windSpeed  km/h, drift of the circles (thermals)
     * @param float|null        $windFrom   degrees the wind blows from (thermals)
     * @param list<?float>|null $centring   average vario (m/s) in each eighth of the circles, from
     *                                      north clockwise: where the glider was around the centre of
     *                                      its circle (thermals, null when too short)
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
        public ?float $turns = null,
        public ?float $radius = null,
        public ?float $bank = null,
        public ?float $windSpeed = null,
        public ?float $windFrom = null,
        public ?array $centring = null,
    ) {
    }

    /** The same phase as another type (a glide that climbs is a slope or wave lift). */
    public function as(string $type): self
    {
        return new self($type, $this->start, $this->end, $this->gain, $this->distance, null, null, $this->straight);
    }

    /**
     * The same thermal with its turns, radius, bank, wind and centring.
     *
     * @param list<?float>|null $centring
     */
    public function withCircles(float $turns, ?float $radius, ?float $bank, ?float $windSpeed, ?float $windFrom, ?array $centring = null): self
    {
        return new self($this->type, $this->start, $this->end, $this->gain, $this->distance, $this->glideRatio, $this->direction, $this->straight, $turns, $radius, $bank, $windSpeed, $windFrom, $centring);
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
