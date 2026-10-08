<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * A flight track as read from its file (TrackReader), before analysis.
 */
final readonly class Track
{
    /**
     * @param list<Fix>       $fixes      in time order
     * @param list<Turnpoint> $turnpoints the task, empty when the file has none
     * @param bool            $exactTimes false when the times are estimated (Condor .ftr files)
     */
    public function __construct(
        public string $format,
        public string $simulator,
        public string $pilot,
        public ?string $competitionId,
        public ?string $glider,
        public ?string $landscape,
        public ?int $raceId,
        public ?string $flightPlan,
        public array $turnpoints,
        public array $fixes,
        public bool $exactTimes,
        public ?\DateTimeImmutable $date = null,
    ) {
    }

    /**
     * @param list<Fix> $fixes
     */
    public function withFixes(array $fixes): self
    {
        return new self($this->format, $this->simulator, $this->pilot, $this->competitionId, $this->glider, $this->landscape, $this->raceId, $this->flightPlan, $this->turnpoints, $fixes, $this->exactTimes, $this->date);
    }
}
