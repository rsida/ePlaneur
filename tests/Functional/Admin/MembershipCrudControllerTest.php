<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MembershipCrudController;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use App\Entity\User;
use App\Membership\MembershipStatus;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Membership requests in the back-office: payment recorded, validation (the account joins the
 * "Membre" group and is told by e-mail), refusal, CSV export of a season.
 *
 * @extends AbstractCrudTestCase<MembershipCrudController>
 */
final class MembershipCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    private Membership $membership;

    protected function getControllerFqcn(): string
    {
        return MembershipCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $season = (new MembershipSeason(2026))->setOpen(true);
        $season->addType($adult = (new MembershipType())->setName('Adulte')->setPrice(2500));
        $this->entityManager->persist($season);
        $member = $this->userIn($this->entityManager, 'jeanne@example.org');
        $this->membership = (new Membership($member, $season, new \DateTimeImmutable('2026-01-10')))
            ->setType($adult)->setDonation(500)->setFirstName('Jeanne')->setLastName('Pilote')->setBirthDate(new \DateTimeImmutable('1990-01-01'))
            ->setAddress('12 rue des Thermiques')->setPostalCode('97400')->setCity('Saint-Denis')->setPhone('0692')->setRulesAccepted(true);
        $this->entityManager->persist($this->membership);
        $this->entityManager->flush();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
    }

    public function testThePaymentIsRecordedThenTheMembershipValidated(): void
    {
        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('tbody', 'Jeanne Pilote');
        self::assertSelectorTextContains('body', 'Saisons et tarifs', 'In the menu');

        $this->client->submit($crawler->filter('form[action*="/paiement"]')->form());
        self::assertResponseRedirects();
        self::assertSame(MembershipStatus::Paid, $this->reload()->getStatus());
        self::assertSame(Membership::PAYMENT_TRANSFER, $this->reload()->getPaymentMethod());

        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        $this->client->submit($crawler->filter('form[action*="/valider"]')->form());
        self::assertResponseRedirects();
        $membership = $this->reload();
        self::assertSame(MembershipStatus::Validated, $membership->getStatus());
        self::assertSame('comite@example.org', $membership->getValidatedBy()?->getEmail());
        $member = $membership->getUser();
        self::assertInstanceOf(User::class, $member);
        self::assertTrue($member->isInGroup('member'), 'The account joined the "Membre" group');
        self::assertQueuedEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? throw new \LogicException(), 'attestation');
    }

    public function testActionsNeedTheirToken(): void
    {
        $this->client->request('POST', '/admin/membership/'.$this->membership->getId().'/valider?token=forged');

        self::assertResponseStatusCodeSame(403);
        self::assertSame(MembershipStatus::Requested, $this->reload()->getStatus());
    }

    public function testARequestIsRefused(): void
    {
        $crawler = $this->client->request('GET', $this->generateDetailUrl((int) $this->membership->getId()));
        $this->client->submit($crawler->filter('form[action*="/refuser"]')->form());

        self::assertSame(MembershipStatus::Refused, $this->reload()->getStatus());
        self::assertFalse($this->reload()->getUser()?->isInGroup('member'));
    }

    public function testASeasonIsExportedForTheTreasurer(): void
    {
        $this->client->request('GET', '/admin/membership/export?saison=2026');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('Nom;Prénom;E-mail', $csv);
        self::assertStringContainsString('Pilote;Jeanne;jeanne@example.org;01/01/1990', $csv);
        self::assertStringContainsString('Adulte;25,00;5,00;30,00', $csv);
    }

    private function reload(): Membership
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Membership::class)->find($this->membership->getId()) ?? throw new \LogicException('Membership deleted');
    }

    public function testMembersCannotOpenTheScreen(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
