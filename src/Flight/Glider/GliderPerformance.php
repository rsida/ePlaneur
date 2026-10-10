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
 * shows above or below the curve: the difference is its netto, the vertical speed of the air.
 * The MacCready setting of a glide is the expected climb its airspeed is the speed to fly of.
 *
 * The netto vario of the flight, second by second, is the vario plus the sink of the polar at the
 * airspeed of that second; only in glides, the polar does not hold while circling.
 */
final readonly class GliderPerformance
{
    /** A glide shorter than this says little about the speed flown. */
    private const int MIN_GLIDE_TIME = 60;
    private const int MIN_GLIDE_DISTANCE = 2000;

    /**
     * @param array{speed: float, sink: float}                                                                                                           $minimumSink
     * @param array{speed: float, ratio: float}                                                                                                          $bestGlide
     * @param array{speed: float, sink: float, average: float}|null                                                                                      $speedToFly
     * @param array{speed: float, from: float}|null                                                                                                      $wind
     * @param list<array{start: int, airspeed: float, groundSpeed: float, sink: float, distance: float, ratio: ?float, netto: ?float, macCready: float}> $glides
     * @param list<array{float, float}>                                                                                                                  $curve
     * @param list<?float>                                                                                                                               $netto
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
        public ?float $glideNetto,
        public ?float $glideMacCready,
        public array $curve,
        public array $netto,
    ) {
    }

    /**
     * @param array<string, mixed> $analysis Flight::getAnalysis()
     */
    public static function of(Glider $glider, array $analysis): self
    {
        $water = isset($analysis['water']) ? (int) $analysis['water'] : null;
        $mass = $glider->massWith($water);
        // The polar at the mass of the flight
        $polar = $glider->getPolar()->at($mass / $glider->getReferenceMass());
        $climb = isset($analysis['statistics']['climbAverage']) ? (float) $analysis['statistics']['climbAverage'] : null;
        /** @var list<array<string, mixed>> $phases */
        $phases = $analysis['phases'] ?? [];
        $wind = self::wind($phases);
        /** @var array<string, list<float>> $series */
        $series = $analysis['series'] ?? [];
        $glides = self::glides($phases, $series, $wind, $polar);
        // Averages of the glides, weighted by their distance
        $average = static function (string $key) use ($glides): ?float {
            $weighted = array_filter($glides, static fn (array $glide): bool => null !== $glide[$key]);
            $total = array_sum(array_column($weighted, 'distance'));

            return $total > 0 ? array_sum(array_map(static fn (array $glide): float => $glide[$key] * $glide['distance'], $weighted)) / $total : null;
        };

        // From the stall to the never exceed speed
        $curve = $polar->curve(1.0, $glider->getMinSpeed() ?? 0.0, $glider->getMaxSpeed());

        return new self(
            glider: $glider,
            water: $water,
            mass: $mass,
            wingLoading: $mass / $glider->getWingArea(),
            minimumSink: $polar->minimumSink(),
            bestGlide: $polar->bestGlide(),
            climb: $climb,
            speedToFly: null !== $climb && $climb > 0 ? $polar->speedToFly($climb) : null,
            wind: $wind,
            glides: $glides,
            glideSpeed: $average('airspeed'),
            glideNetto: $average('netto'),
            glideMacCready: $average('macCready'),
            curve: $curve,
            netto: self::netto($phases, $series, $wind, $polar),
        );
    }

    /**
     * Data of the polar chart of the flight page.
     *
     * @return array{curve: list<array{float, float}>, glides: list<array{float, float, int, ?float, float}>, speedToFly: ?float}
     */
    public function chartData(): array
    {
        return [
            'curve' => $this->curve,
            // Airspeed, sink rate, second of the flight, netto, MacCready setting
            'glides' => array_map(static fn (array $glide): array => [round($glide['airspeed'], 1), round($glide['sink'], 2), $glide['start'], null !== $glide['netto'] ? round($glide['netto'], 2) : null, $glide['macCready']], $this->glides),
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
     * @return list<array{start: int, airspeed: float, groundSpeed: float, sink: float, distance: float, ratio: ?float, netto: ?float, macCready: float}>
     */
    private static function glides(array $phases, array $series, ?array $wind, Polar $polar): array
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
                $airspeed = self::airspeed($groundSpeed, Geo::bearing($series['latitude'][$start], $series['longitude'][$start], $series['latitude'][$end], $series['longitude'][$end]), $wind);
            }
            $sink = -(float) $phase['gain'] / $phase['duration'];
            $polarSink = $polar->sinkAt($airspeed);
            $glides[] = [
                'start' => $start,
                'airspeed' => $airspeed,
                'groundSpeed' => $groundSpeed,
                'sink' => $sink,
                'distance' => (float) $phase['distance'],
                'ratio' => isset($phase['glideRatio']) ? (float) $phase['glideRatio'] : null,
                'netto' => null !== $polarSink ? $polarSink - $sink : null,
                'macCready' => $polar->macCreadyFor($airspeed),
            ];
        }

        return $glides;
    }

    /**
     * Netto vario of each second of the glides (m/s, null elsewhere): the vario plus the sink of
     * the polar at the airspeed of that second, its heading taken over five seconds.
     *
     * @param list<array<string, mixed>>            $phases
     * @param array<string, list<float>>            $series
     * @param array{speed: float, from: float}|null $wind
     *
     * @return list<?float>
     */
    private static function netto(array $phases, array $series, ?array $wind, Polar $polar): array
    {
        $count = \count($series['vario'] ?? []);
        $netto = array_fill(0, $count, null);
        foreach ($phases as $phase) {
            if ('glide' !== $phase['type']) {
                continue;
            }
            for ($i = (int) $phase['start'], $end = min((int) $phase['end'], $count - 1); $i <= $end; ++$i) {
                $from = max(0, $i - 2);
                $to = min($count - 1, $i + 2);
                $speed = (float) $series['speed'][$i];
                $airspeed = null !== $wind && $to > $from
                    ? self::airspeed($speed, Geo::bearing($series['latitude'][$from], $series['longitude'][$from], $series['latitude'][$to], $series['longitude'][$to]), $wind)
                    : $speed;
                $sink = $polar->sinkAt($airspeed);
                if (null !== $sink) {
                    $netto[$i] = round($series['vario'][$i] + $sink, 2);
                }
            }
        }

        return $netto;
    }

    /**
     * Airspeed (km/h) of a ground velocity (km/h, heading in degrees) in a wind: the ground velocity
     * minus the wind, as vectors.
     *
     * @param array{speed: float, from: float} $wind
     */
    private static function airspeed(float $groundSpeed, float $heading, array $wind): float
    {
        $heading = deg2rad($heading);
        $to = deg2rad($wind['from'] + 180);

        return hypot(
            $groundSpeed * sin($heading) - $wind['speed'] * sin($to),
            $groundSpeed * cos($heading) - $wind['speed'] * cos($to),
        );
    }
}
