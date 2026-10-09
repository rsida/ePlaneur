<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\FlightExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Flight values for people: durations, distances, gaps, moments, flight plan names.
 */
final class FlightExtensionTest extends TestCase
{
    /**
     * @return iterable<array{string, string}>
     */
    public static function flightPlans(): iterable
    {
        yield 'club naming' => ['C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur', 'C3-c060 Laragne – Ventoux – Fayence'];
        yield 'letter after the number' => ['C3-c056a_3eFAI-EUQ-J5-Zeltweg_JS318_Slov3  ePlaneur', 'C3-c056a 3eFAI – EUQ – J5 – Zeltweg'];
        yield 'other name' => ['Mon_plan', 'Mon plan'];
    }

    #[DataProvider('flightPlans')]
    public function testFlightPlanNames(string $name, string $expected): void
    {
        self::assertSame($expected, FlightExtension::flightPlanName($name));
    }

    public function testTimes(): void
    {
        self::assertSame('1 h 02 min', FlightExtension::duration(3725));
        self::assertSame('+2 min 05 s', FlightExtension::gap(125));
        self::assertSame("\u{2212}45 s", FlightExtension::gap(-45));
        self::assertSame('1:02:05', FlightExtension::clock(3725));
        self::assertSame('21:49', FlightExtension::clock(1309));
    }
}
