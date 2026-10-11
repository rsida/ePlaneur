<?php

declare(strict_types=1);

namespace App\Flight;

use App\Flight\Condor\CondorReport;

/**
 * A flight track as read from its file (TrackReader), before analysis.
 */
final readonly class Track
{
    /**
     * @param list<Fix>                               $fixes      in time order
     * @param list<Turnpoint>                         $turnpoints the task, empty when the file has none
     * @param bool                                    $exactTimes false when the times are estimated (Condor .ftr files)
     * @param int|null                                $water      litres of water ballast at take-off (Condor files)
     * @param CondorReport|null                       $report     what Condor computed for the flight (IGC exported by Condor)
     * @param list<array{time: float, height: float}> $recoveries height recoveries of Condor: the glider put
     *                                                            higher at once, at the time of the fix after the jump
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
        public ?int $water = null,
        public ?CondorReport $report = null,
        public array $recoveries = [],
    ) {
    }

    /**
     * @param list<Fix>                               $fixes
     * @param list<array{time: float, height: float}> $recoveries
     */
    public function withFixes(array $fixes, array $recoveries = []): self
    {
        return new self($this->format, $this->simulator, $this->pilot, $this->competitionId, $this->glider, $this->landscape, $this->raceId, $this->flightPlan, $this->turnpoints, $fixes, $this->exactTimes, $this->date, $this->water, $this->report, $recoveries);
    }
}
