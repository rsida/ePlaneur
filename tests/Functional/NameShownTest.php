<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\NameShown;
use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Track;
use App\Flight\Turnpoint;
use App\Session\ResultNames;
use App\Tests\Functional\Admin\AdminUsers;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The name a member shows to the others (pseudonyms for minors): chosen in the profile, used for their
 * flights, the rankings and the results of the server.
 */
final class NameShownTest extends WebTestCase
{
    use AdminUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testThePublicNameFollowsTheChoice(): void
    {
        $lea = (new User())->setDisplayName('Léa Petit');
        self::assertSame('Léa Petit', $lea->getPublicName());
        self::assertFalse($lea->hidesName());
        self::assertSame('Léa', $lea->setNameShown(NameShown::FirstName)->getPublicName());
        self::assertSame('Léa', $lea->setNameShown(NameShown::Pseudonym)->getPublicName(), 'The first name while no pseudonym is chosen');
        self::assertSame('Faucon', $lea->setPseudonym(' Faucon ')->getPublicName());
    }

    public function testTheMemberChoosesAPseudonymThatNobodyElseHas(): void
    {
        $this->userIn($this->entityManager, 'max@example.org')->setPseudonym('Faucon');
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $this->entityManager->flush();
        $this->client->loginUser($lea);
        $this->client->request('GET', '/mon-compte/profil');

        $this->client->submitForm('Enregistrer le nom affiché', ['display_name[displayName]' => 'Léa Petit', 'display_name[nameShown]' => 'pseudonym', 'display_name[pseudonym]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#display_name', 'Choisissez votre pseudonyme.');

        $this->client->submitForm('Enregistrer le nom affiché', ['display_name[pseudonym]' => 'faucon']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#display_name', 'Ce pseudonyme est déjà pris.');

        $this->client->submitForm('Enregistrer le nom affiché', ['display_name[pseudonym]' => 'Planeur Bleu']);
        self::assertResponseRedirects('/mon-compte/profil#display_name');
        $this->entityManager->clear();
        $lea = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'lea@example.org']) ?? throw new \LogicException();
        self::assertSame(NameShown::Pseudonym, $lea->getNameShown());
        self::assertSame('Planeur Bleu', $lea->getPublicName());
        self::assertSame('Léa Petit', $lea->getDisplayName());
    }

    public function testFlightsAndResultsShowThePseudonym(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org')->setDisplayName('Léa Petit')->setNameShown(NameShown::Pseudonym)->setPseudonym('Planeur Bleu');
        $max = $this->userIn($this->entityManager, 'max@example.org')->setDisplayName('Max Martin')->setNameShown(NameShown::FirstName)->setCondorCn('MM');
        $plan = new FlightPlan('C2-d015');
        $session = new NetworkSession($plan, new \DateTimeImmutable('2026-10-14'), new \DateTimeImmutable());
        $session->setResults('DSRace-1-on-2026-10-14', [
            $this->place(1, 'Léa Petit', 'LP'),
            $this->place(2, 'Max Martin', 'MM'),
            $this->place(3, 'Paul Durand', 'PD'),
        ]);
        $flight = $this->flight($lea, 'Léa Petit', 'LP')->setPublic(true)->setSession($session);
        $this->entityManager->persist($plan);
        $this->entityManager->persist($session);
        $this->entityManager->flush();
        $this->entityManager->refresh($session);

        self::assertSame('Léa Petit', $flight->getRecordedPilot());
        self::assertSame('Planeur Bleu', $flight->getPilot());
        $results = static::getContainer()->get(ResultNames::class)->of($session);
        self::assertSame(['Planeur Bleu', 'Max', 'Paul Durand'], array_column($results, 'player'), 'Léa by her track, Max by the CN of his profile');

        $this->client->request('GET', '/vols/'.$flight->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Planeur Bleu');
        self::assertStringNotContainsString('Léa Petit', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->userIn($this->entityManager, 'paul@example.org'));
        $this->client->request('GET', '/sessions/'.$session->getId());
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Planeur Bleu', $content);
        self::assertStringNotContainsString('Léa Petit', $content);
        self::assertStringNotContainsString('Max Martin', $content);
    }

    private function flight(User $owner, string $pilot, string $competitionId): Flight
    {
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 2', $pilot, $competitionId, 'ASK21', 'AA2', null, 'C2-d015 ePlaneur', [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)], FlightSamples::glideThermalGlide(), true, new \DateTimeImmutable('2026-10-14'));
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable('2026-10-14'));
        $this->entityManager->persist($flight);

        return $flight;
    }

    /** @return array<string, mixed> */
    private function place(int $rank, string $player, string $competitionId): array
    {
        return ['rank' => $rank, 'status' => 'Finished', 'player' => $player, 'competitionId' => $competitionId, 'registration' => null, 'glider' => 'ASK21', 'distance' => 50000, 'time' => 1500, 'speed' => 120.0 - $rank, 'penalty' => 0.0, 'score' => 1000.0];
    }
}
