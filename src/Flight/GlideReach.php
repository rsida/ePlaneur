<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Airfield;
use App\Entity\Flight;
use App\Repository\AirfieldRepository;

/**
 * The airfields around a flight and the glide ratio that says which can be reached, for the glide
 * cone of the viewer: an airfield is within reach when its distance over the height above it, less
 * an arrival MARGIN, is at most the safe ratio: SAFETY of the best glide ratio of the polar (at
 * most MAX_RATIO), or DEFAULT_RATIO without the polar. Straight lines: the relief in between and
 * the wind are not counted.
 */
final readonly class GlideReach
{
    private const float MARGIN = 200.0;
    private const float SAFETY = 0.7;
    private const float MAX_RATIO = 40.0;
    private const float DEFAULT_RATIO = 25.0;
    /** Degrees around the track where airfields are looked for (about 40 km). */
    private const float AROUND = 0.4;

    public function __construct(
        private AirfieldRepository $airfields,
    ) {
    }

    /**
     * @param float|null $bestGlide best glide ratio of the polar of the glider, when known
     *
     * @return array{ratio: float, margin: float, airfields: list<array{string, float, float, float}>}|null
     */
    public function of(Flight $flight, ?float $bestGlide): ?array
    {
        $series = $flight->getAnalysis()['series'];
        if (null === $flight->getLandscape() || [] === $series['latitude']) {
            return null;
        }
        $found = $this->airfields->findInBox(
            $flight->getLandscape(),
            min($series['latitude']) - self::AROUND,
            min($series['longitude']) - self::AROUND,
            max($series['latitude']) + self::AROUND,
            max($series['longitude']) + self::AROUND,
        );
        if ([] === $found) {
            return null;
        }

        return [
            'ratio' => null !== $bestGlide ? round(min(self::MAX_RATIO, $bestGlide * self::SAFETY), 1) : self::DEFAULT_RATIO,
            'margin' => self::MARGIN,
            'airfields' => array_map(static fn (Airfield $airfield): array => [$airfield->getName(), $airfield->getLatitude(), $airfield->getLongitude(), $airfield->getAltitude()], $found),
        ];
    }
}
