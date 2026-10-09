<?php

declare(strict_types=1);

namespace App\Tests\Unit\Session;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Session\CondorClubTasks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CondorClubTasksTest extends TestCase
{
    public function testTheSettingsOfATaskAreReadFromCondorClub(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            self::assertSame('https://condor.club/api/task/27105', $url);

            return new MockResponse((string) file_get_contents(__DIR__.'/../../fixtures/condor/condor-club-task-27105.json'), ['response_headers' => ['content-type' => 'application/json']]);
        });

        self::assertSame(330, (new CondorClubTasks($client))->settings(27105)->finishMinHeight);
    }

    public function testAnUnknownTaskIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CondorClubTasks(new MockHttpClient(new MockResponse('{"error":"not found"}', ['http_code' => 404]))))->settings(1);
    }

    public function testTheTaskIdIsReadFromTheLinkOfTheSession(): void
    {
        $session = new NetworkSession(new FlightPlan('C3-c046a'), new \DateTimeImmutable('2026-10-08'), new \DateTimeImmutable());
        self::assertNull($session->getCondorClubTaskId());
        self::assertSame(27105, $session->setCondorClubUrl('https://condor.club/task/27105')->getCondorClubTaskId());
        self::assertSame(27105, $session->setCondorClubUrl('https://www.condor.club/fr/task/27105/results')->getCondorClubTaskId());
        self::assertNull($session->setCondorClubUrl('https://www.condor.club/showcircuit/0/12345/')->getCondorClubTaskId());
    }
}
