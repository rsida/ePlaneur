<?php

declare(strict_types=1);

namespace App\Flight\Condor;

/**
 * What Condor itself computed for a flight, as it writes it in the LCON lines of its IGC export
 * ("Analyse du vol" > "Export IGC"): the flight plan (LCONFPL, the .fpl file line by line), the
 * official result (LCONFlightInfo) and the points of each penalty (LCONPenalties, only those that
 * cost points). These are the figures Condor Club shows, exact where the track only gives estimates.
 *
 * Read on six exports (2026-06-21 and 2026-10-11): the times are clock times of the task,
 * "hh:mm:ss"; the task time runs from TaskStart, the opening of the start gate in a regatta (C3-c060,
 * C3-c058) and the crossing of the start line in a start window (within 1 s of the track); distances and speeds carry their unit ("182.83km", "134.06km/h"). The status is not reliable:
 * "Finished" in that export, "/" in exports of the same flights made on 2026-10-11 (read as null).
 */
final readonly class CondorReport
{
    /**
     * @param int|null             $startTime  seconds of the day, clock time of the task, when the flight started
     * @param int|null             $taskStart  seconds of the day when the task time starts (gate opening in a regatta, crossing of the line otherwise)
     * @param int|null             $flightTime seconds
     * @param int|null             $taskTime   seconds from taskStart to the finish
     * @param string|null          $status     Condor's status of the pilot ("Finished"...)
     * @param float|null           $distance   kilometres flown on the task
     * @param float|null           $speed      average speed on the task, km/h
     * @param array<string, float> $penalties  keys of TaskSettings::PENALTIES → points lost
     * @param string|null          $flightPlan the flight plan, as the .fpl file
     */
    public function __construct(
        public ?int $raceId,
        public ?int $startTime,
        public ?int $taskStart,
        public ?int $flightTime,
        public ?int $taskTime,
        public ?string $status,
        public ?float $distance,
        public ?float $speed,
        public ?float $penaltyPoints,
        public array $penalties,
        public ?string $flightPlan,
    ) {
    }

    /**
     * The report in the LCON lines of an IGC file, null when it has none.
     *
     * @param list<string> $lines the lines of the file
     */
    public static function fromIgcLines(array $lines): ?self
    {
        $info = [];
        $penalties = [];
        $plan = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, 'LCONFPL')) {
                $plan[] = substr($line, 7);
            } elseif (1 === preg_match('/^LCONFlightInfo(\w+)=(.*)$/', $line, $match)) {
                $info[$match[1]] = trim($match[2]);
            } elseif (1 === preg_match('/^LCONPenalties(\w+)=(-?\d+(?:\.\d+)?)/', $line, $match)) {
                $penalties[$match[1]] = (float) $match[2];
            }
        }
        if ([] === $info && [] === $plan) {
            return null;
        }

        return new self(
            raceId: isset($info['RaceID']) && is_numeric($info['RaceID']) ? (int) $info['RaceID'] : null,
            startTime: self::time($info['StartTime'] ?? null),
            taskStart: self::time($info['TaskStart'] ?? null),
            flightTime: self::time($info['FlightTime'] ?? null),
            taskTime: self::time($info['TaskTime'] ?? null),
            status: 1 === preg_match('/\p{L}/u', $info['PlayerStatus'] ?? '') ? $info['PlayerStatus'] : null,
            distance: self::number($info['DistanceFlown'] ?? null),
            speed: self::number($info['AverageSpeed'] ?? null),
            penaltyPoints: self::number($info['PenaltyPoints'] ?? null),
            penalties: $penalties,
            flightPlan: [] !== $plan ? implode("\n", $plan)."\n" : null,
        );
    }

    /**
     * @param array<string, mixed> $stored toArray()
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            raceId: $stored['raceId'] ?? null,
            startTime: $stored['startTime'] ?? null,
            taskStart: $stored['taskStart'] ?? null,
            flightTime: $stored['flightTime'] ?? null,
            taskTime: $stored['taskTime'] ?? null,
            status: $stored['status'] ?? null,
            distance: $stored['distance'] ?? null,
            speed: $stored['speed'] ?? null,
            penaltyPoints: $stored['penaltyPoints'] ?? null,
            penalties: $stored['penalties'] ?? [],
            flightPlan: $stored['flightPlan'] ?? null,
        );
    }

    /**
     * @return array{raceId: ?int, startTime: ?int, taskStart: ?int, flightTime: ?int, taskTime: ?int, status: ?string, distance: ?float, speed: ?float, penaltyPoints: ?float, penalties: array<string, float>, flightPlan: ?string}
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** True when Condor counted the pilot as finished. */
    public function isFinished(): bool
    {
        return 'finished' === strtolower((string) $this->status);
    }

    /** "01:21:49" → seconds. */
    private static function time(?string $value): ?int
    {
        if (null === $value || 1 !== preg_match('/^(\d+):(\d{2}):(\d{2})$/', $value, $match)) {
            return null;
        }

        return (int) $match[1] * 3600 + (int) $match[2] * 60 + (int) $match[3];
    }

    /** "134.06km/h" → 134.06. */
    private static function number(?string $value): ?float
    {
        return null !== $value && 1 === preg_match('/^-?\d+(?:\.\d+)?/', $value, $match) ? (float) $match[0] : null;
    }
}
