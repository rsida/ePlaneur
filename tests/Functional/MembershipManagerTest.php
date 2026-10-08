<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use App\Entity\User;
use App\Membership\MembershipMailer;
use App\Membership\MembershipManager;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\MembershipSeasonRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * End of memberships and renewal reminders, at chosen dates.
 */
final class MembershipManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->season(2026);
    }

    public function testTheGroupIsRemovedAfterTheGraceMonthUnlessRenewed(): void
    {
        $renewed = $this->member('renouvele@example.org', [2026, 2027]);
        $late = $this->member('retard@example.org', [2026]);
        $byHand = $this->user('yapla@example.org', true);
        $this->season(2027);

        self::assertSame([], $this->manager('2027-01-20')->expire(), 'January: the 2026 members stay members');

        $removed = $this->manager('2027-02-01')->expire(true);
        self::assertSame(['retard@example.org'], array_map(static fn (User $user): string => $user->getEmail(), $removed));
        self::assertTrue($late->isInGroup('member'), 'Dry run');

        $this->manager('2027-02-01')->expire();
        self::assertFalse($late->isInGroup('member'));
        self::assertTrue($renewed->isInGroup('member'));
        self::assertTrue($byHand->isInGroup('member'), 'Members of the Yapla years, without membership on the site, stay members');
    }

    public function testPastMembersAreRemindedOnceWhenTheNextSeasonOpens(): void
    {
        $this->member('renouvele@example.org', [2026, 2027]);
        $this->member('oublie@example.org', [2026]);

        self::assertSame([], $this->manager('2026-12-01')->remind(), 'The 2027 season is not open yet');

        $this->season(2027)->setOpen(true);
        $this->entityManager->flush();
        $reminded = $this->manager('2026-12-01')->remind();
        self::assertSame(['oublie@example.org'], array_map(static fn (Membership $membership): string => $membership->getEmail(), $reminded));
        self::assertQueuedEmailCount(1);
        self::assertSame([], $this->manager('2026-12-02')->remind(), 'Once');
    }

    private function manager(string $now): MembershipManager
    {
        $container = static::getContainer();

        return new MembershipManager(
            $this->entityManager,
            $container->get(MembershipRepository::class),
            $container->get(MembershipSeasonRepository::class),
            $container->get(GroupRepository::class),
            $container->get(UserRepository::class),
            $container->get(MembershipMailer::class),
            new MockClock($now),
        );
    }

    private function season(int $year): MembershipSeason
    {
        $season = $this->entityManager->getRepository(MembershipSeason::class)->findOneBy(['year' => $year]);
        if (null === $season) {
            $season = new MembershipSeason($year);
            $season->addType((new MembershipType())->setName('Adulte')->setPrice(2500));
            $this->entityManager->persist($season);
            $this->entityManager->flush();
        }

        return $season;
    }

    /**
     * @param list<int> $years validated memberships
     */
    private function member(string $email, array $years): User
    {
        $user = $this->user($email, true);
        foreach ($years as $year) {
            $season = $this->season($year);
            $membership = (new Membership($user, $season, new \DateTimeImmutable($year.'-01-05')))->setType($season->getTypes()->first() ?: null)
                ->setFirstName('A')->setLastName('B')->setAddress('x')->setPostalCode('1')->setCity('c')->setPhone('0')->setRulesAccepted(true);
            $membership->validate(null, new \DateTimeImmutable($year.'-01-06'));
            $this->entityManager->persist($membership);
        }
        $this->entityManager->flush();

        return $user;
    }

    private function user(string $email, bool $member): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($email)->setVerified(true)->setPassword('x');
        if ($member) {
            $user->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode('member') ?? throw new \LogicException('run make test-db'));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
