<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Flight\Analysis\Launch;
use App\Session\TaskSettings;
use PHPUnit\Framework\TestCase;

/**
 * Settings of a task, checked against the Condor Club pages of the same tasks.
 */
final class TaskSettingsTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../fixtures/condor/';

    public function testTheFlightPlanOfARaceIsRead(): void
    {
        // C3-s117 of 2026-03-05; Condor Club: 15 August 2025, 12:00, delay 5 min, window 15 min,
        // aerotow to 1,500 m, wind 180° 20 km/h, clouds 1,982 m above the ground
        $settings = TaskSettings::fromFpl((string) file_get_contents(self::FIXTURES.'Race 560447479 on 2026.03.05.fpl'));

        self::assertSame('fpl', $settings->source);
        self::assertSame('2025-08-15', $settings->date?->format('Y-m-d'));
        self::assertSame(12 * 60, $settings->startTime);
        self::assertSame(15, $settings->startWindow);
        self::assertSame(5, $settings->startDelay);
        self::assertFalse($settings->isRegatta());
        self::assertSame(12 * 60 + 5, $settings->gateOpening());
        self::assertSame(Launch::Aerotow, $settings->launch);
        self::assertSame(1500, $settings->startHeight);
        self::assertSame(1600, $settings->startMaxHeight);
        self::assertSame(500, $settings->finishMinHeight);
        self::assertSame(170, $settings->maxStartSpeed);
        self::assertSame(['CloudFlying' => 200, 'PlaneRecovery' => 100, 'HeightRecovery' => 200, 'WrongWindowEnterance' => 50, 'WindowCollision' => 20, 'AirspaceEnterance' => 20, 'PenaltyZoneEnterance' => 50, 'ThermalHelpers' => 200, 'StartSpeed' => 2, 'HighStart' => 1, 'LowFinish' => 1], $settings->penalties);
        self::assertTrue($settings->options['PDA']);
        self::assertFalse($settings->options['Smoke']);
        self::assertSame(3.0, $settings->thermalHelpersRange);
        self::assertSame('Été', $settings->season());

        $base = $settings->weather[0];
        self::assertSame('Base', $base['name']);
        self::assertSame(180.0, $base['windDirection']);
        self::assertEqualsWithDelta(20.0, $base['windSpeed'], 0.5);
        self::assertSame(1982, $base['cloudBase']);
        self::assertSame('Faible', TaskSettings::strengthLabel($base['thermalStrength']));
        self::assertSame('Normale', TaskSettings::activityLabel($base['thermalActivity']));
        self::assertSame(['Base', 'orage', 'Vercors'], array_column($settings->weather, 'name'));
        self::assertSame('Sud', TaskSettings::compass(180));
    }

    public function testACondorClubTaskIsRead(): void
    {
        // C3-c046a; Condor Club: 31 May 2024, 13:00, regatta start in flight at 1,100 m, minimum
        // finish height 330 m, clouds 1,525 m, wind 283° 17 km/h
        $settings = TaskSettings::fromCondorClub(json_decode((string) file_get_contents(self::FIXTURES.'condor-club-task-27105.json'), true));

        self::assertSame('condor.club', $settings->source);
        self::assertSame('2024-05-31', $settings->date?->format('Y-m-d'));
        self::assertSame(13 * 60, $settings->startTime);
        self::assertTrue($settings->isRegatta());
        self::assertSame(5, $settings->startDelay);
        self::assertSame(Launch::Airborne, $settings->launch);
        self::assertSame(1100, $settings->startHeight);
        self::assertSame(330, $settings->finishMinHeight);
        self::assertSame(45.0, $settings->maxWingLoading);
        self::assertSame(200, $settings->penalties['CloudFlying']);
        self::assertTrue($settings->options['PDA']);
        self::assertSame(1525, $settings->weather[0]['cloudBase']);
        self::assertSame(17.0, $settings->weather[0]['windSpeed']);
        self::assertSame('Printemps', $settings->season());

        self::assertEquals($settings, TaskSettings::fromArray($settings->toArray()), 'Stored and read back');
    }

    public function testAnotherFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaskSettings::fromFpl("Rank,Status,Player\n1,Finished,Alice\n");
    }
}
