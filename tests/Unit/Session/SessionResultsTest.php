<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Session\EplaneurSessions;
use App\Session\SessionResults;
use PHPUnit\Framework\TestCase;

final class SessionResultsTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../fixtures/eplaneur/';

    public function testTheResultsPageOfEplaneurIsRead(): void
    {
        $results = SessionResults::fromHtml((string) file_get_contents(self::FIXTURES.'results.html'));

        self::assertCount(11, $results);
        self::assertSame([
            'rank' => 1, 'status' => 'Finished', 'player' => 'Alice Martin', 'competitionId' => 'P01', 'registration' => 'F-C001',
            'glider' => 'Diana2', 'distance' => 205500, 'time' => 4781, 'speed' => 154.7, 'penalty' => 0.0, 'score' => 1000.0,
        ], $results[0]);
        self::assertSame('Crashed', $results[10]['status']);
        self::assertSame('Crashé', SessionResults::statusLabel('Crashed'));
    }

    public function testTheCsvFileOfTheServerIsRead(): void
    {
        $csv = "\xEF\xBB\xBFRank;Status;Player;CN;RN;Plane;Dist;Time;Speed;Penalty;Score\r\n"
            ."1;Finished;Alice.Martin;P01;F-C001;Diana2;205.5 km;01:19:41;154.7 km/h;0.0 p;1000.0 p\r\n"
            ."2;Landed;Bruno.Petit;P02;;Diana2;32,0;;;;81.2\r\n";
        $results = SessionResults::fromCsv($csv);

        self::assertCount(2, $results);
        self::assertSame(4781, $results[0]['time']);
        self::assertSame(32000, $results[1]['distance']);
        self::assertNull($results[1]['registration']);
        self::assertNull($results[1]['time']);
    }

    public function testATextWithoutResultsIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SessionResults::fromCsv("Nom;Prénom\nMartin;Alice\n");
    }

    public function testTheSessionListOfEplaneurIsRead(): void
    {
        $sessions = EplaneurSessions::parseSessions((string) file_get_contents(self::FIXTURES.'sessions.html'));

        self::assertCount(3, $sessions);
        self::assertSame('DSRace-971793592-on-2026-10-08', $sessions[0]['file']);
        self::assertSame('C3', $sessions[0]['folder']);
        self::assertSame('2026-10-08', $sessions[0]['date']->format('Y-m-d'));
        self::assertSame('C3-c046a', $sessions[0]['code']);
        self::assertSame(11, $sessions[0]['participants']);
        self::assertSame(['CdF2025V2-J1R1-StAuban-Challes', 'D2', 'AA3'], [$sessions[0]['title'], $sessions[0]['gliders'], $sessions[0]['landscape']]);
        self::assertSame('C2', $sessions[1]['folder']);
    }
}
