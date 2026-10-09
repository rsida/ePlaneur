<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Entity\FlightPlan;
use PHPUnit\Framework\TestCase;

final class FlightPlanTest extends TestCase
{
    public function testTheCodeIsFoundInATaskNameOrWhatWasTyped(): void
    {
        self::assertSame('C3-c060', FlightPlan::codeIn('C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur'));
        self::assertSame('C3-c046a', FlightPlan::codeIn('c3-C046A · CdF2025V2'));
        self::assertSame('C2-d009', FlightPlan::codeIn('Session sur C2-d009'));
        self::assertNull(FlightPlan::codeIn('C3-c06'), 'Three digits');
        self::assertNull(FlightPlan::codeIn('XC3-c060'), 'Not inside a word');
        self::assertNull(FlightPlan::codeIn(null));
    }

    public function testATaskNameOfTheClubDescribesTheFlightPlan(): void
    {
        self::assertSame(['title' => 'Laragne-Ventoux-Fayence', 'gliders' => 'D2', 'landscape' => 'AA3'], FlightPlan::describe('C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur'));
        self::assertSame(['title' => null, 'gliders' => null, 'landscape' => null], FlightPlan::describe('Tâche test'));
    }

    public function testTheCodeGivesTheVersionAndTheCategory(): void
    {
        $plan = new FlightPlan('C2-d009');
        self::assertSame(2, $plan->getCondorVersion());
        self::assertSame('Initiation', $plan->getCategory());
        self::assertSame('Compétition', (new FlightPlan('C3-c046a'))->getCategory());
        self::assertSame('Standard', (new FlightPlan('C3-s118'))->getCategory());
        self::assertSame('C3-s118 · StEynard', (string) (new FlightPlan('C3-s118'))->setTitle('StEynard'));
    }
}
