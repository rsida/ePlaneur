<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Airfield;
use App\Entity\Flight;
use App\Entity\PathItem;
use App\Flight\Analysis\FlightMarks;
use App\Flight\FlightClouds;
use App\Flight\Geo\Geo;
use App\Repository\AirfieldRepository;
use App\Session\Penalties;

/**
 * Checks a flight against the criteria of an exercise of "Mon parcours", from its analysis: the
 * glider imposed, then each criterion (ExerciseCriterion). An exercise without criteria asks for a
 * finished task. Values read from the stored statistics are cheap; the others load the analysis.
 */
final class ExerciseCheck
{
    /** A landing counts on an airfield within half its runway and this margin of its centre. */
    private const float AIRFIELD_MARGIN = 200.0;

    /** Ground speed under which the glider has stopped at the end of the track. */
    private const float STOPPED = 10.0;

    /** @var array<string, list<Airfield>> */
    private array $airfields = [];

    public function __construct(
        private readonly AirfieldRepository $airfieldRepository,
    ) {
    }

    public function check(PathItem $item, Flight $flight): ExerciseResult
    {
        $lines = [];
        if (null !== $item->getGlider()) {
            $lines[] = [
                'label' => 'Planeur',
                'asked' => 'Planeur : '.$item->getGlider(),
                'measured' => $flight->getGlider(),
                'met' => null !== $flight->getGlider() && 0 === strcasecmp(trim($flight->getGlider()), $item->getGlider()),
            ];
        }
        foreach ($item->getCriteria() as ['criterion' => $criterion, 'value' => $value]) {
            $measured = $this->measure($criterion, $flight);
            $lines[] = [
                'label' => $criterion->label(),
                'asked' => $criterion->describe($value),
                'measured' => match (true) {
                    null === $measured => null,
                    \is_bool($measured) => $measured ? 'oui' : 'non',
                    default => ExerciseCriterion::format($measured, $criterion->unit()),
                },
                'met' => $criterion->isMet($measured, $value),
            ];
        }
        $rankBy = $item->getRankBy();
        $measure = $this->measure($rankBy, $flight);

        return new ExerciseResult($flight, $lines, \is_float($measure) ? $measure : null);
    }

    /**
     * What a flight did on one criterion, in the unit of the criterion; null when unknown.
     */
    public function measure(ExerciseCriterion $criterion, Flight $flight): float|bool|null
    {
        $statistics = $flight->getStatistics();
        $number = static fn (string $key, float $factor = 1.0): ?float => isset($statistics[$key]) ? round((float) $statistics[$key] * $factor, 2) : null;

        return match ($criterion) {
            ExerciseCriterion::TaskFinished => null !== $flight->getTaskSpeed(),
            ExerciseCriterion::TaskSpeed => $flight->getTaskSpeed(),
            ExerciseCriterion::Duration => round($flight->getDuration() / 60, 1),
            ExerciseCriterion::Distance => round($flight->getDistance() / 1000, 1),
            ExerciseCriterion::Thermals => $number('thermals'),
            ExerciseCriterion::ClimbAverage => $number('climbAverage'),
            ExerciseCriterion::CirclingShare => $number('circlingShare', 100),
            ExerciseCriterion::TurnBalance => isset($statistics['leftShare']) ? round(100 * min((float) $statistics['leftShare'], 1 - (float) $statistics['leftShare']), 1) : null,
            ExerciseCriterion::SpeedMax => $number('speedMax'),
            ExerciseCriterion::AltitudeMin => $number('altitudeMin'),
            ExerciseCriterion::AltitudeGain => round(FlightMarks::of($flight->getAnalysis())->gain),
            ExerciseCriterion::Recoveries => (float) \count($flight->getAnalysis()['recoveries'] ?? []),
            ExerciseCriterion::Penalties => $this->penalties($flight),
            ExerciseCriterion::LandedOnAirfield => $this->landedOnAirfield($flight),
        };
    }

    /** Points of penalty of the flight: Condor's when it counted them, the estimate otherwise. */
    private function penalties(Flight $flight): ?float
    {
        $settings = FlightClouds::settingsOf($flight);
        if (null === $settings) {
            return null;
        }
        $penalties = Penalties::of($flight->getAnalysis(), $settings);

        return (float) ($penalties['official'] ?? $penalties['estimated']);
    }

    /** Whether the glider stopped on an airfield of its landscape at the end of the flight. */
    private function landedOnAirfield(Flight $flight): ?bool
    {
        $landscape = $flight->getLandscape();
        if (null === $landscape) {
            return null;
        }
        $series = $flight->getAnalysis()['series'];
        $last = \count($series['time']) - 1;
        if ($last < 0 || $series['speed'][$last] > self::STOPPED) {
            return false;
        }
        $latitude = $series['latitude'][$last];
        $longitude = $series['longitude'][$last];
        $this->airfields[$landscape] ??= $this->airfieldRepository->findBy(['landscape' => $landscape]);

        return array_any($this->airfields[$landscape], static fn (Airfield $airfield): bool => Geo::distance($latitude, $longitude, $airfield->getLatitude(), $airfield->getLongitude()) <= $airfield->getLength() / 2 + self::AIRFIELD_MARGIN);
    }
}
