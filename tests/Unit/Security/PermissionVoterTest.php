<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Group;
use App\Entity\User;
use App\Security\Permission;
use App\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PermissionVoterTest extends TestCase
{
    public function testGrantsAPermissionHeldByOneOfTheGroups(): void
    {
        $editors = (new Group('editor', 'Rédacteur'))->grant(Permission::AdminAccess);
        $user = (new User())->setEmail('jane@example.org')->addGroup(new Group('member', 'Membre'))->addGroup($editors);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($user, Permission::AdminAccess));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, Permission::UserManage));
    }

    public function testAGroupWithAllPermissionsGrantsEveryPermission(): void
    {
        $user = (new User())->setEmail('admin@example.org')->addGroup((new Group('admin', 'Admin'))->setAllPermissions(true));

        foreach (Permission::cases() as $permission) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($user, $permission));
        }
    }

    public function testRevokedPermissionIsDenied(): void
    {
        $group = (new Group('editor', 'Rédacteur'))->grant(Permission::UserManage)->revoke(Permission::UserManage);
        $user = (new User())->setEmail('jane@example.org')->addGroup($group);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, Permission::UserManage));
    }

    public function testVisitorsAreDenied(): void
    {
        $result = (new PermissionVoter())->vote(new NullToken(), null, [Permission::AdminAccess->value]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAbstainsOnOtherAttributes(): void
    {
        $user = (new User())->setEmail('jane@example.org');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new PermissionVoter())->vote($token, null, ['ROLE_USER']));
    }

    private function vote(User $user, Permission $permission): int
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return (new PermissionVoter())->vote($token, null, [$permission->value]);
    }
}
