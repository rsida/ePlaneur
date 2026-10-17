<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Airfield;
use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\FlightPlan;
use App\Entity\Group;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Security\Permission;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Flight viewer: upload of a Condor track or an IGC file, flight page and its analysis, rights.
 */
final class FlightTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVisitorsAreAskedToLogIn(): void
    {
        $this->client->request('GET', '/vols');

        self::assertResponseRedirects('/connexion');
    }

    public function testACondorTrackIsUploadedAndAnalysed(): void
    {
        $pilot = $this->user('pilote@example.org');
        $this->client->loginUser($pilot);

        $crawler = $this->client->request('GET', '/vols');
        self::assertSelectorTextContains('h1', 'Analyse de vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'C3-c060-1.ftr')]);

        $flight = $this->entityManager->getRepository(Flight::class)->findOneBy([]);
        self::assertNotNull($flight);
        self::assertResponseRedirects('/vols/'.$flight->getId());
        self::assertSame('Romain SIDA', $flight->getPilot());
        self::assertSame(1930306707, $flight->getRaceId());
        self::assertSame($pilot->getId(), $flight->getOwner()?->getId());

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Romain SIDA');
        self::assertSelectorTextContains('.p-flight__meta', 'Diana2 · AA3 · C3-c060_Laragne-Ventoux-Fayence');
        self::assertSelectorTextContains('.p-flight__head .c-tag', 'Départ en vol');
        self::assertSelectorTextNotContains('body', 'sont estimées', 'Times decoded from the records');
        self::assertSelectorTextContains('.p-flight__legs', 'Laragne Chp → Ventoux');
        // The detail of the analysis in tabs, the track managed from one menu
        self::assertSelectorTextContains('#tab-synthese[aria-selected="true"]', 'Synthèse');
        self::assertSelectorExists('#panel-phases .p-flight__phase-row');
        self::assertSelectorTextContains('.p-flight__menu', 'Export IGC');
        // The key explains what the map draws from above too: the task and the ring of the glide range
        self::assertSelectorTextContains('.p-flight__key', 'Circuit');
        self::assertSelectorTextContains('.p-flight__key', 'rayon de plané');
        self::assertSelectorTextContains('.p-flight__key', 'Instruments');
        // The guided debrief, from the start of the flight to the review
        self::assertSelectorExists('.p-flight__guide-toggle');
        $chapters = $this->client->getCrawler()->filter('.p-flight__chapter-title')->each(static fn ($title): string => $title->text());
        self::assertSame('Le lancement', $chapters[0], 'the sample never crosses the start line');
        self::assertStringContainsString('bilan', (string) end($chapters));

        // An airfield of the landscape near the track, for the glide cone
        $this->entityManager->persist(new Airfield('AA3', 'Laragne', 44.3157, 5.8125, 655.0, 800));
        $this->entityManager->persist(new Airfield('AA3', 'Far away', 47.0, 11.0, 500.0, 800));
        $this->entityManager->flush();

        $this->client->request('GET', '/vols/'.$flight->getId().'/analyse.json');
        self::assertResponseIsSuccessful();
        /** @var array{series: array{time: list<int>}, exactTimes: bool, reach: array{ratio: float, margin: float, airfields: list<array{string, float, float, float}>}} $analysis */
        $analysis = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(306, $analysis['series']['time'], '3,000 records over 305 s, one point per second');
        self::assertTrue($analysis['exactTimes']);
        self::assertSame(['Laragne'], array_column($analysis['reach']['airfields'], 0), 'Only the airfields around the track');
        self::assertSame(200.0, (float) $analysis['reach']['margin']);
    }

    public function testTheLiftOfTheClubIsMappedByLandscape(): void
    {
        $this->client->request('GET', '/vols/ascendances');
        self::assertResponseRedirects('/connexion');

        $pilot = $this->user('pilote@example.org');
        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'C3-c060-1.ftr')]);

        $this->client->request('GET', '/vols/ascendances');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#landscape', 'AA3 · 1 vol');
        self::assertSelectorExists('[data-controller="lift-map"][data-lift-map-url-value="/vols/ascendances/AA3.json"]');
        $this->client->request('GET', '/vols/ascendances?paysage=Inconnu');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/vols/ascendances/AA3.json');
        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $lift */
        $lift = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(1, $lift['flights']);
        self::assertSame(['thermals', 'slopes', 'flights'], array_keys($lift));
    }

    public function testAnIgcFileIsUploaded(): void
    {
        $this->client->loginUser($this->user('pilote@example.org'));
        $path = sys_get_temp_dir().'/'.uniqid('flight', true).'.igc';
        file_put_contents($path, FlightSamples::igc(FlightSamples::glideThermalGlide()));

        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($path, 'vol.igc')]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'Jeanne Pilote');
        self::assertSelectorTextNotContains('body', 'sont estimées');
        self::assertSelectorTextContains('.p-flight__stats', 'Durée 7 min'); // 419 s
    }

    public function testOtherFilesAreRefusedWithAMessage(): void
    {
        $this->client->loginUser($this->user('pilote@example.org'));
        $path = sys_get_temp_dir().'/'.uniqid('flight', true).'.igc';
        file_put_contents($path, 'not a flight');

        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($path, 'faux.igc')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.c-alert--error', 'Format non reconnu');
        self::assertSame(0, $this->entityManager->getRepository(Flight::class)->count([]));
    }

    public function testOnlyThePilotOrAnAdministratorDeletesAFlight(): void
    {
        $pilot = $this->user('pilote@example.org');
        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'vol.ftr')]);
        $id = $this->entityManager->getRepository(Flight::class)->findOneBy([])?->getId();

        $this->client->loginUser($this->user('autre@example.org'));
        $crawler = $this->client->request('GET', '/vols/'.$id);
        self::assertResponseIsSuccessful('Other members see the flight');
        self::assertSelectorNotExists('.p-flight__delete');
        $this->client->request('POST', '/vols/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols/'.$id);
        $this->client->submit($crawler->filter('.p-flight__delete')->form());
        self::assertResponseRedirects('/vols');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Flight::class)->find($id));
    }

    public function testFlightsOfTheSameFlightPlanAreGathered(): void
    {
        foreach (['pilote@example.org', 'autre@example.org'] as $email) {
            $this->client->loginUser($this->user($email));
            $crawler = $this->client->request('GET', '/vols');
            $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'C3-c060-1.ftr')]);
        }
        $id = $this->entityManager->getRepository(Flight::class)->findOneBy([])?->getId();

        $this->client->request('GET', '/vols/'.$id);
        self::assertSelectorTextContains('.p-flight__links', '2 traces sur ce plan de vol');

        // The flight plan of the library got its page (no session of this race was recorded)
        $this->client->request('GET', '/vols/'.$id.'/session');
        self::assertResponseRedirects('/plans-de-vol/C3-c060');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'C3-c060 · Laragne-Ventoux-Fayence');
        self::assertCount(2, $this->client->getCrawler()->filter('.p-session__ranking tbody tr'));

        $this->client->request('GET', '/plans-de-vol/C3-c060/traces.json');
        /** @var array{task: array<string, mixed>|null, flights: list<array{start: int, latitude: list<float>, altitude: list<float>, speed: list<float>}>} $session */
        $session = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(2, $session['flights']);
        self::assertNotNull($session['task']);
        self::assertCount(306, $session['flights'][0]['altitude']);
        self::assertCount(306, $session['flights'][0]['speed'], 'For the bank of the gliders');
        self::assertSame(0, $session['flights'][0]['start']);
    }

    public function testTwoFlightsOfASessionAreCompared(): void
    {
        // The sample flight on a task from x = 0 (start) to x = 7,000 m, uploaded by two pilots
        $file = sys_get_temp_dir().'/task.igc';
        file_put_contents($file, FlightSamples::igc(FlightSamples::glideThermalGlide(), extraHeaders: FlightSamples::igcTask('Tâche test', [0, 0, 7000])));
        foreach (['pilote@example.org', 'autre@example.org'] as $email) {
            $this->client->loginUser($this->user($email));
            $crawler = $this->client->request('GET', '/vols');
            $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($file, 'task.igc')]);
        }
        [$first, $second] = $this->entityManager->getRepository(Flight::class)->findBy([], ['id' => 'ASC']);
        self::assertSame('Tâche test', $first->getFlightPlan());

        $this->client->request('GET', '/vols/'.$second->getId());
        $this->client->clickLink('Comparer avec un autre pilote');
        self::assertResponseRedirects('/vols/'.$second->getId().'/comparer/'.$first->getId());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jeanne Pilote face à Jeanne Pilote');
        self::assertSelectorTextContains('.p-compare__verdict', 'les deux pilotes sont à égalité');
        self::assertSelectorTextContains('.p-compare__summary', 'Vario moyen en thermique');

        $this->client->request('GET', '/vols/'.$second->getId().'/comparer/'.$first->getId().'.json');
        self::assertResponseIsSuccessful();
        /** @var array{step: int, flights: list<array{times: list<int>, altitude: list<float>, speed: list<float>}>} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(250, $data['step']);
        self::assertCount(\count($data['flights'][0]['altitude']), $data['flights'][0]['speed'], 'For the bank of the gliders');
        self::assertCount(2, $data['flights']);
        self::assertSame($data['flights'][0]['times'], $data['flights'][1]['times'], 'The same track');
        self::assertGreaterThan(20, \count($data['flights'][0]['times']), 'From the start to the finish, 250 m apart');
    }

    public function testThePilotComparesTheirFlightFromTheResultsOfTheSession(): void
    {
        // Two pilots of the same race, on a task from x = 0 (start) to x = 7,000 m
        $igc = FlightSamples::igc(FlightSamples::glideThermalGlide(), extraHeaders: FlightSamples::igcTask('Tâche test', [0, 0, 7000]));
        foreach (['JP1' => 'pilote@example.org', 'JP2' => 'autre@example.org'] as $cn => $email) {
            $file = sys_get_temp_dir().'/task-'.$cn.'.igc';
            file_put_contents($file, str_replace('HFCIDCOMPETITIONID:JP1', 'HFCIDCOMPETITIONID:'.$cn, $igc));
            $this->client->loginUser($this->user($email));
            $crawler = $this->client->request('GET', '/vols');
            $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($file, 'task.igc')]);
        }
        $flights = $this->entityManager->getRepository(Flight::class)->findBy([], ['id' => 'ASC']);
        $plan = new FlightPlan('C3-c999');
        $session = (new NetworkSession($plan, new \DateTimeImmutable('2026-02-19'), new \DateTimeImmutable()))
            ->setResults('resultats.csv', array_map(static fn (int $rank, string $cn): array => ['rank' => $rank, 'status' => 'Finished', 'player' => 'Pilote '.$cn, 'competitionId' => $cn, 'registration' => null, 'glider' => 'Duo Discus', 'distance' => 7000, 'time' => 600, 'speed' => 42.0, 'penalty' => 0.0, 'score' => 1000.0], [1, 2], ['JP1', 'JP2']));
        $this->entityManager->persist($plan);
        $this->entityManager->persist($session);
        foreach ($flights as $flight) {
            $flight->setSession($session);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Logged in as the second pilot: a link to compare with the first, none to compare with oneself
        $this->client->request('GET', '/sessions/'.$session->getId());
        self::assertSelectorExists('.p-session__results a[href="/vols/'.$flights[1]->getId().'/comparer/'.$flights[0]->getId().'"]');
        self::assertSelectorNotExists('.p-session__results a[href="/vols/'.$flights[1]->getId().'/comparer/'.$flights[1]->getId().'"]');
        self::assertSelectorNotExists('.p-session__results .c-callout', 'The track is dropped');
    }

    public function testAFlightAloneHasNothingToCompare(): void
    {
        $this->client->loginUser($this->user('pilote@example.org'));
        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'C3-c060-1.ftr')]);
        $id = $this->entityManager->getRepository(Flight::class)->findOneBy([])?->getId();

        $this->client->request('GET', '/vols/'.$id.'/comparer');
        self::assertResponseRedirects('/vols/'.$id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert', 'rien à comparer');
    }

    public function testThePilotAndTrainersCommentAMomentOfTheFlight(): void
    {
        $pilot = $this->user('pilote@example.org');
        $id = $this->uploadSample($pilot);

        $crawler = $this->client->request('GET', '/vols/'.$id);
        self::assertSelectorExists('.p-flight__advice', 'Advice on the flight');
        $this->client->submit($crawler->selectButton('Commenter')->form([
            'flight_comment[body]' => 'Spirale décentrée ici.',
            'flight_comment[step]' => '5',
            'flight_comment[at]' => '120',
        ]));
        self::assertResponseRedirects('/vols/'.$id.'#commentaires');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.p-flight__comment', 'Spirale décentrée ici.');
        self::assertSelectorTextContains('.p-flight__comment .p-flight__comment-at', '2:00');
        self::assertSelectorTextContains('.p-flight__comment', 'Étape 5 · Voler à la vitesse MacCready');

        // Another member sees the comment but may not comment
        $this->client->loginUser($this->user('autre@example.org'));
        $crawler = $this->client->request('GET', '/vols/'.$id);
        self::assertSelectorTextContains('.p-flight__comment', 'Spirale décentrée ici.');
        self::assertSelectorNotExists('.p-flight__comment-form');
        self::assertSelectorNotExists('.p-flight__comment form');
        $this->client->request('POST', '/vols/'.$id.'/commentaires', ['flight_comment' => ['body' => 'Non']]);
        self::assertResponseStatusCodeSame(403);

        // A trainer comments the whole flight: the moment is left out
        $trainer = $this->user('formateur@example.org');
        $trainer->addGroup($this->trainers());
        $this->entityManager->flush();
        $this->client->loginUser($trainer);
        $crawler = $this->client->request('GET', '/vols/'.$id);
        $form = $crawler->selectButton('Commenter')->form(['flight_comment[body]' => 'Bon vol dans l’ensemble.', 'flight_comment[at]' => '60']);
        $atMoment = $form['flight_comment[atMoment]'];
        \assert($atMoment instanceof ChoiceFormField);
        $atMoment->untick();
        $this->client->submit($form);
        $this->entityManager->clear();
        $comments = $this->entityManager->getRepository(FlightComment::class)->findBy([], ['id' => 'ASC']);
        self::assertSame([120, null], array_map(static fn (FlightComment $comment): ?int => $comment->getAt(), $comments));

        // Only its author deletes a comment
        $crawler = $this->client->request('GET', '/vols/'.$id);
        self::assertCount(1, $crawler->filter('.p-flight__comment-delete'));
        $this->client->submit($crawler->filter('.p-flight__comment-delete')->form());
        $this->entityManager->clear();
        self::assertCount(1, $this->entityManager->getRepository(FlightComment::class)->findAll());
    }

    public function testAnEmptyCommentIsRefused(): void
    {
        $id = $this->uploadSample($this->user('pilote@example.org'));
        $crawler = $this->client->request('GET', '/vols/'.$id);
        $this->client->submit($crawler->selectButton('Commenter')->form(['flight_comment[body]' => '  ']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.c-field__errors', 'Écrivez votre commentaire.');
        self::assertSelectorExists('#tab-conseils[aria-selected="true"]', 'The form and its error are in view');
    }

    public function testTheLogbookIsSeenByThePilotAndTrainers(): void
    {
        $pilot = $this->user('pilote@example.org');
        $this->uploadSample($pilot);

        $this->client->request('GET', '/vols/carnet');
        self::assertResponseRedirects('/mon-compte/carnet');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon carnet de vol');
        self::assertSelectorTextContains('.p-flight__stats', 'Vols 1');
        self::assertSelectorExists('[data-controller="logbook-chart"]');
        // One flight: the skills it measures, no trend yet
        self::assertSelectorTextContains('.p-flight-logbook__skills', 'Montée en thermique');
        self::assertSelectorTextContains('.p-flight-logbook__skills', 'pas encore assez de vols');

        $this->client->loginUser($this->user('autre@example.org'));
        $this->client->request('GET', '/vols/carnet/'.$pilot->getId());
        self::assertResponseStatusCodeSame(403);

        $trainer = $this->user('formateur@example.org');
        $trainer->addGroup($this->trainers());
        $this->entityManager->flush();
        $this->client->loginUser($trainer);
        $this->client->request('GET', '/vols/carnet/'.$pilot->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'pilote@example.org');
    }

    public function testThePilotMakesAFlightPublicForVisitors(): void
    {
        $pilot = $this->user('pilote@example.org');
        $id = $this->uploadSample($pilot);
        $crawler = $this->client->request('GET', '/vols/'.$id);
        $this->client->submit($crawler->selectButton('Commenter')->form(['flight_comment[body]' => 'Note du pilote.']));

        // Only its pilot makes it public
        $this->client->loginUser($this->user('autre@example.org'));
        $crawler = $this->client->request('GET', '/vols/'.$id);
        self::assertSelectorNotExists('form[action$="/public"]');
        $this->client->request('POST', '/vols/'.$id.'/public', ['public' => '1']);
        self::assertResponseStatusCodeSame(403);

        // Private: visitors are asked to log in
        $this->client->restart();
        $this->client->request('GET', '/vols/'.$id);
        self::assertResponseRedirects('/connexion');
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.p-home-flights');
        self::assertSelectorNotExists('[data-controller="home-replay"]');
        $this->client->request('GET', '/vols/'.$id.'/apercu.json');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols/'.$id);
        $this->client->submit($crawler->selectButton('Rendre public')->form());
        self::assertResponseRedirects('/vols/'.$id);

        // Public: on the home page, and seen by visitors without the comments
        $this->client->restart();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.p-home-flights', 'Jeanne Pilote');
        self::assertSelectorExists('[data-controller="home-replay"][data-home-replay-url-value="/vols/'.$id.'/apercu.json"]', 'Replayed on the home page');
        self::assertSelectorExists('.p-logbook__panel a[href="/vols/'.$id.'/comparer"]', 'The logbook card shows this flight');
        self::assertSelectorNotExists('.p-logbook__panel form', 'Visitors are asked to create an account to upload');
        self::assertSelectorExists('.p-logbook__panel a[href="/inscription"]');
        $this->client->request('GET', '/vols/'.$id.'/apercu.json');
        self::assertResponseIsSuccessful();
        $replay = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(2, $replay['step']);
        self::assertNotEmpty($replay['track']['latitude']);
        self::assertSame(array_keys($replay['track']), ['latitude', 'longitude', 'altitude', 'speed'], 'Only what the replay draws');
        self::assertCount(\count($replay['track']['latitude']), $replay['track']['altitude']);
        $this->client->request('GET', '/vols/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-flight__links', 'Public');
        self::assertSelectorNotExists('#commentaires');
        self::assertSelectorTextNotContains('body', 'Note du pilote.');
        $this->client->request('GET', '/vols/'.$id.'/analyse.json');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/vols/'.$id.'/session');
        self::assertResponseRedirects('/connexion', null, 'The rest of the viewer needs an account');
    }

    public function testRecordsSeasonRankingAndBadges(): void
    {
        $file = sys_get_temp_dir().'/task.igc';
        file_put_contents($file, FlightSamples::igc(FlightSamples::glideThermalGlide(), extraHeaders: FlightSamples::igcTask('Tâche test', [0, 0, 7000])));
        $pilot = $this->user('pilote@example.org');
        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($file, 'task.igc')]);

        $this->client->request('GET', '/vols/records?saison=2026');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Records et classement');
        self::assertSelectorTextContains('[aria-labelledby="season-title"] tbody', 'pilote@example.org');
        self::assertSelectorTextContains('[aria-labelledby="tasks-title"] tbody', 'Tâche test');

        $this->client->request('GET', '/mon-compte/carnet');
        self::assertSelectorTextContains('.p-flight-logbook__badge.is-earned', 'Premier circuit terminé');
    }

    /** The sample IGC flight uploaded by a pilot, who stays logged in. */
    private function uploadSample(User $pilot): int
    {
        $file = sys_get_temp_dir().'/sample.igc';
        file_put_contents($file, FlightSamples::igc(FlightSamples::glideThermalGlide()));
        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols');
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload($file, 'sample.igc')]);

        return (int) $this->entityManager->getRepository(Flight::class)->findOneBy([], ['id' => 'DESC'])?->getId();
    }

    private function trainers(): Group
    {
        $group = (new Group('trainer', 'Formateur'))->grant(Permission::FlightReview);
        $this->entityManager->persist($group);

        return $group;
    }

    private function user(string $email, string ...$groups): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($email)->setVerified(true)->setPassword('x');
        foreach ($groups as $code) {
            $user->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode($code) ?? throw new \LogicException($code));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function upload(string $source, string $name): UploadedFile
    {
        $copy = sys_get_temp_dir().'/'.uniqid('upload', true);
        copy($source, $copy);

        return new UploadedFile($copy, $name, 'application/octet-stream', null, true);
    }
}
