<?php

declare(strict_types=1);

namespace App\Training;

/**
 * A criterion of an exercise as the back-office edits it (PathItem::getCriteriaSet()); reads as
 * "Vitesse circuit : au moins 80 km/h" in the list of the form.
 */
final class CriterionRow
{
    public function __construct(
        public ?ExerciseCriterion $criterion = null,
        public ?float $value = null,
    ) {
    }

    public function __toString(): string
    {
        return null !== $this->criterion ? $this->criterion->describe($this->value) : 'Nouveau critère';
    }
}
