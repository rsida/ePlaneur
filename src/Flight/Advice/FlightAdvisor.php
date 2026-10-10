<?php

declare(strict_types=1);

namespace App\Flight\Advice;

use App\Flight\Analysis\FlightMarks;
use App\Flight\Glider\GliderPerformance;
use App\Session\TaskSettings;
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
 * - glide speed against climb rate: with the polar of the glider (GliderPerformance), the MacCready
 *   speed for the average climb of the flight at its wing loading, against the estimated airspeed of
 *   the glides: 20 km/h slower or 30 km/h faster is worth a look, within 20 km/h is good. Without
 *   the polar, about 95 km/h plus 20 km/h per m/s for a 15 m glider, and only the slow side
 *   (straight flight includes ridge running, ballasted gliders fly much faster);
 * - the marks of the Silver badge (step 4): 1 h of flight, 1,000 m gained from a low point, 50 km
 *   from the take-off;
 * - transitions that wander: 20 % or more longer than the straight lines (step 5);
 * - spiral wider than the one of the best climbs of the flight by 20 % or more (step 4);
 * - final glide too high: 150 m or more above the minimum finish altitude of the task, when the
 *   settings of the session are known (step 5);
 * - centring: 45 s or more on average before the first good circle, over three thermals or more
 *   (step 4), 20 s or less is good;
 * - leaving late: 2 min or more in all at the top of the thermals climbing slower than the average
 *   of the flight (step 5);
 * - lift crossed in straight flight: 200 m or more gained that way is good dolphin flight; crossed
 *   twice or more at twice the climb of the thermal circled next (one minute or more), it was worth
 *   a few turns (step 5);
 * - thermals of the other pilots of the race missed (SessionLift), twice or more, at least as
 *   strong as the average climb of the flight (step 5).
 */
final class FlightAdvisor
{
    private const float MANY_CIRCLES = 0.40;
    private const float FEW_CIRCLES = 0.25;
    private const float WEAK_CLIMB = 1.0;
    private const int WEAK_MIN_DURATION = 60;
    private const float NO_GAIN = 50.0;
    private const float ONE_SIDE = 0.85;
    private const float WANDERING = 0.20;
    private const float WIDE_SPIRAL = 1.20;
    private const float HIGH_FINISH = 150.0;
    private const int MIN_GLIDES = 3;
    private const float SLOW_GLIDES = 20.0;
    private const float FAST_GLIDES = 30.0;
    private const int MIN_CENTRED = 3;
    private const int SLOW_CENTRING = 45;
    private const int QUICK_CENTRING = 20;
    private const int LATE_EXITS = 120;
    private const int MIN_CROSSINGS = 2;
    private const float DOLPHIN_GAIN = 200.0;
    private const float MISSED_LIFT = 2.0;

    /**
     * @param array<string, mixed>   $analysis
     * @param TaskSettings|null      $settings    of the session of the flight, when known
     * @param GliderPerformance|null $performance the flight against the polar of its glider, when known
     * @param array<string, mixed>   $sessionLift the lift the other pilots of the race found (SessionLift::around())
     *
     * @return list<Advice>
     */
    public function advise(array $analysis, ?TaskSettings $settings = null, ?GliderPerformance $performance = null, array $sessionLift = []): array
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
        $polarAdvice = null !== $performance ? self::speedToFly($performance) : null;
        if (null !== $polarAdvice) {
            $advice[] = $polarAdvice;
        } elseif (null !== $glideSpeed && null !== $average && $average > 0) {
            $expected = 95 + 20 * $average;
            if ($glideSpeed < $expected - 20) {
                $advice[] = new Advice(Advice::TIP, 'Transitions lentes', \sprintf(
                    'Transitions à %d km/h pour des thermiques à %s m/s : la vitesse MacCready est plutôt autour de %d km/h (planeur de 15 m). Accélérer entre les thermiques fait gagner plus de temps que la hauteur perdue n’en coûte.',
                    round($glideSpeed), self::number($average), round($expected),
                ), TrainingStep::MacCready);
            }
        }

        $detour = $statistics['glideDetour'] ?? null;
        if (null !== $detour && $detour >= self::WANDERING) {
            $advice[] = new Advice(Advice::TIP, 'Des transitions qui serpentent', \sprintf(
                'Vos transitions se sont écartées de %d %% des lignes droites. Choisissez la prochaine ascendance avant de quitter la précédente, puis tenez le cap.',
                round($detour * 100),
            ), TrainingStep::MacCready);
        }

        $radius = $statistics['spiralRadius'] ?? null;
        $target = $statistics['spiralTarget'] ?? null;
        if (null !== $radius && null !== $target && $target > 0 && $radius >= $target * self::WIDE_SPIRAL) {
            $advice[] = new Advice(Advice::TIP, 'Une spirale large', \sprintf(
                'Rayon moyen de %d m, quand vos meilleures montées tournaient à %d m. Plus serré et mieux centré, le planeur reste dans le cœur du thermique.',
                round($radius), round($target),
            ), TrainingStep::Flaps);
        }

        $final = $analysis['task']['finalGlide'] ?? null;
        if (null !== $settings && null !== $settings->finishMinHeight && \is_array($final)) {
            $excess = $final['arrivalAltitude'] - $settings->finishMinHeight;
            if ($excess >= self::HIGH_FINISH) {
                $climb = $final['climb'] ?? null;
                $advice[] = new Advice(Advice::TIP, 'Une arrivée trop haute', \sprintf(
                    'Ligne d’arrivée franchie %d m au-dessus du minimum%s. Une finale calculée au plus juste, et accélérée, rapporte ce temps.',
                    round($excess),
                    null !== $climb && $climb > 0 ? \sprintf(' : au taux de votre dernière ascendance (%s m/s), %s de montée qui ne vous ont rien rapporté', self::number($climb), self::seconds($excess / $climb)) : '',
                ), TrainingStep::MacCready);
            }
        }

        $centred = array_values(array_filter(array_map(static fn (array $phase): ?int => $phase['entry'] ?? null, $thermals), static fn (?int $entry): bool => null !== $entry));
        if (\count($centred) >= self::MIN_CENTRED) {
            $entry = array_sum($centred) / \count($centred);
            if ($entry >= self::SLOW_CENTRING) {
                $advice[] = new Advice(Advice::TIP, 'Un centrage lent', \sprintf(
                    'En moyenne %s avant le premier bon tour (70 %% du meilleur) sur %d thermiques. Le radar de centrage de chaque thermique montre de quel côté décaler les cercles.',
                    self::seconds($entry), \count($centred),
                ), TrainingStep::Flaps);
            } elseif ($entry <= self::QUICK_CENTRING) {
                $advice[] = new Advice(Advice::GOOD, 'Un centrage rapide', \sprintf(
                    'En moyenne %s avant le premier bon tour sur %d thermiques : vous trouvez vite le cœur.',
                    self::seconds($entry), \count($centred),
                ), TrainingStep::Flaps);
            }
        }

        $late = array_filter($thermals, static fn (array $phase): bool => ($phase['exit'] ?? 0) > 0);
        $lateTime = array_sum(array_map(static fn (array $phase): int => $phase['exit'], $late));
        if (null !== $average && $lateTime >= self::LATE_EXITS) {
            $advice[] = new Advice(Advice::TIP, 'Des sorties tardives', \sprintf(
                '%s au total en haut de %d %s à monter moins vite que votre moyenne (%s m/s). Quand le thermique faiblit sous ce que la suite promet, partez : la frise des événements les montre.',
                self::seconds($lateTime), \count($late), \count($late) > 1 ? 'thermiques' : 'thermique', self::number($average),
            ), TrainingStep::MacCready);
        }

        $crossings = $analysis['crossings'] ?? [];
        $dolphin = array_sum(array_map(static fn (array $crossing): float => max(0.0, $crossing['gain']), $crossings));
        if ($dolphin >= self::DOLPHIN_GAIN) {
            $advice[] = new Advice(Advice::GOOD, 'Du vol en dauphin', \sprintf(
                '%s m gagnés en ligne droite dans %d ascendances traversées sans spiraler : autant de montée en moins à faire en thermique.',
                number_format($dolphin, 0, ',', ' '), \count($crossings),
            ), TrainingStep::MacCready);
        }
        $missed = self::missedLift($crossings, $analysis['phases']);
        if (\count($missed) >= self::MIN_CROSSINGS) {
            $advice[] = new Advice(Advice::TIP, 'Des ascendances laissées pour un thermique plus faible', \sprintf(
                '%d fois, une ascendance traversée en ligne droite (jusqu’à %s m/s en vario énergie totale) était au moins deux fois plus forte que le thermique spiralé ensuite. Quelques tours dedans auraient payé : la frise des événements les montre.',
                \count($missed), self::number(max(array_column($missed, 'vario'))),
            ), TrainingStep::MacCready);
        }

        $missedThermals = array_values(array_filter($sessionLift['thermals'] ?? [], static fn (array $thermal): bool => $thermal['missed'] && null !== $average && $thermal['climb'] >= $average));
        if (\count($missedThermals) >= self::MIN_CROSSINGS) {
            usort($missedThermals, static fn (array $a, array $b): int => $b['climb'] <=> $a['climb']);
            $best = $missedThermals[0];
            $advice[] = new Advice(Advice::TIP, 'Des thermiques de la course manqués', \sprintf(
                '%d thermiques où d’autres pilotes de la course ont monté au moins à votre moyenne (%s m/s), passés à moins de 500 m sans y tourner ; le meilleur à %s m/s (%s), frôlé à %d m. Ils sont en jaune sur la carte et la frise.',
                \count($missedThermals), self::number($average), self::number($best['climb']), implode(', ', $best['pilots']), $best['closest']['distance'],
            ), TrainingStep::MacCready);
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
        $flight = FlightMarks::of($analysis);
        $gain = $flight->gain;
        $distance = $flight->farthest;
        $statistics = $analysis['statistics'];
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

    /**
     * Lift crossed in straight flight at least MISSED_LIFT times as strong as the thermal circled
     * next, when that thermal lasted WEAK_MIN_DURATION or more.
     *
     * @param list<array<string, mixed>> $crossings
     * @param list<array<string, mixed>> $phases
     *
     * @return list<array<string, mixed>>
     */
    private static function missedLift(array $crossings, array $phases): array
    {
        $missed = [];
        foreach ($crossings as $crossing) {
            foreach ($phases as $phase) {
                if ('thermal' !== $phase['type'] || ($phase['start'] ?? 0) < $crossing['end']) {
                    continue;
                }
                if ($phase['duration'] >= self::WEAK_MIN_DURATION && $crossing['vario'] >= self::MISSED_LIFT * max(0.1, $phase['vario'])) {
                    $missed[] = $crossing;
                }
                break;
            }
        }

        return $missed;
    }

    /** Glide speed against the MacCready speed of the polar, with three glides or more. */
    private static function speedToFly(GliderPerformance $performance): ?Advice
    {
        $speedToFly = $performance->speedToFly;
        if (null === $speedToFly || null === $performance->glideSpeed || \count($performance->glides) < self::MIN_GLIDES || null === $performance->climb) {
            return null;
        }
        $flown = $performance->glideSpeed;
        $best = $speedToFly['speed'];
        $context = \sprintf(
            'Pour des thermiques à %s m/s, la polaire du %s à %s kg/m² donne une vitesse de transition de %d km/h (MacCready %s) ; vos transitions : %d km/h (vitesse air estimée, vent retiré).',
            self::number($performance->climb),
            $performance->glider->getModel(),
            self::number($performance->wingLoading),
            round($best),
            self::number($performance->climb),
            round($flown),
        );
        if ($flown < $best - self::SLOW_GLIDES) {
            return new Advice(Advice::TIP, 'Transitions lentes', $context.' Accélérer entre les thermiques fait gagner plus de temps que la hauteur perdue n’en coûte.', TrainingStep::MacCready);
        }
        if ($flown > $best + self::FAST_GLIDES) {
            return new Advice(Advice::TIP, 'Transitions rapides', $context.' Au-delà, la hauteur perdue coûte plus de temps de montée que la vitesse n’en fait gagner, sauf le long d’une pente ou sous une rue de nuages.', TrainingStep::MacCready);
        }

        return new Advice(Advice::GOOD, 'Vitesse de transition adaptée', $context, TrainingStep::MacCready);
    }

    private static function number(float $value): string
    {
        return number_format($value, 1, ',', ' ');
    }

    /** A short time: "1 min 24 s", "45 s". */
    private static function seconds(float $seconds): string
    {
        $seconds = (int) round($seconds);

        return $seconds >= 60 ? \sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60) : $seconds.' s';
    }

    private static function minutes(int|float $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        return $minutes >= 60 ? \sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60) : \sprintf('%d min', $minutes);
    }
}
