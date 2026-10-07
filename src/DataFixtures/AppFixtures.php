<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\User;
use App\Security\DefaultGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Development data: the default groups (purged by the fixtures loader, normally created by a
 * migration) and one account per access level. Every account uses the password below.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'ePlaneur-dev-2026';

    /** @var array<string, array{string, list<DefaultGroup>}> e-mail => [display name, groups] */
    private const array USERS = [
        'admin@eplaneur.test' => ['Admin ePlaneur', [DefaultGroup::Admin]],
        'comite@eplaneur.test' => ['Camille Comité', [DefaultGroup::Committee]],
        'membre@eplaneur.test' => ['Max Membre', [DefaultGroup::Member]],
        'inscrit@eplaneur.test' => ['Inès Inscrite', []],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $groups = [];
        foreach (DefaultGroup::cases() as $default) {
            $group = (new Group($default->value, $default->label()))
                ->setDescription($default->description())
                ->setAllPermissions($default->hasAllPermissions())
                ->setPermissions($default->defaultPermissions())
                ->setSystem(true);
            $manager->persist($group);
            $this->addReference('group-'.$default->value, $group);
            $groups[$default->value] = $group;
        }
        foreach (DefaultGroup::cases() as $default) {
            foreach ($default->includedGroups() as $included) {
                $groups[$default->value]->addIncludedGroup($groups[$included->value]);
            }
        }

        foreach (self::USERS as $email => [$displayName, $defaults]) {
            $user = (new User())->setEmail($email)->setDisplayName($displayName)->setVerified(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            foreach ($defaults as $default) {
                $user->addGroup($groups[$default->value]);
            }
            $manager->persist($user);
        }

        $manager->flush();
    }
}
