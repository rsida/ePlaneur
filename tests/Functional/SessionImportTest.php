<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Repository\NetworkSessionRepository;
use App\Session\EplaneurSessions;
use App\Session\FlightPlans;
use App\Session\SessionImporter;
use App\Session\SessionLinker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Import of the sessions of eplaneur.fr: list, dedicated sessions, results; run twice, nothing is
 * duplicated.
 */
final class SessionImportTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__.'/../fixtures/eplaneur/';

    public function testTheSessionsOfEplaneurAreImportedOnce(): void
    {
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;
            if (str_contains($url, 'lire-tableau-csv-resultats-session.php')) {
                return new MockResponse((string) file_get_contents(self::FIXTURES.'results.html'));
            }
            // The list, and the list of the Epitech sessions (the C2-d009 one)
            if (str_contains($url, 'session_dediee=Epitech')) {
                return new MockResponse(preg_replace('#<tbody>.*?</tr>(.*?</tr>).*</tbody>#s', '<tbody>$1</tbody>', (string) file_get_contents(self::FIXTURES.'sessions.html')));
            }

            return new MockResponse(str_contains($url, 'session_dediee=') ? '<table></table>' : (string) file_get_contents(self::FIXTURES.'sessions.html'));
        }, EplaneurSessions::BASE_URL);
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $importer = new SessionImporter(
            new EplaneurSessions($client),
            $container->get(NetworkSessionRepository::class),
            $container->get(FlightPlans::class),
            $container->get(SessionLinker::class),
            $entityManager,
            new MockClock('2026-10-09 12:00'),
        );

        $report = $importer->import(new \DateTimeImmutable('2026-10-06'), true, 0);

        self::assertSame(['created' => 3, 'updated' => 0, 'kept' => 0, 'failed' => []], $report);
        $session = $container->get(NetworkSessionRepository::class)->findOneBy(['resultsFile' => 'DSRace-971793592-on-2026-10-08']);
        self::assertInstanceOf(NetworkSession::class, $session);
        self::assertSame(971793592, $session->getRaceId());
        self::assertCount(11, $session->getResults());
        self::assertNull($session->getDedicatedTo());
        self::assertSame('Epitech', $container->get(NetworkSessionRepository::class)->findOneBy(['resultsFile' => 'DSRace-1505796450-on-2026-10-08'])?->getDedicatedTo());
        $plan = $entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => 'C3-c046a']);
        self::assertSame('CdF2025V2-J1R1-StAuban-Challes', $plan?->getTitle());
        self::assertSame('https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=C3-c046a', $plan->getSheetUrl());
        self::assertSame(1, $entityManager->getRepository(FlightPlan::class)->count(['code' => 'C3-c046a']), 'One flight plan for its two sessions');

        $calls = \count($requested);
        $report = $importer->import(null, true, 0);
        self::assertSame(['created' => 0, 'updated' => 0, 'kept' => 3, 'failed' => []], $report);
        self::assertSame($calls + 7, \count($requested), 'The 7 lists again, no results page');
    }
}
