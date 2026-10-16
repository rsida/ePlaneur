<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\SchoolClassCrudController;
use App\Entity\SchoolClass;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * Classes in the back-office: seen and corrected by whoever manages the accounts.
 *
 * @extends AbstractCrudTestCase<SchoolClassCrudController>
 */
final class SchoolClassCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return SchoolClassCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testAnAdministratorClosesTheLinkOfAClass(): void
    {
        $this->client->disableReboot();
        $teacher = $this->userIn($this->entityManager, 'prof@example.org');
        $class = new SchoolClass($teacher, 'BIA 2026-2027', new \DateTimeImmutable());
        $class->addPupil($this->userIn($this->entityManager, 'eleve@example.org'));
        $this->entityManager->persist($class);
        $this->entityManager->flush();
        $this->client->loginUser($this->userIn($this->entityManager, 'admin@example.org', 'admin'));

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'BIA 2026-2027');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl((int) $class->getId()));
        $form = $crawler->filter('form[name="SchoolClass"]')->form();
        $open = $form['SchoolClass[open]'];
        self::assertInstanceOf(ChoiceFormField::class, $open);
        $open->untick();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $class = $this->entityManager->find(SchoolClass::class, $class->getId());
        self::assertFalse($class?->isOpen());
        self::assertCount(1, $class->getPupils(), 'The pupils stay');
    }

    public function testTheCommitteeCannotOpenIt(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
