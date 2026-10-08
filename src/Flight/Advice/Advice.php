<?php

declare(strict_types=1);

namespace App\Flight\Advice;

use App\Training\TrainingStep;

/**
 * One piece of advice on a flight (FlightAdvisor): something to work on, something done well, or
 * where the flight stands, with the step of the training path it belongs to.
 */
final readonly class Advice
{
    public const string TIP = 'tip';
    public const string GOOD = 'good';
    /** Neither a fault nor a success: where the flight stands (Silver badge marks). */
    public const string INFO = 'info';

    /**
     * @param self::TIP|self::GOOD|self::INFO $kind
     */
    public function __construct(
        public string $kind,
        public string $title,
        public string $text,
        public ?TrainingStep $step = null,
    ) {
    }
}
