<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MenuItemCrudController;
use App\Entity\MenuItem;
use App\Entity\Page;
use App\Navigation\MenuLocation;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Menu links: a new link appears in the site header; unsafe addresses, a page and an address
 * together, and a parent from another menu are refused.
 *
 * @extends AbstractCrudTestCase<MenuItemCrudController>
 */
final class MenuItemCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return MenuItemCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->userIn($this->entityManager, 'admin@example.org', 'admin'));
    }

    public function testANewLinkAppearsInTheHeader(): void
    {
        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $this->client->submit($crawler->filter('form[name="MenuItem"]')->form([
            'MenuItem[label]' => 'Condor Club',
            'MenuItem[url]' => 'https://www.condorsoaring.com',
            'MenuItem[position]' => '99',
        ]));
        self::assertResponseRedirects();
        self::assertNotNull($this->entityManager->getRepository(MenuItem::class)->findOneBy(['label' => 'Condor Club']));

        $this->client->request('GET', '/');
        self::assertSelectorExists('.c-nav a[href="https://www.condorsoaring.com"]');
    }

    public function testInvalidLinksAreRefused(): void
    {
        $footer = new MenuItem(MenuLocation::Footer, 'Colonne du pied de page');
        $this->entityManager->persist($footer);
        $page = new Page('Le Club', 'le-club');
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $cases = [
            'javascript:' => ['MenuItem[url]' => 'javascript:alert(1)'],
            'parent from another menu' => ['MenuItem[url]' => '/actualites', 'MenuItem[parent]' => (string) $footer->getId()],
            'page and address' => ['MenuItem[url]' => '/actualites', 'MenuItem[page]' => (string) $page->getId()],
        ];

        foreach ($cases as $case => $values) {
            $crawler = $this->client->request('GET', $this->generateNewFormUrl());
            $this->client->submit($crawler->filter('form[name="MenuItem"]')->form(['MenuItem[label]' => 'Piège '.$case] + $values));

            self::assertResponseStatusCodeSame(422, $case);
            self::assertNull($this->entityManager->getRepository(MenuItem::class)->findOneBy(['label' => 'Piège '.$case]), $case);
        }
    }
}
