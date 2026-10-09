<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\NetworkSessionCrudController;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Sessions in the back-office: listed and corrected by the committee, not by members.
 *
 * @extends AbstractCrudTestCase<NetworkSessionCrudController>
 */
final class NetworkSessionCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return NetworkSessionCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheCommitteeCorrectsASession(): void
    {
        $this->client->disableReboot();
        $plan = (new FlightPlan('C3-c046a'))->setTitle('CdF2025V2-J1R1-StAuban-Challes');
        $session = new NetworkSession($plan, new \DateTimeImmutable('2026-10-08'), new \DateTimeImmutable());
        $this->entityManager->persist($plan);
        $this->entityManager->persist($session);
        $this->entityManager->flush();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'C3-c046a');
        self::assertSelectorExists('a[href="/sessions/nouvelle"]');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl((int) $session->getId()));
        $this->client->submit($crawler->filter('form[name="NetworkSession"]')->form(['NetworkSession[dedicatedTo]' => 'Compétition']));
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame('Compétition', $this->entityManager->find(NetworkSession::class, $session->getId())?->getDedicatedTo());
    }

    public function testMembersCannotOpenIt(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
