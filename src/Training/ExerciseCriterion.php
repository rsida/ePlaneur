<?php

declare(strict_types=1);

namespace App\Training;

/**
 * What the analysis of a flight checks for an exercise of "Mon parcours" (ExerciseCheck): a value
 * to reach (at least), not to pass (at most), or a fact (yes). Numeric criteria can also rank the
 * flights that pass an exercise.
 */
enum ExerciseCriterion: string
{
    case TaskFinished = 'task_finished';
    case LandedOnAirfield = 'landed_on_airfield';
    case Penalties = 'penalties';
    case Recoveries = 'recoveries';
    case TaskSpeed = 'task_speed';
    case Duration = 'duration';
    case Distance = 'distance';
    case AltitudeGain = 'altitude_gain';
    case Thermals = 'thermals';
    case ClimbAverage = 'climb_average';
    case CirclingShare = 'circling_share';
    case TurnBalance = 'turn_balance';
    case SpeedMax = 'speed_max';
    case AltitudeMin = 'altitude_min';

    public const string AT_LEAST = 'min';
    public const string AT_MOST = 'max';
    public const string FACT = 'yes';

    public function label(): string
    {
        return match ($this) {
            self::TaskFinished => 'Circuit terminé',
            self::LandedOnAirfield => 'Posé sur un terrain',
            self::Penalties => 'Points de pénalité',
            self::Recoveries => 'Récupérations d’altitude',
            self::TaskSpeed => 'Vitesse circuit',
            self::Duration => 'Durée de vol',
            self::Distance => 'Distance parcourue',
            self::AltitudeGain => 'Gain d’altitude depuis un point bas',
            self::Thermals => 'Thermiques exploités',
            self::ClimbAverage => 'Vario moyen en thermique',
            self::CirclingShare => 'Temps passé à spiraler',
            self::TurnBalance => 'Spirales du côté le moins pratiqué',
            self::SpeedMax => 'Vitesse maximale',
            self::AltitudeMin => 'Altitude la plus basse',
        };
    }

    public function kind(): string
    {
        return match ($this) {
            self::TaskFinished, self::LandedOnAirfield => self::FACT,
            self::Penalties, self::Recoveries, self::CirclingShare, self::SpeedMax => self::AT_MOST,
            default => self::AT_LEAST,
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::TaskSpeed, self::SpeedMax => 'km/h',
            self::Duration => 'min',
            self::Distance => 'km',
            self::AltitudeGain, self::AltitudeMin => 'm',
            self::ClimbAverage => 'm/s',
            self::CirclingShare, self::TurnBalance => '%',
            self::Penalties => 'points',
            default => '',
        };
    }

    /** How the criterion reads with its value: "Vitesse circuit : au moins 80 km/h". */
    public function describe(?float $value): string
    {
        if (0.0 === (float) $value && self::Penalties === $this) {
            return 'Aucun point de pénalité';
        }
        if (0.0 === (float) $value && self::Recoveries === $this) {
            return 'Aucune récupération d’altitude';
        }

        return match ($this->kind()) {
            self::FACT => $this->label(),
            self::AT_MOST => \sprintf('%s : au plus %s', $this->label(), self::format($value ?? 0.0, $this->unit())),
            default => \sprintf('%s : au moins %s', $this->label(), self::format($value ?? 0.0, $this->unit())),
        };
    }

    /** Whether a measured value meets the criterion. */
    public function isMet(float|bool|null $measured, ?float $value): bool
    {
        if (null === $measured) {
            return false;
        }

        return match ($this->kind()) {
            self::FACT => true === $measured,
            self::AT_MOST => (float) $measured <= ($value ?? 0.0),
            default => (float) $measured >= ($value ?? 0.0),
        };
    }

    /** For a ranking: whether a higher value is better. */
    public function higherIsBetter(): bool
    {
        return self::AT_MOST !== $this->kind();
    }

    public function isNumeric(): bool
    {
        return self::FACT !== $this->kind();
    }

    public static function format(float $value, string $unit): string
    {
        $decimals = \in_array($unit, ['m/s'], true) ? 1 : (\in_array($unit, ['km/h', 'km', 'points'], true) && floor($value) !== $value ? 1 : 0);

        return trim(number_format($value, $decimals, ',', ' ').' '.$unit);
    }
}
