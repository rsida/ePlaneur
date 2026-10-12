<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Flight\Analysis\Launch;
use App\Session\TaskSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Settings of a task, checked against the Condor Club pages of the same tasks.
 */
final class TaskSettingsTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../fixtures/condor/';

    public function testTheStopJoinOfARegattaComesFromTheStartOfItsTask(): void
    {
        // C3-c060: 14:45, delay 5 min, regatta; Condor's IGC export: TaskStart 15:05:00
        $settings = TaskSettings::fromArray(['source' => 'igc', 'startTime' => 14 * 60 + 45, 'startWindow' => 0, 'startDelay' => 5]);

        self::assertSame(15, $settings->joinTimeFrom(15 * 3600 + 5 * 60));
        self::assertSame(15 * 60 + 5, $settings->withJoinTime(15)->gateOpening());
    }

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
        self::assertSame(12 * 60 + 15, $settings->joinClosing(), 'the stop join of the club server');
        self::assertSame(12 * 60 + 20, $settings->gateOpening(), 'start at 12:23:06 in the IGC export of this race');
        self::assertSame(12 * 60 + 15, $settings->withJoinTime(10)->gateOpening(), 'the stop join of the session');
        self::assertNull($settings->joinTimeFrom(12 * 3600 + 23 * 60 + 6), 'a start window tells no stop join');
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
        self::assertSame([0, 5, 10], array_map(static fn (array $zone): int => \count($zone['polygon'] ?? []), $settings->weather), 'the corners of the drawn zones, in landscape metres');
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
        self::assertSame([0, 7, 7], array_map(static fn (array $zone): int => \count($zone['polygon'] ?? []), $settings->weather));
        self::assertSame([823614.75, 121713.93], $settings->weather[1]['polygon'][0] ?? null);
        self::assertSame(4000, $settings->weather[1]['border'] ?? null);
        self::assertSame(17.0, $settings->weather[0]['windSpeed']);
        self::assertSame('Printemps', $settings->season());

        self::assertEquals($settings, TaskSettings::fromArray($settings->toArray()), 'Stored and read back');
    }

    /**
     * @return iterable<string, array{int, Launch}>
     */
    public static function launches(): iterable
    {
        // Flight plans of the club saved with each launch (2026-10-12): C3-d010a, C3-s121, C3-c034
        yield 'aerotow' => [0, Launch::Aerotow];
        yield 'winch' => [1, Launch::Winch];
        yield 'airborne' => [2, Launch::Airborne];
    }

    #[DataProvider('launches')]
    public function testTheLaunchOfTheFlightPlan(int $startType, Launch $expected): void
    {
        $settings = TaskSettings::fromFpl("[Task]\r\nLandscape=AA3\r\nCount=0\r\n[GameOptions]\r\nStartType={$startType}\r\nStartHeight=1500\r\n");

        self::assertSame($expected, $settings->launch);
    }

    public function testAnotherFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TaskSettings::fromFpl("Rank,Status,Player\n1,Finished,Alice\n");
    }
}
