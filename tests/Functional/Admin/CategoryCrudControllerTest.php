<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\Admin\DashboardController;
use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Post categories: creation with their address, and addresses that are refused.
 *
 * @extends AbstractCrudTestCase<CategoryCrudController>
 */
final class CategoryCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return CategoryCrudController::class;
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

    public function testACategoryIsCreatedAndListed(): void
    {
        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $this->client->submit($crawler->filter('form[name="Category"]')->form([
            'Category[name]' => 'Météo',
            'Category[slug]' => 'meteo',
            'Category[description]' => 'Lire le ciel avant de voler.',
        ]));
        self::assertResponseRedirects();

        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => 'meteo']);
        self::assertSame('Météo', $category?->getName());

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('tbody', 'Météo');
    }

    public function testInvalidAddressesAreRefused(): void
    {
        foreach (['Avec Majuscules', str_repeat('a', 121)] as $slug) {
            $crawler = $this->client->request('GET', $this->generateNewFormUrl());
            $this->client->submit($crawler->filter('form[name="Category"]')->form([
                'Category[name]' => 'Essai',
                'Category[slug]' => $slug,
            ]));

            self::assertResponseStatusCodeSame(422, $slug);
            self::assertNull($this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]));
        }
    }
}
