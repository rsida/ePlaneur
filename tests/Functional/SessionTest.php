<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Flight\FlightImporter;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * Network sessions: the list, proposing one with its flight plan, its results from the server and the
 * tracks of its race, the flight plan page, and who may do what.
 */
final class SessionTest extends WebTestCase
{
    private const string TRACK = __DIR__.'/../fixtures/flights/aa3-laragne-start.ftr';
    private const int RACE_ID = 1930306707;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVisitorsSeeTheSessionsButNotTheResults(): void
    {
        $past = $this->session('C3-c060', '2026-02-19', 'DSRace-'.self::RACE_ID.'-on-2026-02-19');
        $this->session('C2-d009', '+3 days')->setDedicatedTo('Epitech');
        $this->entityManager->flush();

        $this->client->request('GET', '/sessions');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-sessions__upcoming', 'C2-d009');
        self::assertSelectorTextContains('.p-sessions__upcoming', 'Session dédiée · Epitech');
        self::assertSelectorTextContains('table', 'C3-c060');
        self::assertSelectorNotExists('a[href="/sessions/nouvelle"]');

        $this->client->request('GET', '/sessions?condor=2');
        self::assertSelectorNotExists('table');

        $this->client->request('GET', '/sessions/'.$past->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'C3-c060');
        self::assertSelectorExists('a[href="https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=C3-c060"]');
        self::assertSelectorTextContains('.p-session__results', 'Connectez-vous');
        self::assertSelectorTextNotContains('.p-session__results', 'Alice Martin');

        $this->client->request('GET', '/plans-de-vol/C3-c060');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'C3-c060');
        $this->client->request('GET', '/plans-de-vol/C3-c060/traces.json');
        self::assertResponseRedirects();
    }

    public function testTheHomePageShowsTheComingSessions(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#vols', 'Les sessions sont annoncées dans le groupe WhatsApp');
        self::assertSelectorNotExists('.p-next-flight');

        $this->session('C3-c046a', '2026-02-19');
        $next = $this->session('C3-s118', '+2 days')->setStartTime(new \DateTimeImmutable('21:00'));
        $this->entityManager->flush();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#vols', 'Les prochains vols');
        self::assertSelectorTextContains('#vols', 'C3-s118');
        self::assertSelectorTextNotContains('#vols', 'C3-c046a');
        self::assertSelectorTextContains('.p-next-flight', '21h00');
        self::assertSelectorExists('.p-next-flight a[href="/sessions/'.$next->getId().'"]');
    }

    public function testAMemberProposesASessionOnANewFlightPlan(): void
    {
        $this->client->loginUser($this->user('max@example.org', 'member'));
        $this->entityManager->persist((new FlightPlan('C3-c046a'))->setTitle('CdF2025V2-J1R1-StAuban-Challes'));
        $this->entityManager->flush();

        $this->client->request('GET', '/plans-de-vol/recherche?q=StAuban');
        self::assertResponseIsSuccessful();
        self::assertSame([['code' => 'C3-c046a', 'label' => 'C3-c046a · CdF2025V2-J1R1-StAuban-Challes', 'url' => null]], json_decode((string) $this->client->getResponse()->getContent(), true));

        $crawler = $this->client->request('GET', '/sessions/nouvelle');
        $this->client->submit($crawler->selectButton('Proposer la session')->form([
            'session[date]' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d'),
            'session[startTime]' => '21:00',
            'session[flightPlan]' => 'Vol libre',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'code du plan de vol');

        $this->client->submit($crawler->selectButton('Proposer la session')->form([
            'session[date]' => (new \DateTimeImmutable('+7 days'))->format('Y-m-d'),
            'session[startTime]' => '21:00',
            'session[flightPlan]' => 'C3-s118 · StEynard-GdeSure-Colombier-Versoud',
            'session[sheetUrl]' => '',
            'session[condorClubUrl]' => 'https://www.condor.club/showcircuit/0/12345/',
            'session[joinTime]' => '10',
            'session[notes]' => 'Serveur AuvRAlpes, Stop Join 10 min.',
        ]));
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'C3-s118 · StEynard-GdeSure-Colombier-Versoud');
        self::assertSelectorTextContains('.p-flight__meta', 'briefing à 21h00');
        self::assertSelectorTextContains('.p-flight__meta', 'organisée par Test max@example.org');
        self::assertSelectorTextContains('.p-session__notes', 'Stop Join 10 min');

        $plan = $this->entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => 'C3-s118']);
        self::assertSame('https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=C3-s118', $plan?->getSheetUrl(), 'Recorded for the next sessions');
        self::assertSame('StEynard-GdeSure-Colombier-Versoud', $plan->getTitle());
        self::assertSame(10, $this->entityManager->getRepository(NetworkSession::class)->findOneBy(['flightPlan' => $plan])?->getJoinTime(), 'the stop join of the session');

        $this->client->request('GET', '/');
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('#sessions-title', 'On se retrouve en vol');
    }

    public function testRegisteredUsersCannotProposeSessions(): void
    {
        $this->client->loginUser($this->user('ines@example.org'));
        $this->client->request('GET', '/sessions/nouvelle');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/plans-de-vol/recherche?q=C3');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheResultsOfTheServerGatherTheTracksOfTheRace(): void
    {
        $organiser = $this->user('max@example.org', 'member');
        $pilot = $this->user('jeanne@example.org');
        $flight = $this->upload($pilot);
        self::assertNull($flight->getSession(), 'No session yet');

        $session = $this->session('C3-c060', '2026-02-19')->setOrganiser($organiser);
        $this->entityManager->flush();
        $this->client->loginUser($organiser);
        $crawler = $this->client->request('GET', '/sessions/'.$session->getId().'/modifier');
        $form = $crawler->selectButton('Enregistrer')->form();

        $this->uploadIn($form, $this->results('resultats.csv'));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'tel que le serveur l’a nommé');

        $form = $this->client->getCrawler()->selectButton('Enregistrer')->form();
        $this->uploadIn($form, $this->results('DSRace-'.self::RACE_ID.'-on-2026-02-19.csv'));
        $this->client->submit($form);
        self::assertResponseRedirects('/sessions/'.$session->getId());

        // The flight plan of the race gives the settings of the task
        $crawler = $this->client->request('GET', '/sessions/'.$session->getId().'/modifier');
        $form = $crawler->selectButton('Enregistrer')->form();
        $field = $form['session[flightPlanFile]'];
        \assert($field instanceof FileFormField);
        $field->upload(\dirname(__DIR__).'/fixtures/condor/Race 560447479 on 2026.03.05.fpl');
        $this->client->submit($form);
        self::assertResponseRedirects('/sessions/'.$session->getId());

        $this->entityManager->clear();
        $flight = $this->entityManager->find(Flight::class, $flight->getId());
        self::assertSame($session->getId(), $flight?->getSession()?->getId(), 'Same race id');
        self::assertSame('2026-02-19', $flight->getDate()->format('Y-m-d'), 'A .ftr file takes the day of its session');

        $this->client->loginUser($pilot);
        $this->client->request('GET', '/sessions/'.$session->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-session__results', 'Alice Martin');
        self::assertSelectorTextContains('.p-session__results', 'Arrivé');
        self::assertSelectorTextContains('.p-session-settings', 'Remorquage');
        self::assertSelectorTextContains('.p-session-settings', '1 982 m sol');
        self::assertSelectorExists('[data-controller="session-viewer"][data-session-viewer-url-value="/sessions/'.$session->getId().'/traces.json"]');
        // What the replay map draws, each on or off
        self::assertSelectorExists('[data-controller="session-viewer"] .p-flight__layer-input[value="followed"][data-action="session-viewer#toggleLayer"]');
        self::assertSelectorTextContains('[data-controller="session-viewer"] .p-flight__layers-panel', 'Thermiques de tous les pilotes');
        // The whole replay full screen
        self::assertSelectorExists('[data-controller="session-viewer"] [data-session-viewer-target="viewer"] [data-session-viewer-target="fullscreen"]');
        $this->client->request('GET', '/sessions/'.$session->getId().'/traces.json');
        $replay = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $replay['flights']);
        self::assertArrayHasKey('clouds', $replay['flights'][0], 'the cumulus over the thermals of each pilot');

        $this->client->request('GET', '/vols/'.$flight->getId());
        self::assertSelectorExists('a[href="/sessions/'.$session->getId().'"]');
        self::assertSelectorTextContains('.p-flight__official', 'Résultat officiel');
        self::assertSelectorTextContains('.p-session-settings', 'Fenêtre de 15 min');
        self::assertSelectorExists('[data-flight-viewer-target="viewer"] [data-flight-viewer-target="fullscreen"]');
        self::assertSelectorExists('[data-flight-viewer-target="clockToggle"][data-action="flight-viewer#toggleClock"]');

        // Downloads: the original file and an IGC export; the thermals of the race
        $this->client->request('GET', '/vols/'.$flight->getId().'/fichier');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('laragne.ftr', (string) $this->client->getResponse()->headers->get('content-disposition'));
        $this->client->request('GET', '/vols/'.$flight->getId().'/igc');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('AXEPePlaneur', (string) $this->client->getResponse()->getContent());
        // One track only: nothing to compare yet
        $this->client->request('GET', '/sessions/'.$session->getId().'/comparaison');
        self::assertResponseRedirects('/sessions/'.$session->getId());
        $this->client->request('GET', '/sessions/'.$session->getId().'/thermiques.json');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('thermals', json_decode((string) $this->client->getResponse()->getContent(), true));
        $this->client->request('GET', '/vols/'.$flight->getId().'/session');
        self::assertResponseRedirects('/sessions/'.$session->getId());
        self::assertSelectorNotExists('a[href="/sessions/'.$session->getId().'/modifier"]');
        $this->client->request('GET', '/sessions/'.$session->getId().'/modifier');
        self::assertResponseStatusCodeSame(403, 'Only its organiser or the committee');
    }

    public function testAnUploadedTrackJoinsItsSession(): void
    {
        $session = $this->session('C3-c060', '2026-02-19', 'DSRace-'.self::RACE_ID.'-on-2026-02-19');
        $other = $this->session('C3-c060', '2026-02-26', 'DSRace-42-on-2026-02-26');
        $this->entityManager->flush();

        $flight = $this->upload($this->user('jeanne@example.org'));

        self::assertSame($session, $flight->getSession());
        self::assertNotSame($other, $flight->getSession());

        $this->client->loginUser($this->user('camille@example.org', 'committee'));
        $this->client->request('GET', '/plans-de-vol/C3-c060');
        self::assertSelectorTextContains('body', '2 sessions sur ce plan de vol');
        self::assertSelectorExists('[data-session-viewer-url-value="/plans-de-vol/C3-c060/traces.json"]');

        $crawler = $this->client->request('GET', '/sessions/'.$session->getId());
        $this->client->submit($crawler->selectButton('Supprimer la session')->form());
        self::assertResponseRedirects('/sessions');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Flight::class, $flight->getId())?->getSession(), 'The track stays');
    }

    private function session(string $code, string $date, ?string $resultsFile = null): NetworkSession
    {
        $plan = $this->entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => $code]) ?? (new FlightPlan($code))->setSheetUrl(\sprintf(FlightPlan::SHEET_URL, $code));
        $this->entityManager->persist($plan);
        $session = new NetworkSession($plan, new \DateTimeImmutable($date), new \DateTimeImmutable());
        if (null !== $resultsFile) {
            $session->setResults($resultsFile, [['rank' => 1, 'status' => 'Finished', 'player' => 'Alice Martin', 'competitionId' => 'P01', 'registration' => null, 'glider' => 'Diana2', 'distance' => 182800, 'time' => 4909, 'speed' => 134.1, 'penalty' => 0.0, 'score' => 1000.0]]);
        }
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    private function upload(User $pilot): Flight
    {
        $file = sys_get_temp_dir().'/session-track.ftr';
        copy(self::TRACK, $file);

        return static::getContainer()->get(FlightImporter::class)->import($file, 'laragne.ftr', $pilot);
    }

    private function uploadIn(Form $form, string $path): void
    {
        $field = $form['session[results]'];
        \assert($field instanceof FileFormField);
        $field->upload($path);
    }

    /** A results file of the server, named `$name`: its path. */
    private function results(string $name): string
    {
        $path = sys_get_temp_dir().'/'.$name;
        file_put_contents($path, "Rank,Status,Player,CN,RN,Plane,Dist,Time,Speed,Penalty,Score\n1,Finished,Alice.Martin,RS2,F-C001,Diana2,182.8 km,01:21:49,134.1 km/h,0.0 p,1000.0 p\n");

        return $path;
    }

    private function user(string $email, string ...$groups): User
    {
        $user = (new User())->setEmail($email)->setDisplayName('Test '.$email)->setVerified(true)->setPassword('x');
        foreach ($groups as $code) {
            $user->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode($code) ?? throw new \LogicException($code));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
