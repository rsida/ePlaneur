<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
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

        $users = [];
        foreach (self::USERS as $email => [$displayName, $defaults]) {
            $user = (new User())->setEmail($email)->setDisplayName($displayName)->setVerified(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            foreach ($defaults as $default) {
                $user->addGroup($groups[$default->value]);
            }
            $manager->persist($user);
            $users[$email] = $user;
        }

        // The 2026 season of the club, open, and the membership of the member account
        $season = (new MembershipSeason(2026))->setOpen(true)
            ->setPaymentInstructions('Virement au Club ePlaneur (IBAN communiqué par le trésorier), libellé « Adhésion 2026 + votre nom ».');
        $season->addType((new MembershipType())->setName('Moins de 25 ans')->setPrice(1000)->setMaxAge(24)->setPosition(1)->setDescription('Pour les jeunes, élèves de BIA notamment.'));
        $season->addType($adult = (new MembershipType())->setName('Adulte')->setPrice(2500)->setPosition(2));
        $manager->persist($season);
        $membership = (new Membership($users['membre@eplaneur.test'], $season, new \DateTimeImmutable('2026-01-10 10:00')))
            ->setType($adult)->setFirstName('Max')->setLastName('Membre')->setBirthDate(new \DateTimeImmutable('1990-05-12'))
            ->setAddress('1 rue des Planeurs')->setPostalCode('97400')->setCity('Saint-Denis')->setCountry('FR')->setPhone('+262 692 00 00 00')
            ->setRulesAccepted(true);
        $membership->markPaid(Membership::PAYMENT_TRANSFER, 'VIR 2026-01', new \DateTimeImmutable('2026-01-12'));
        $membership->validate($users['comite@eplaneur.test'], new \DateTimeImmutable('2026-01-13'));
        $manager->persist($membership);

        $manager->flush();
    }
}
