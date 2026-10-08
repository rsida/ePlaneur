<?php

declare(strict_types=1);

namespace App\Flight\Reader;

use App\Flight\Fix;
use App\Flight\Geo\Geo;
use App\Flight\Track;

/**
 * Reads a flight track whatever its format (Condor .ftr, IGC), recognised from its content, and
 * keeps its longest continuous part: Condor moves the glider at once in some cases (500 m higher
 * after a crash or after the finish), which would read as impossible climbs.
 */
final readonly class TrackReader
{
    /** Speeds no glider reaches: a jump between two fixes beyond them is a discontinuity, m/s. */
    private const float MAX_HORIZONTAL_SPEED = 150.0;
    private const float MAX_VERTICAL_SPEED = 40.0;

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

        return $track->withFixes(self::longestContinuousPart($track->fixes));
    }

    /**
     * @param list<Fix> $fixes
     *
     * @return list<Fix>
     */
    private static function longestContinuousPart(array $fixes): array
    {
        $parts = [[$fixes[0]]];
        for ($i = 1, $count = \count($fixes); $i < $count; ++$i) {
            $previous = $fixes[$i - 1];
            $fix = $fixes[$i];
            $elapsed = max($fix->time - $previous->time, 0.05);
            $horizontal = Geo::distance($previous->latitude, $previous->longitude, $fix->latitude, $fix->longitude) / $elapsed;
            $vertical = abs($fix->altitude - $previous->altitude) / $elapsed;
            if ($horizontal > self::MAX_HORIZONTAL_SPEED || $vertical > self::MAX_VERTICAL_SPEED) {
                if ($horizontal <= self::MAX_HORIZONTAL_SPEED) {
                    // A jump upwards only: Condor lifts the glider at the very fix where it crosses
                    // the finish line. That position ends the part, at the altitude it had
                    $parts[\count($parts) - 1][] = new Fix($fix->time, $fix->latitude, $fix->longitude, $previous->altitude);
                }
                $parts[] = [];
            }
            $parts[\count($parts) - 1][] = $fix;
        }
        usort($parts, static fn (array $a, array $b): int => \count($b) <=> \count($a));

        return $parts[0];
    }
}
