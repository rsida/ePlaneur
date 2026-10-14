<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Track;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * "Mon premier vol": the steps of a newcomer, seen by the site or said done by the pilot, and the
 * next initiation flight.
 */
final class FirstFlightTest extends WebTestCase
{
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

    public function testVisitorsSeeTheWayToTheirFirstFlight(): void
    {
        $this->session('C2-s010', '2026-10-15');
        $initiation = $this->session('C2-d015', '2026-10-21');

        $this->client->request('GET', '/');
        self::assertSelectorExists('#formation a[href="/premier-vol"]');

        $this->client->request('GET', '/premier-vol');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon premier vol');
        self::assertSelectorCount(6, '.p-first-flight > li');
        self::assertSelectorNotExists('.p-path-item--done');
        self::assertSelectorExists('#account a[href="/inscription"]');
        self::assertSelectorNotExists('#discord form', 'Visitors read, pilots tick');
        self::assertSelectorExists('#network_flight a[href="/sessions/'.$initiation->getId().'"]', 'The next initiation flight, not the next session');
        self::assertSelectorTextContains('#network_flight', 'mercredi 21 octobre 2026');
        self::assertSelectorNotExists('meta[name="robots"][content*="noindex"]', 'A page for search engines');

        $this->client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('/premier-vol</loc>', (string) $this->client->getResponse()->getContent());
    }

    public function testTheStepsAreTickedFromWhatThePilotDid(): void
    {
        $pilot = (new User())->setEmail('newcomer@example.org')->setDisplayName('Léa')->setVerified(true)->setPassword('x');
        $this->entityManager->persist($pilot);
        $this->entityManager->flush();
        $this->client->loginUser($pilot);

        $this->client->request('GET', '/premier-vol');
        self::assertSelectorExists('#account.p-path-item--done');
        self::assertSelectorExists('#condor.p-first-flight__step--next');
        self::assertSelectorTextContains('.c-meter', '1 étape faite sur 6');
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('section[aria-labelledby="first-flight-title"]', '1 étape faite sur 6');

        $this->client->request('GET', '/premier-vol');
        $this->client->submitForm('C’est fait');
        self::assertResponseRedirects('/premier-vol#discord', 303);
        $this->client->followRedirect();
        self::assertSelectorExists('#discord.p-path-item--done');
        $this->client->submitForm('Je ne l’ai pas encore fait');
        $this->client->followRedirect();
        self::assertSelectorNotExists('#discord.p-path-item--done');
        $this->client->submitForm('C’est fait');

        $owner = $this->entityManager->getReference(User::class, $pilot->getId());
        $owner->setCondorVersions([2]);
        $this->entityManager->persist($this->flight($owner, $this->session('C2-d015', '2026-10-07')));
        $this->entityManager->flush();
        $this->client->request('GET', '/premier-vol');
        self::assertSelectorCount(5, '.p-path-item--done', 'Condor, a flight, a network flight; step 1 of the path is still to do');
        self::assertSelectorExists('#first_step.p-first-flight__step--next');
    }

    public function testOnlyTheStepsTheSiteCannotSeeAreSaidDone(): void
    {
        $pilot = (new User())->setEmail('newcomer@example.org')->setDisplayName('Léa')->setVerified(true)->setPassword('x');
        $this->entityManager->persist($pilot);
        $this->entityManager->flush();
        $this->client->loginUser($pilot);

        $this->client->request('POST', '/premier-vol/solo_flight', ['done' => '1']);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/premier-vol/discord', ['done' => '1', '_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
    }

    private function session(string $code, string $date): NetworkSession
    {
        $plan = $this->entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => $code]) ?? new FlightPlan($code);
        $this->entityManager->persist($plan);
        $session = (new NetworkSession($plan, new \DateTimeImmutable($date), new \DateTimeImmutable()))->setStartTime(new \DateTimeImmutable('16:30'));
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    private function flight(User $owner, NetworkSession $session): Flight
    {
        $track = new Track('igc', 'Condor 2', 'Léa', null, 'ASK21', 'AA2', null, null, [], FlightSamples::glideThermalGlide(), true, new \DateTimeImmutable('2026-10-07'));

        return (new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable('2026-10-07')))->setSession($session);
    }
}
