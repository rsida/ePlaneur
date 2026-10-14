<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training;

use App\Training\ExerciseCriterion;
use PHPUnit\Framework\TestCase;

/**
 * Criteria of the exercises: values to reach, not to pass, facts, and how they read.
 */
final class ExerciseCriterionTest extends TestCase
{
    public function testACriterionIsMetAtItsLimit(): void
    {
        self::assertTrue(ExerciseCriterion::TaskSpeed->isMet(80.0, 80.0));
        self::assertFalse(ExerciseCriterion::TaskSpeed->isMet(79.9, 80.0));
        self::assertTrue(ExerciseCriterion::Penalties->isMet(0.0, 0.0));
        self::assertFalse(ExerciseCriterion::Penalties->isMet(0.2, 0.0));
        self::assertTrue(ExerciseCriterion::LandedOnAirfield->isMet(true, null));
        self::assertFalse(ExerciseCriterion::LandedOnAirfield->isMet(false, null));
        self::assertFalse(ExerciseCriterion::ClimbAverage->isMet(null, 1.0), 'Not measured is not met');
    }

    public function testACriterionReadsWithItsValue(): void
    {
        self::assertSame('Vitesse circuit : au moins 80 km/h', ExerciseCriterion::TaskSpeed->describe(80.0));
        self::assertSame('Aucun point de pénalité', ExerciseCriterion::Penalties->describe(0.0));
        self::assertSame('Points de pénalité : au plus 2 points', ExerciseCriterion::Penalties->describe(2.0));
        self::assertSame('Vario moyen en thermique : au moins 1,5 m/s', ExerciseCriterion::ClimbAverage->describe(1.5));
        self::assertSame('Posé sur un terrain', ExerciseCriterion::LandedOnAirfield->describe(null));
        self::assertFalse(ExerciseCriterion::Penalties->higherIsBetter());
    }
}
