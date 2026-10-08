<?php

declare(strict_types=1);

namespace App\Flight\Advice;

use App\Flight\Geo\Geo;
use App\Training\TrainingStep;

/**
 * Advice on a flight from its analysis (FlightAnalysis::toArray()), tied to the training path.
 * Rules of thumb, not a judgement: they point at what to look at in the flight.
 *
 * - time circling: above 40 % the flight is slow, below 25 % with the task done it is efficient;
 * - weak thermals: climbing for a minute or more at less than 1 m/s, or at less than half the
 *   average climb of the flight, costs time when better ones exist (MacCready);
 * - circling without gain: three thermals or more left after gaining less than 50 m (searching or
 *   centring);
 * - turning always the same way (85 % or more, four thermals or more);
 * - glide speed against climb rate: the MacCready speed of a 15 m glider is about 95 km/h plus
 *   20 km/h per m/s of the climbs expected; 20 km/h slower is worth a look. No advice on flying too
 *   fast: straight flight includes ridge running, and ballasted gliders fly much faster;
 * - the marks of the Silver badge (step 4): 1 h of flight, 1,000 m gained from a low point, 50 km
 *   from the take-off.
 */
final class FlightAdvisor
{
    private const float MANY_CIRCLES = 0.40;
    private const float FEW_CIRCLES = 0.25;
    private const float WEAK_CLIMB = 1.0;
    private const int WEAK_MIN_DURATION = 60;
    private const float NO_GAIN = 50.0;
    private const float ONE_SIDE = 0.85;

    /**
     * @param array<string, mixed> $analysis
     *
     * @return list<Advice>
     */
    public function advise(array $analysis): array
    {
        $statistics = $analysis['statistics'];
        $thermals = array_values(array_filter($analysis['phases'], static fn (array $phase): bool => 'thermal' === $phase['type']));
        $advice = [];

        $circling = $statistics['circlingShare'] ?? null;
        $completed = (bool) ($analysis['task']['completed'] ?? false);
        if (null !== $circling && $circling > self::MANY_CIRCLES) {
            $advice[] = new Advice(Advice::TIP, 'Beaucoup de temps en spirale', \sprintf(
                '%d %% du vol en spirale : au-delà de 40 %%, la vitesse moyenne s’effondre. Ne prenez que les thermiques qui valent le détour, et montez moins haut dans les faibles.',
                round($circling * 100),
            ), TrainingStep::MacCready);
        } elseif (null !== $circling && $circling < self::FEW_CIRCLES && $completed) {
            $advice[] = new Advice(Advice::GOOD, 'Peu de temps en spirale', \sprintf(
                'Seulement %d %% du vol en spirale, circuit terminé : vous exploitez bien les ascendances sur votre route.',
                round($circling * 100),
            ), TrainingStep::MacCready);
        }

        $average = $statistics['climbAverage'] ?? null;
        $weak = array_filter($thermals, static fn (array $phase): bool => $phase['duration'] >= self::WEAK_MIN_DURATION
            && ($phase['vario'] < self::WEAK_CLIMB || (null !== $average && $phase['vario'] < $average / 2)));
        $weakTime = array_sum(array_column($weak, 'duration'));
        if (\count($weak) >= 2 || $weakTime >= 180) {
            $advice[] = new Advice(Advice::TIP, 'Des thermiques faibles acceptés', \sprintf(
                '%d %s à moins de %s m/s pendant %s au total, pour un vario moyen de %s m/s sur le vol. Avec un MacCready réglé sur les thermiques attendus, ces pompes-là se traversent.',
                \count($weak),
                \count($weak) > 1 ? 'thermiques' : 'thermique',
                self::number(max(self::WEAK_CLIMB, null !== $average ? $average / 2 : 0)),
                self::minutes($weakTime),
                null !== $average ? self::number($average) : '—',
            ), TrainingStep::MacCready);
        }

        $noGain = array_filter($thermals, static fn (array $phase): bool => $phase['gain'] < self::NO_GAIN);
        if (\count($noGain) >= 3) {
            $advice[] = new Advice(Advice::TIP, 'Des spirales sans gain', \sprintf(
                '%d spirales quittées après moins de %d m de gain (%s au total). Centrer plus vite, ou ne pas spiraler dans une pompe qui ne se confirme pas : revoyez-les sur la carte.',
                \count($noGain),
                self::NO_GAIN,
                self::minutes(array_sum(array_column($noGain, 'duration'))),
            ), TrainingStep::Flaps);
        }

        $left = $statistics['leftShare'] ?? null;
        if (null !== $left && \count($thermals) >= 4 && ($left >= self::ONE_SIDE || $left <= 1 - self::ONE_SIDE)) {
            $advice[] = new Advice(Advice::TIP, 'Toujours du même côté', \sprintf(
                '%d %% des spirales %s. Savoir spiraler des deux côtés permet de choisir le sens qui centre le mieux le thermique, et de suivre le sens des autres planeurs.',
                round(max($left, 1 - $left) * 100),
                $left >= self::ONE_SIDE ? 'à gauche' : 'à droite',
            ), TrainingStep::Discover);
        }

        $glideSpeed = $statistics['glideSpeed'] ?? null;
        if (null !== $glideSpeed && null !== $average && $average > 0) {
            $expected = 95 + 20 * $average;
            if ($glideSpeed < $expected - 20) {
                $advice[] = new Advice(Advice::TIP, 'Transitions lentes', \sprintf(
                    'Transitions à %d km/h pour des thermiques à %s m/s : la vitesse MacCready est plutôt autour de %d km/h (planeur de 15 m). Accélérer entre les thermiques fait gagner plus de temps que la hauteur perdue n’en coûte.',
                    round($glideSpeed), self::number($average), round($expected),
                ), TrainingStep::MacCready);
            }
        }

        $badge = self::silverBadge($analysis);
        if (null !== $badge) {
            $advice[] = $badge;
        }

        return $advice;
    }

    /**
     * The three marks of the Silver badge, when the flight reaches at least one.
     *
     * @param array<string, mixed> $analysis
     */
    private static function silverBadge(array $analysis): ?Advice
    {
        $series = $analysis['series'];
        $statistics = $analysis['statistics'];
        $from = (int) ($statistics['takeoff'] ?? 0);
        $to = (int) ($statistics['landing'] ?? \count($series['time']) - 1);
        $low = \INF;
        $gain = 0.0;
        $distance = 0.0;
        for ($i = $from; $i <= $to; ++$i) {
            $low = min($low, $series['altitude'][$i]);
            $gain = max($gain, $series['altitude'][$i] - $low);
            if (0 === $i % 10) {
                $distance = max($distance, Geo::distance($series['latitude'][$from], $series['longitude'][$from], $series['latitude'][$i], $series['longitude'][$i]));
            }
        }
        $marks = [
            ['1 h de vol', $statistics['duration'] >= 3600, self::minutes($statistics['duration'])],
            ['1 000 m de gain', $gain >= 1000, \sprintf('%s m', number_format($gain, 0, ',', ' '))],
            ['50 km du départ', $distance >= 50000, \sprintf('%s km', number_format($distance / 1000, 1, ',', ' '))],
        ];
        $reached = \count(array_filter($marks, static fn (array $mark): bool => $mark[1]));
        if (0 === $reached) {
            return null;
        }
        $text = implode(' · ', array_map(static fn (array $mark): string => \sprintf('%s %s (%s)', $mark[1] ? '✓' : '✗', $mark[0], $mark[2]), $marks));

        return new Advice(
            3 === $reached ? Advice::GOOD : Advice::INFO,
            3 === $reached ? 'Repères de l’insigne d’argent atteints' : 'Repères de l’insigne d’argent',
            $text.'. Indicatif : les épreuves se valident sur le Condor Club.',
            TrainingStep::Flaps,
        );
    }

    private static function number(float $value): string
    {
        return number_format($value, 1, ',', ' ');
    }

    private static function minutes(int|float $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        return $minutes >= 60 ? \sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60) : \sprintf('%d min', $minutes);
    }
}
