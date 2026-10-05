<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\UserCrudController;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * @extends AbstractCrudTestCase<UserCrudController>
 */
final class UserCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return UserCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testValidatingAMembershipAddsTheMemberGroup(): void
    {
        $admin = $this->userIn($this->entityManager, 'admin@example.org', 'admin');
        $registered = $this->userIn($this->entityManager, 'inscrit@example.org');
        $this->client->loginUser($admin);

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertResponseIsSuccessful();
        $this->assertGlobalActionNotExists('new');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($registered->getId()));
        $form = $crawler->filter('form[name="User"]')->form();
        $values = $form->getPhpValues();
        $values['User']['groups'] = [(string) $this->defaultGroup('member')->getId()];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $registered = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'inscrit@example.org']);
        self::assertNotNull($registered);
        self::assertTrue($registered->isInGroup('member'));
    }

    public function testAdministratorsCannotDeleteTheirOwnAccount(): void
    {
        $admin = $this->userIn($this->entityManager, 'admin@example.org', 'admin');
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        $token = (string) $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/user/'.$admin->getId().'/delete', ['token' => $token]);

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.org']));
    }
}
