<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MemberCrudController;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Members in the back-office: searched by CN, filtered by membership of the year, their record with
 * memberships; read only, for the committee.
 *
 * @extends AbstractCrudTestCase<MemberCrudController>
 */
final class MemberCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return MemberCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheCommitteeFindsAMemberAndReadsTheirRecord(): void
    {
        $this->client->disableReboot();
        $jeanne = $this->userIn($this->entityManager, 'jeanne@example.org', 'member')->setCondorCn('JP1')->setCondorPilotName('Jeanne Pilote');
        $this->userIn($this->entityManager, 'paul@example.org');
        $season = new MembershipSeason((int) (new \DateTimeImmutable())->format('Y'));
        $season->addType($adult = (new MembershipType())->setName('Adulte')->setPrice(2500));
        $this->entityManager->persist($season);
        $membership = (new Membership($jeanne, $season, new \DateTimeImmutable()))->setType($adult)->setFirstName('Jeanne')->setLastName('Pilote')->setRulesAccepted(true);
        $committee = $this->userIn($this->entityManager, 'comite@example.org', 'committee');
        $membership->validate($committee, new \DateTimeImmutable());
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->client->loginUser($committee);

        $this->client->request('GET', $this->generateIndexUrl('JP1'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'jeanne@example.org');
        self::assertSelectorTextContains('tbody', 'Membre');
        self::assertSelectorTextContains('tbody', 'Validée');

        $this->client->request('GET', $this->generateIndexUrl().'?filters[membership]=validated');
        self::assertSelectorTextContains('tbody', 'jeanne@example.org');
        self::assertSelectorTextNotContains('tbody', 'paul@example.org');
        $this->client->request('GET', $this->generateIndexUrl().'?filters[membership]=none');
        self::assertSelectorTextContains('tbody', 'paul@example.org');
        self::assertSelectorTextNotContains('tbody', 'jeanne@example.org');

        $this->client->request('GET', $this->generateDetailUrl((int) $jeanne->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ep-member', 'Jeanne Pilote');
        self::assertSelectorTextContains('body', 'Adulte');
        self::assertSelectorTextContains('body', 'Aucun vol déposé.');
        self::assertSelectorNotExists('a.action-account', 'USER_MANAGE only');
    }

    public function testMembersCannotOpenIt(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
