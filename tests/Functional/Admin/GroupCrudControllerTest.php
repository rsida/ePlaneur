<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\GroupCrudController;
use App\Entity\Group;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * @extends AbstractCrudTestCase<GroupCrudController>
 */
final class GroupCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return GroupCrudController::class;
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

    public function testAnAdministratorChangesTheRightsOfAGroup(): void
    {
        $committee = $this->defaultGroup('committee');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($committee->getId()));
        $form = $crawler->filter('form[name="Group"]')->form();
        $values = $form->getPhpValues();
        // Enum choices are submitted by their position in Permission::cases()
        $values['Group']['permissions'][] = (string) array_search(Permission::UserManage, Permission::cases(), true);
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $committee = $this->entityManager->getRepository(Group::class)->findOneBy(['code' => 'committee']);
        self::assertNotNull($committee);
        self::assertTrue($committee->hasPermission(Permission::UserManage));
        self::assertTrue($committee->hasPermission(Permission::AdminAccess), 'Other rights are kept');
    }

    public function testTheCommitteeIncludesTheMembers(): void
    {
        $committee = $this->defaultGroup('committee');
        self::assertContains($this->defaultGroup('member'), $committee->getIncludedGroups()->toArray(), 'Set by the migration');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($committee->getId()));
        self::assertCount(1, $crawler->filter('[name="Group[includedGroups][]"][value="'.$this->defaultGroup('member')->getId().'"][checked]'));
    }

    public function testANewGroupGetsACode(): void
    {
        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $this->client->submit($crawler->filter('form[name="Group"]')->form([
            'Group[name]' => 'Rédacteurs',
            'Group[code]' => 'redacteur',
        ]));
        self::assertResponseRedirects();

        $group = $this->entityManager->getRepository(Group::class)->findOneBy(['code' => 'redacteur']);
        self::assertNotNull($group);
        self::assertSame('Rédacteurs', $group->getName());
        self::assertSame([], $group->getPermissions());
    }

    public function testDefaultGroupsCannotBeDeleted(): void
    {
        $member = $this->defaultGroup('member');

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorNotExists(\sprintf('tr[data-id="%d"] .action-delete', $member->getId()));

        // Even when the delete form is posted directly
        $this->client->request('POST', '/admin/group/'.$member->getId().'/delete', ['token' => $this->deleteToken()]);
        self::assertNotNull($this->entityManager->getRepository(Group::class)->findOneBy(['code' => 'member']));

        // Other groups can be deleted
        $this->entityManager->persist($temporary = new Group('temporaire', 'Temporaire'));
        $this->entityManager->flush();
        $id = $temporary->getId();
        $this->client->request('POST', '/admin/group/'.$id.'/delete', ['token' => $this->deleteToken()]);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Group::class, $id));
    }

    private function deleteToken(): string
    {
        $crawler = $this->client->request('GET', $this->generateIndexUrl());

        return (string) $crawler->filter('#action-confirmation-form input[name="token"]')->first()->attr('value');
    }
}
