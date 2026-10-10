<?php

declare(strict_types=1);

namespace App\Flight\Glider;

use App\Entity\Glider;
use App\Flight\Geo\Geo;

/**
 * A flight against the polar of its glider: mass and wing loading with the water ballast of the
 * track, what the polar gives at that mass, the MacCready speed for the climbs of the flight, and
 * each glide placed on the polar.
 *
 * A track gives the ground speed, not the airspeed: the airspeed of a glide is estimated by taking
 * away the wind, the average drift of the thermals of the flight (Phase::$windSpeed, $windFrom).
 * Its sink rate is the height lost over its duration, so a glide through rising or sinking air
 * shows above or below the curve.
 */
final readonly class GliderPerformance
{
    /** A glide shorter than this says little about the speed flown. */
    private const int MIN_GLIDE_TIME = 60;
    private const int MIN_GLIDE_DISTANCE = 2000;

    /**
     * @param array{speed: float, sink: float}                                                                          $minimumSink
     * @param array{speed: float, ratio: float}                                                                         $bestGlide
     * @param array{speed: float, sink: float, average: float}|null                                                     $speedToFly
     * @param array{speed: float, from: float}|null                                                                     $wind
     * @param list<array{start: int, airspeed: float, groundSpeed: float, sink: float, distance: float, ratio: ?float}> $glides
     * @param list<array{float, float}>                                                                                 $curve
     */
    public function __construct(
        public Glider $glider,
        public ?int $water,
        public float $mass,
        public float $wingLoading,
        public array $minimumSink,
        public array $bestGlide,
        public ?float $climb,
        public ?array $speedToFly,
        public ?array $wind,
        public array $glides,
        public ?float $glideSpeed,
        public array $curve,
    ) {
    }

    /**
     * @param array<string, mixed> $analysis Flight::getAnalysis()
     */
    public static function of(Glider $glider, array $analysis): self
    {
        $water = isset($analysis['water']) ? (int) $analysis['water'] : null;
        $mass = $glider->massWith($water);
        $ratio = $mass / $glider->getReferenceMass();
        $polar = $glider->getPolar();
        $climb = isset($analysis['statistics']['climbAverage']) ? (float) $analysis['statistics']['climbAverage'] : null;
        /** @var list<array<string, mixed>> $phases */
        $phases = $analysis['phases'] ?? [];
        $wind = self::wind($phases);
        $glides = self::glides($phases, $analysis['series'] ?? [], $wind);
        $distance = array_sum(array_column($glides, 'distance'));
        $glideSpeed = $distance > 0 ? array_sum(array_map(static fn (array $glide): float => $glide['airspeed'] * $glide['distance'], $glides)) / $distance : null;

        // From the stall to the never exceed speed
        $curve = $polar->curve($ratio, $glider->getMinSpeed() ?? 0.0, $glider->getMaxSpeed());

        return new self(
            glider: $glider,
            water: $water,
            mass: $mass,
            wingLoading: $mass / $glider->getWingArea(),
            minimumSink: $polar->minimumSink($ratio),
            bestGlide: $polar->bestGlide($ratio),
            climb: $climb,
            speedToFly: null !== $climb && $climb > 0 ? $polar->speedToFly($climb, $ratio) : null,
            wind: $wind,
            glides: $glides,
            glideSpeed: $glideSpeed,
            curve: $curve,
        );
    }

    /**
     * Data of the polar chart of the flight page.
     *
     * @return array{curve: list<array{float, float}>, glides: list<array{float, float, int}>, speedToFly: ?float}
     */
    public function chartData(): array
    {
        return [
            'curve' => $this->curve,
            'glides' => array_map(static fn (array $glide): array => [round($glide['airspeed'], 1), round($glide['sink'], 2), $glide['start']], $this->glides),
            'speedToFly' => null !== $this->speedToFly ? round($this->speedToFly['speed']) : null,
        ];
    }

    /**
     * Average wind of the flight: the drift of its thermals, averaged as vectors weighted by their
     * duration.
     *
     * @param list<array<string, mixed>> $phases
     *
     * @return array{speed: float, from: float}|null
     */
    private static function wind(array $phases): ?array
    {
        $east = 0.0;
        $north = 0.0;
        $time = 0;
        foreach ($phases as $phase) {
            if ('thermal' !== $phase['type'] || null === ($phase['windSpeed'] ?? null) || null === ($phase['windFrom'] ?? null)) {
                continue;
            }
            // Where the wind goes, as a vector
            $to = deg2rad((float) $phase['windFrom'] + 180);
            $east += sin($to) * (float) $phase['windSpeed'] * $phase['duration'];
            $north += cos($to) * (float) $phase['windSpeed'] * $phase['duration'];
            $time += $phase['duration'];
        }
        if (0 === $time) {
            return null;
        }
        $east /= $time;
        $north /= $time;

        return ['speed' => hypot($east, $north), 'from' => fmod(rad2deg(atan2($east, $north)) + 180 + 360, 360)];
    }

    /**
     * Glides of the flight with their estimated airspeed and sink rate.
     *
     * @param list<array<string, mixed>>            $phases
     * @param array<string, list<float>>            $series
     * @param array{speed: float, from: float}|null $wind
     *
     * @return list<array{start: int, airspeed: float, groundSpeed: float, sink: float, distance: float, ratio: ?float}>
     */
    private static function glides(array $phases, array $series, ?array $wind): array
    {
        $glides = [];
        foreach ($phases as $phase) {
            if ('glide' !== $phase['type'] || $phase['duration'] < self::MIN_GLIDE_TIME || $phase['distance'] < self::MIN_GLIDE_DISTANCE) {
                continue;
            }
            $start = (int) $phase['start'];
            $end = (int) $phase['end'];
            $groundSpeed = (float) $phase['speed'];
            $airspeed = $groundSpeed;
            if (null !== $wind && isset($series['latitude'][$start], $series['latitude'][$end])) {
                // Ground velocity along the glide minus the wind
                $heading = deg2rad(Geo::bearing($series['latitude'][$start], $series['longitude'][$start], $series['latitude'][$end], $series['longitude'][$end]));
                $to = deg2rad($wind['from'] + 180);
                $airspeed = hypot(
                    $groundSpeed * sin($heading) - $wind['speed'] * sin($to),
                    $groundSpeed * cos($heading) - $wind['speed'] * cos($to),
                );
            }
            $sink = -(float) $phase['gain'] / $phase['duration'];
            $glides[] = [
                'start' => $start,
                'airspeed' => $airspeed,
                'groundSpeed' => $groundSpeed,
                'sink' => $sink,
                'distance' => (float) $phase['distance'],
                'ratio' => isset($phase['glideRatio']) ? (float) $phase['glideRatio'] : null,
            ];
        }

        return $glides;
    }
}
