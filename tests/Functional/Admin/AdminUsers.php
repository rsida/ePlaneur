<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accounts for back-office tests, in the default groups created by the migrations.
 */
trait AdminUsers
{
    private function userIn(EntityManagerInterface $entityManager, string $email, string ...$groupCodes): User
    {
        $user = (new User())->setEmail($email)->setDisplayName('Test '.$email)->setVerified(true)->setPassword('x');
        foreach ($groupCodes as $code) {
            $user->addGroup($this->defaultGroup($code));
        }
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function defaultGroup(string $code): Group
    {
        $group = static::getContainer()->get(GroupRepository::class)->findOneByCode($code);
        self::assertNotNull($group, 'Default groups missing: run make test-db.');

        return $group;
    }
}
