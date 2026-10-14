<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Levels the ranking of an exercise can be limited to, from the steps the trainers validated: a
 * pupil does not race the club's best pilots. The network step (3) makes an "ePilote".
 */
enum RankingLevel: string
{
    case All = 'tous';
    case Pupils = 'eleves';
    case Pilots = 'epilotes';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tous les niveaux',
            self::Pupils => 'Élèves (avant l’étape 3)',
            self::Pilots => 'ePilotes (étape 3 validée)',
        };
    }

    /** Whether a pilot with the network step validated or not is at this level. */
    public function accepts(bool $networkValidated): bool
    {
        return match ($this) {
            self::All => true,
            self::Pupils => !$networkValidated,
            self::Pilots => $networkValidated,
        };
    }
}
