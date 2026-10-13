<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Airfield;
use App\Entity\Airspace;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Tests\Unit\Flight\Glider\GliderSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The flight plan workshop: who may open it, the landscapes and gliders it offers, and the data of a
 * landscape (regions, airfields, airspaces). The instructions themselves are composed in the browser.
 */
final class FlightPlanWorkshopTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOnlyAdministratorsOpenItForNow(): void
    {
        $this->client->request('GET', '/plans-de-vol/atelier');
        self::assertResponseRedirects();

        $this->client->loginUser($this->user('max@example.org', 'committee'));
        $this->client->request('GET', '/plans-de-vol/atelier');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/plans-de-vol/atelier/AA3.json');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/sessions');
        self::assertSelectorExists('a[href="/sessions/nouvelle"]');
        self::assertSelectorNotExists('a[href="/plans-de-vol/atelier"]');
        $this->client->request('GET', '/admin');
        self::assertSelectorNotExists('a[href="/plans-de-vol/atelier"]');
    }

    public function testTheWorkshopOffersTheLandscapesWithAirfieldsAndTheGliders(): void
    {
        $this->entityManager->persist(new Airfield('AA3', 'Barcelonnette', 44.387, 6.609, 1132.0, 920));
        $this->entityManager->persist(new Airfield('Unknown', 'Somewhere', 45.0, 6.0, 500.0, 600));
        $this->entityManager->persist(GliderSamples::glider('Diana2'));
        $this->entityManager->flush();
        $this->client->loginUser($this->user('max@example.org', 'admin'));

        $this->client->request('GET', '/plans-de-vol/atelier');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Atelier plans de vol');
        $page = $this->client->getCrawler()->filter('[data-controller="flight-plan-workshop"]');
        self::assertSame(['AA3'], json_decode((string) $page->attr('data-flight-plan-workshop-landscapes-value'), true));
        self::assertSame(['15 mètres' => [['Diana2', 15, 180]]], json_decode((string) $page->attr('data-flight-plan-workshop-gliders-value'), true));
        self::assertSelectorExists('select#startType option[value="window"][selected]', 'The start is a window by default');
        self::assertSelectorExists('select#finishType option[value="window"][selected]', 'The finish is a window by default');

        $this->client->request('GET', '/sessions');
        self::assertSelectorExists('a[href="/plans-de-vol/atelier"]');
        $this->client->request('GET', '/admin');
        self::assertSelectorCount(2, 'a[href="/plans-de-vol/atelier"]', 'In the menu and on the dashboard');
    }

    public function testTheDataOfALandscapeGivesItsRegionsAirfieldsAndAirspaces(): void
    {
        $this->entityManager->persist(new Airfield('AA3', 'Barcelonnette', 44.387, 6.609, 1132.0, 920));
        $this->entityManager->persist(new Airfield('AA3', 'Dole', 47.04, 5.43, 196.0, 2200));
        $this->entityManager->persist(new Airspace('AA3', 'R196A1 Est GAP (Notam)', 'UNCLASSIFIED', '3300FT AGL', 'FL195', 44.642, 6.691));
        $this->entityManager->flush();
        $this->client->loginUser($this->user('max@example.org', 'admin'));

        $this->client->request('GET', '/plans-de-vol/atelier/AA3.json');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['Alpes du Sud', 'Jura et Bourgogne'], $data['regions']);
        self::assertSame(['Barcelonnette', 1132, 920, 'Alpes du Sud'], $data['airfields'][0]);
        self::assertSame([['R196A1 Est GAP (Notam)', 'UNCLASSIFIED', '3300FT AGL', 'FL195', 'Alpes du Sud']], $data['airspaces']);

        $this->client->request('GET', '/plans-de-vol/atelier/Nowhere.json');
        self::assertResponseStatusCodeSame(404);
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
