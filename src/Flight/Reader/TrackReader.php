<?php

declare(strict_types=1);

namespace App\Flight\Reader;

use App\Flight\Fix;
use App\Flight\Geo\Geo;
use App\Flight\Track;

/**
 * Reads a flight track whatever its format (Condor .ftr, IGC), recognised from its content, and
 * keeps its longest continuous part: Condor moves the glider at once in some cases, which would read
 * as impossible moves.
 *
 * A height recovery (the pilot asks Condor for height: the glider is put about 500 m higher at the
 * same place, 200 points of penalty on the club's races) does not cut the track: it is recorded on
 * the track (Track::$recoveries) and the analysis leaves it out of the climbs. Found on the IGC
 * exports of 2026-10-11: +501 m in the middle of C3-c058, whose task was lost when the track was cut
 * there, and +495 m and +498 m as the glider crossed the finish of two other races.
 */
final readonly class TrackReader
{
    /**
     * Speeds no glider reaches, m/s: a jump between two fixes beyond them, and longer than
     * MIN_JUMP, is a discontinuity. A pull-up at 250 km/h climbs 4 m in the 0.1 s between two
     * Condor records (40 to 50 m/s), so the vertical limit is the fastest airspeed of a glider; the
     * jumps of Condor are hundreds of metres at once.
     */
    private const float MAX_HORIZONTAL_SPEED = 150.0;
    private const float MAX_VERTICAL_SPEED = 80.0;

    /** Metres under which a move between two fixes is not a jump, whatever its speed. */
    private const float MIN_JUMP = 30.0;

    public function __construct(
        private FtrReader $ftr,
        private IgcReader $igc,
    ) {
    }

    public function read(string $content): Track
    {
        $track = match (true) {
            $this->ftr->supports($content) => $this->ftr->read($content),
            $this->igc->supports($content) => $this->igc->read($content),
            default => throw new InvalidTrackException('Format non reconnu : déposez une trace Condor 3 (.ftr) ou un fichier IGC.'),
        };

        [$fixes, $recoveries] = self::longestContinuousPart($track->fixes);

        return $track->withFixes($fixes, $recoveries);
    }

    /**
     * @param list<Fix> $fixes
     *
     * @return array{list<Fix>, list<array{time: float, height: float}>} the fixes of the part, and its height recoveries
     */
    private static function longestContinuousPart(array $fixes): array
    {
        $parts = [[$fixes[0]]];
        $recoveries = [[]];
        for ($i = 1, $count = \count($fixes); $i < $count; ++$i) {
            $previous = $fixes[$i - 1];
            $fix = $fixes[$i];
            $elapsed = max($fix->time - $previous->time, 0.05);
            $distance = Geo::distance($previous->latitude, $previous->longitude, $fix->latitude, $fix->longitude);
            $height = abs($fix->altitude - $previous->altitude);
            $horizontalJump = $distance / $elapsed > self::MAX_HORIZONTAL_SPEED && $distance > self::MIN_JUMP;
            $verticalJump = $height / $elapsed > self::MAX_VERTICAL_SPEED && $height > self::MIN_JUMP;
            if ($verticalJump && !$horizontalJump && $fix->altitude > $previous->altitude) {
                // Height recovery: higher at the same place, the flight goes on
                $recoveries[\count($parts) - 1][] = ['time' => $fix->time, 'height' => $fix->altitude - $previous->altitude];
            } elseif ($horizontalJump || $verticalJump) {
                $parts[] = [];
                $recoveries[] = [];
            }
            $parts[\count($parts) - 1][] = $fix;
        }
        $longest = 0;
        foreach ($parts as $k => $part) {
            if (\count($part) > \count($parts[$longest])) {
                $longest = $k;
            }
        }

        return [$parts[$longest], $recoveries[$longest]];
    }
}
