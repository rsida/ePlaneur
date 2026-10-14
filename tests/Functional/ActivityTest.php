<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\FlightPlan;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\NetworkSession;
use App\Entity\StepProgress;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Track;
use App\Tests\Functional\Admin\AdminUsers;
use App\Tests\Unit\Flight\FlightSamples;
use App\Training\ProgressStatus;
use App\Training\TrainingStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Activity of the club: month by month for the committee, and its export.
 */
final class ActivityTest extends WebTestCase
{
    use AdminUsers;
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        static::mockTime('2026-10-14 12:00');
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOnlyTheCommitteeSeesTheActivity(): void
    {
        $this->client->request('GET', '/activite');
        self::assertResponseRedirects('/connexion');
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', '/activite');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $this->client->request('GET', '/activite');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin');
        self::assertSelectorExists('a[href="/activite"]');
    }

    public function testTheActivityIsCountedMonthByMonth(): void
    {
        $committee = $this->userIn($this->entityManager, 'comite@example.org', 'committee');
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $max = $this->userIn($this->entityManager, 'max@example.org');
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("UPDATE app_user SET created_at = '2025-01-01 10:00:00'");
        $connection->executeStatement("UPDATE app_user SET created_at = '2026-09-03 10:00:00' WHERE id IN (?, ?)", [$lea->getId(), $max->getId()]);

        $plan = new FlightPlan('C2-d015');
        $session = new NetworkSession($plan, new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-01'));
        $this->entityManager->persist($plan);
        $this->entityManager->persist($session);
        $result = static fn (string $player): array => ['rank' => 1, 'status' => 'Finished', 'player' => $player, 'competitionId' => null, 'registration' => null, 'glider' => 'ASK21', 'distance' => 50000, 'time' => 3600, 'speed' => 50.0, 'penalty' => 0.0, 'score' => 1000.0];
        $this->entityManager->persist((new NetworkSession($plan, new \DateTimeImmutable('2026-10-07'), new \DateTimeImmutable('2026-10-01')))
            ->setResults('DSRace-1-on-2026-10-07', [$result('Léa'), $result('Max'), $result('Zoé')]));
        $first = $this->flight($lea, '2026-09-10')->setSession($session);
        $this->flight($lea, '2026-10-02');
        $this->flight($max, '2026-10-05');
        $this->entityManager->persist(new StepProgress($lea, TrainingStep::Discover, ProgressStatus::Validated, new \DateTimeImmutable('2026-10-01')));
        $this->entityManager->persist(new FlightComment($first, $committee, new \DateTimeImmutable('2026-10-03')));
        $season = new MembershipSeason(2026);
        $membership = new Membership($lea, $season, new \DateTimeImmutable('2026-09-12'));
        $membership->validate($committee, new \DateTimeImmutable('2026-09-15'));
        $this->entityManager->persist($season);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->client->loginUser($committee);

        $crawler = $this->client->request('GET', '/activite');
        $row = static fn (string $month): array => $crawler->filter('tr')->reduce(static fn ($tr): bool => $month === $tr->filter('th')->text())->filter('td')->each(static fn ($td): string => $td->text());
        self::assertCount(12, $crawler->filter('tbody tr'), 'The last twelve months');
        // Comptes, adhésions, vols, heures, pilotes, sessions, vols en session, étapes, éléments, commentaires
        [$accounts, $memberships, $flights, , $pilots, $sessions, $participations, $steps, , $comments] = $row('sept. 2026');
        self::assertSame(['2', '1', '1', '1', '1', '1', '0', '0'], [$accounts, $memberships, $flights, $pilots, $sessions, $participations, $steps, $comments]);
        [, , $flights, , $pilots, $sessions, $participations, $steps, , $comments] = $row('oct. 2026');
        self::assertSame(['2', '2', '1', '0', '1', '1'], [$flights, $pilots, $sessions, $participations, $steps, $comments], 'The results of the server are not tracks');
        [, , $flights, , $pilots] = $row('Total');
        self::assertSame(['3', '2'], [$flights, $pilots], 'Léa flew in both months: counted once');
        self::assertSelectorExists('[data-controller="activity-chart"]');

        $this->client->request('GET', '/activite?annee=2025');
        self::assertSelectorCount(12, 'tbody tr');
        self::assertSelectorTextContains('tbody tr:first-child th', 'déc. 2025');

        $this->client->request('GET', '/activite.csv');
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('Mois;"Comptes créés";"Adhésions validées";"Vols déposés";"Heures de vol";"Pilotes actifs"', $csv);
        self::assertStringContainsString("\n09/2026;2;1;1;", $csv);
        self::assertMatchesRegularExpression('/\nTotal;2;1;3;\d+,\d;2;2;1;1;0;1\n/', $csv);
    }

    private function flight(User $owner, string $date): Flight
    {
        $track = new Track('igc', 'Condor 2', 'Pilote', null, 'ASK21', 'AA2', null, null, [], FlightSamples::glideThermalGlide(), true, new \DateTimeImmutable($date));
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable($date));
        $this->entityManager->persist($flight);

        return $flight;
    }
}
