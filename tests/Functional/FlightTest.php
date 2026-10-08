<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        self::assertSelectorTextContains('body', 'les durées et les vitesses sont estimées');
        self::assertSelectorTextContains('.p-flight__legs', 'Laragne Chp → Ventoux');

        $this->client->request('GET', '/vols/'.$flight->getId().'/analyse.json');
        self::assertResponseIsSuccessful();
        /** @var array{series: array{time: list<int>}, exactTimes: bool} $analysis */
        $analysis = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(312, $analysis['series']['time'], '3,000 records of 0.104 s, one point per second');
        self::assertFalse($analysis['exactTimes']);
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

    public function testFlightsOfTheSameFlightPlanFormASession(): void
    {
        foreach (['pilote@example.org', 'autre@example.org'] as $email) {
            $this->client->loginUser($this->user($email));
            $crawler = $this->client->request('GET', '/vols');
            $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => $this->upload(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', 'C3-c060-1.ftr')]);
        }
        $id = $this->entityManager->getRepository(Flight::class)->findOneBy([])?->getId();

        $this->client->request('GET', '/vols/'.$id);
        self::assertSelectorTextContains('.p-flight__links', 'Session : 2 vols sur ce plan de vol');

        $this->client->request('GET', '/vols/'.$id.'/session');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'C3-c060_Laragne-Ventoux-Fayence');
        self::assertCount(2, $this->client->getCrawler()->filter('.p-session__ranking tbody tr'));

        $this->client->request('GET', '/vols/'.$id.'/session.json');
        /** @var array{task: array<string, mixed>|null, flights: list<array{start: int, latitude: list<float>, altitude: list<float>}>} $session */
        $session = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(2, $session['flights']);
        self::assertNotNull($session['task']);
        self::assertCount(312, $session['flights'][0]['altitude']);
        self::assertSame(0, $session['flights'][0]['start']);
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
