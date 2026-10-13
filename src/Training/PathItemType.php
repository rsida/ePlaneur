<?php

declare(strict_types=1);

namespace App\Training;

/**
 * What a pilot does to complete an item of a step of "Mon parcours".
 */
enum PathItemType: string
{
    /** A page, a document or a video to read or watch; the pilot says when it is done. */
    case Reading = 'reading';
    /** A flight: on a flight plan, done by a finished task on it; without one, the pilot says when it is done. */
    case Exercise = 'exercise';
    /** A quiz, done with the pass mark. */
    case Quiz = 'quiz';
    /** The validation of the step by a trainer (the training path of the logbook). */
    case Validation = 'validation';

    public function label(): string
    {
        return match ($this) {
            self::Reading => 'Lecture ou vidéo',
            self::Exercise => 'Exercice en vol',
            self::Quiz => 'Quiz',
            self::Validation => 'Validation par un formateur',
        };
    }

    /** Icon of assets/icons/. */
    public function icon(): string
    {
        return match ($this) {
            self::Reading => 'file-text',
            self::Exercise => 'glider',
            self::Quiz => 'lightbulb',
            self::Validation => 'thumbs-up',
        };
    }
}
