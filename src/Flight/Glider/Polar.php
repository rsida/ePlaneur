<?php

declare(strict_types=1);

namespace App\Flight\Glider;

/**
 * Polar of a glider: its sink rate at each airspeed, as Condor stores it in the .pol file of the
 * glider (Planes/<glider>/<glider>.pol).
 *
 * The file holds a count, then for each node six floats: the point (km/h, vertical speed in m/s,
 * negative), the handle towards the previous node and the handle towards the next one; two nodes
 * and the handles between them are a cubic Bézier curve (decoded on 2026-10-10: the minimum sink and
 * best glide of the curve match the sheet of the glider within 2 %, e.g. Diana 2: 0.47 m/s and 49.5
 * at 89 km/h, sheet 0.45 m/s and 50 at 91 km/h).
 *
 * The curve is the glider at its reference mass (Glider::getReferenceMass()). At another mass m,
 * speeds and sink rates are multiplied by √(m / reference): the glide ratio stays, at a higher
 * speed.
 */
final readonly class Polar
{
    /** Points sampled on each Bézier segment. */
    private const int SAMPLES = 40;

    /** @var list<array{float, float}> speed (km/h) and sink rate (m/s, positive), by speed */
    private array $points;

    /**
     * @param list<list<float>> $nodes [speed, vertical speed, in speed, in vertical speed, out speed, out vertical speed]
     */
    public function __construct(
        public array $nodes,
    ) {
        $points = [];
        for ($n = 0; $n + 1 < \count($nodes); ++$n) {
            [$x0, $y0, , , $x1, $y1] = $nodes[$n];
            [$x3, $y3, $x2, $y2] = $nodes[$n + 1];
            for ($k = 0 === $n ? 0 : 1; $k <= self::SAMPLES; ++$k) {
                $t = $k / self::SAMPLES;
                $u = 1 - $t;
                $points[] = [
                    $u ** 3 * $x0 + 3 * $u ** 2 * $t * $x1 + 3 * $u * $t ** 2 * $x2 + $t ** 3 * $x3,
                    -($u ** 3 * $y0 + 3 * $u ** 2 * $t * $y1 + 3 * $u * $t ** 2 * $y2 + $t ** 3 * $y3),
                ];
            }
        }
        usort($points, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $this->points = $points;
    }

    /** Polar of a Condor .pol file; null when the content is not one. */
    public static function fromCondor(string $content): ?self
    {
        if (\strlen($content) < 4) {
            return null;
        }
        $count = (int) (unpack('l', $content)[1] ?? 0);
        if ($count < 2 || $count > 50 || \strlen($content) < 4 + $count * 24) {
            return null;
        }
        $values = array_values(unpack(\sprintf('g%d', $count * 6), $content, 4) ?: []);
        $nodes = [];
        for ($n = 0; $n < $count; ++$n) {
            $node = array_map(static fn (float $v): float => round($v, 4), \array_slice($values, $n * 6, 6));
            if (\count(array_filter($node, is_finite(...))) < 6) {
                return null;
            }
            $nodes[] = $node;
        }

        return new self($nodes);
    }

    /**
     * Points of the curve at a mass ratio (mass / reference mass): speed (km/h), sink rate (m/s).
     *
     * @return list<array{float, float}>
     */
    public function points(float $massRatio = 1.0): array
    {
        $scale = sqrt($massRatio);

        return array_map(static fn (array $p): array => [$p[0] * $scale, $p[1] * $scale], $this->points);
    }

    /**
     * The curve every `step` km/h between two speeds (kept within the curve), for a chart.
     *
     * @return list<array{float, float}>
     */
    public function curve(float $massRatio, float $from, float $to, int $step = 2): array
    {
        $points = $this->points($massRatio);
        $curve = [];
        $last = (int) floor(min($to, end($points)[0] ?? 0));
        for ($speed = (int) ceil(max($from, $points[0][0] ?? 0)); $speed <= $last; $speed += $step) {
            $sink = $this->sinkAt($speed, $massRatio);
            if (null !== $sink) {
                $curve[] = [(float) $speed, round($sink, 2)];
            }
        }

        return $curve;
    }

    /** Sink rate (m/s) at an airspeed (km/h), null out of the curve. */
    public function sinkAt(float $speed, float $massRatio = 1.0): ?float
    {
        $points = $this->points($massRatio);
        for ($i = 1, $count = \count($points); $i < $count; ++$i) {
            [$v1, $w1] = $points[$i - 1];
            [$v2, $w2] = $points[$i];
            if ($speed >= $v1 && $speed <= $v2) {
                return $v2 > $v1 ? $w1 + ($w2 - $w1) * ($speed - $v1) / ($v2 - $v1) : $w1;
            }
        }

        return null;
    }

    /** @return array{speed: float, sink: float} */
    public function minimumSink(float $massRatio = 1.0): array
    {
        $best = null;
        foreach ($this->points($massRatio) as [$speed, $sink]) {
            if (null === $best || $sink < $best[1]) {
                $best = [$speed, $sink];
            }
        }

        return ['speed' => $best[0] ?? 0.0, 'sink' => $best[1] ?? 0.0];
    }

    /** @return array{speed: float, ratio: float} */
    public function bestGlide(float $massRatio = 1.0): array
    {
        $best = ['speed' => 0.0, 'ratio' => 0.0];
        foreach ($this->points($massRatio) as [$speed, $sink]) {
            $ratio = $sink > 0 ? $speed / 3.6 / $sink : 0.0;
            if ($ratio > $best['ratio']) {
                $best = ['speed' => $speed, 'ratio' => $ratio];
            }
        }

        return $best;
    }

    /**
     * MacCready speed to fly for an expected climb rate (m/s): the airspeed of the glides that gives
     * the best average speed over thermal and glide, and that average speed (still air).
     *
     * @return array{speed: float, sink: float, average: float}
     */
    public function speedToFly(float $climb, float $massRatio = 1.0): array
    {
        $best = ['speed' => 0.0, 'sink' => 0.0, 'average' => 0.0];
        if ($climb <= 0) {
            $minimum = $this->bestGlide($massRatio);

            return ['speed' => $minimum['speed'], 'sink' => $minimum['speed'] / 3.6 / max(1.0, $minimum['ratio']), 'average' => 0.0];
        }
        foreach ($this->points($massRatio) as [$speed, $sink]) {
            $average = $speed * $climb / ($climb + $sink);
            if ($average > $best['average']) {
                $best = ['speed' => $speed, 'sink' => $sink, 'average' => $average];
            }
        }

        return $best;
    }
}
