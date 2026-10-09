<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\FlightPlanCrudController;
use App\Entity\FlightPlan;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Flight plans in the back-office: their sheet address corrected by the committee.
 *
 * @extends AbstractCrudTestCase<FlightPlanCrudController>
 */
final class FlightPlanCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return FlightPlanCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheCommitteeCorrectsTheSheetOfAFlightPlan(): void
    {
        $this->client->disableReboot();
        $plan = new FlightPlan('C2-d009');
        $this->entityManager->persist($plan);
        $this->entityManager->flush();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('tbody', 'C2-d009');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl((int) $plan->getId()));
        $this->client->submit($crawler->filter('form[name="FlightPlan"]')->form([
            'FlightPlan[title]' => 'Barcelonnette-GapTallard-49km',
            'FlightPlan[sheetUrl]' => 'https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=C2-d009',
        ]));
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame('Barcelonnette-GapTallard-49km', $this->entityManager->find(FlightPlan::class, $plan->getId())?->getTitle());
    }

    public function testMembersCannotOpenIt(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
