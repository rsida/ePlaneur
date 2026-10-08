<?php

declare(strict_types=1);

namespace App\Training;

/**
 * The eight steps of the club's guided training path (doc/project/training.md).
 */
enum TrainingStep: int
{
    case Discover = 1;
    case Solo = 2;
    case Network = 3;
    case Flaps = 4;
    case MacCready = 5;
    case FlightComputer = 6;
    case Condor3 = 7;
    case Theory = 8;

    public function label(): string
    {
        return match ($this) {
            self::Discover => 'Découvrir Condor et le pilotage',
            self::Solo => 'Devenir autonome en solo',
            self::Network => 'Voler en réseau',
            self::Flaps => 'Maîtriser les volets, insigne d’argent',
            self::MacCready => 'Voler à la vitesse MacCready',
            self::FlightComputer => 'Utiliser un calculateur (XCSoar)',
            self::Condor3 => 'Passer à Condor 3',
            self::Theory => 'Préparer le Brevet ePlaneur',
        };
    }

    /** "Étape 5 · Voler à la vitesse MacCready". */
    public function title(): string
    {
        return \sprintf('Étape %d · %s', $this->value, $this->label());
    }
}
