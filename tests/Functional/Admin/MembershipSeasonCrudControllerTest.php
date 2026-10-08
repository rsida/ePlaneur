<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MembershipSeasonCrudController;
use App\Entity\MembershipSeason;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Membership seasons: a season is created with its membership types and prices.
 *
 * @extends AbstractCrudTestCase<MembershipSeasonCrudController>
 */
final class MembershipSeasonCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return MembershipSeasonCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
    }

    public function testASeasonIsCreatedWithItsTypes(): void
    {
        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $form = $crawler->filter('form[name="MembershipSeason"]')->form();
        $values = $form->getPhpValues();
        $values['MembershipSeason']['year'] = '2027';
        $values['MembershipSeason']['open'] = '1';
        $values['MembershipSeason']['types'] = [
            ['name' => 'Moins de 25 ans', 'price' => '10', 'maxAge' => '24', 'position' => '1'],
            ['name' => 'Adulte', 'price' => '25,50', 'position' => '2'],
        ];
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();

        $season = $this->entityManager->getRepository(MembershipSeason::class)->findOneBy(['year' => 2027]);
        self::assertNotNull($season);
        self::assertTrue($season->isOpen());
        self::assertSame([1000, 2550], array_map(static fn ($type): int => $type->getPrice(), $season->getTypes()->toArray()));
        self::assertSame(24, $season->getTypes()->first()->getMaxAge());

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('tbody', '2027');
    }
}
