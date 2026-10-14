<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training;

use App\Training\BadgeTier;
use App\Training\BadgeView;
use App\Training\PathBadge;
use PHPUnit\Framework\TestCase;

/**
 * Tiers of a badge of "Mon parcours" and the way to the next one.
 */
final class BadgeViewTest extends TestCase
{
    public function testATierIsEarnedAtItsThreshold(): void
    {
        $none = new BadgeView(PathBadge::Landings, 0);
        self::assertNull($none->tier());
        self::assertSame(BadgeTier::Bronze, $none->nextTier());
        self::assertSame(1, $none->nextThreshold());

        $bronze = new BadgeView(PathBadge::Landings, 4);
        self::assertSame(BadgeTier::Bronze, $bronze->tier());
        self::assertSame(BadgeTier::Silver, $bronze->nextTier());
        self::assertSame(10, $bronze->nextThreshold());
        self::assertSame(33, $bronze->percent(), '3 of the 9 landings between bronze and silver');

        $gold = new BadgeView(PathBadge::Landings, 60);
        self::assertSame(BadgeTier::Gold, $gold->tier());
        self::assertNull($gold->nextTier());
        self::assertSame(100, $gold->percent());
    }

    public function testTheCountReadsWithItsNumber(): void
    {
        self::assertSame('1 vol sans pénalité', PathBadge::CleanFlights->count(1));
        self::assertSame('5 vols sans pénalité', PathBadge::CleanFlights->count(5));
    }
}
