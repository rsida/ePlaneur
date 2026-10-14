<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Badges of "Mon parcours": each one counts something a pilot does again and again and has three
 * tiers. Most of them reward good practice (landing on an airfield, flying without penalty, flying
 * with the club, regularly) rather than speed, which the rankings already reward. Computed from what
 * the pilot did, nothing stored; the badges of single flights (100 km, 5 h...) are in the logbook.
 */
enum PathBadge: string
{
    case Steps = 'steps';
    case Exercises = 'exercises';
    case Quizzes = 'quizzes';
    case Landings = 'landings';
    case CleanFlights = 'clean_flights';
    case NetworkFlights = 'network_flights';
    case Weeks = 'weeks';
    case Podiums = 'podiums';

    public function label(): string
    {
        return match ($this) {
            self::Steps => 'Explorateur',
            self::Exercises => 'Appliqué',
            self::Quizzes => 'Théoricien',
            self::Landings => 'Retour au terrain',
            self::CleanFlights => 'Vol propre',
            self::NetworkFlights => 'Pilote du club',
            self::Weeks => 'Régulier',
            self::Podiums => 'Podium',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Steps => 'Étapes du parcours terminées.',
            self::Exercises => 'Exercices en vol réussis, tous critères remplis.',
            self::Quizzes => 'Quiz réussis.',
            self::Landings => 'Vols terminés à l’arrêt sur un terrain.',
            self::CleanFlights => 'Vols sans aucun point de pénalité, en vol en réseau ou sur un plan de vol du club.',
            self::NetworkFlights => 'Vols en réseau avec le club.',
            self::Weeks => 'Semaines avec au moins un vol.',
            self::Podiums => 'Exercices où vous êtes dans les trois premiers du classement.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Steps => 'map',
            self::Exercises => 'crosshair',
            self::Quizzes => 'lightbulb',
            self::Landings => 'glider',
            self::CleanFlights => 'shield-check',
            self::NetworkFlights => 'users',
            self::Weeks => 'calendar',
            self::Podiums => 'trophy',
        };
    }

    /**
     * What is counted, by number: "1 étape terminée", "3 étapes terminées".
     */
    public function count(int $count): string
    {
        [$one, $many] = match ($this) {
            self::Steps => ['étape terminée', 'étapes terminées'],
            self::Exercises => ['exercice réussi', 'exercices réussis'],
            self::Quizzes => ['quiz réussi', 'quiz réussis'],
            self::Landings => ['atterrissage sur un terrain', 'atterrissages sur un terrain'],
            self::CleanFlights => ['vol sans pénalité', 'vols sans pénalité'],
            self::NetworkFlights => ['vol en réseau', 'vols en réseau'],
            self::Weeks => ['semaine de vol', 'semaines de vol'],
            self::Podiums => ['podium', 'podiums'],
        };

        return \sprintf('%d %s', $count, $count > 1 ? $many : $one);
    }

    /**
     * Counts that earn the bronze, silver and gold tiers.
     *
     * @return array{int, int, int}
     */
    public function thresholds(): array
    {
        return match ($this) {
            self::Steps => [1, 4, 8],
            self::Exercises, self::Quizzes => [1, 5, 15],
            self::Landings => [1, 10, 50],
            self::CleanFlights => [1, 5, 20],
            self::NetworkFlights => [1, 10, 30],
            self::Weeks => [4, 12, 26],
            self::Podiums => [1, 3, 10],
        };
    }
}
