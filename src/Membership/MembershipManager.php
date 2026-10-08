<?php

declare(strict_types=1);

namespace App\Membership;

use App\Entity\Group;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\MembershipSeasonRepository;
use App\Repository\UserRepository;
use App\Security\DefaultGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * The life of a membership (roadmap step 8a): the member sends a request, the payment is recorded
 * (online or by the treasurer), the club validates it and the account joins the "Membre" group;
 * memberships end with the season (31 December), with a grace month for renewals, and members are
 * reminded to renew when the next season opens.
 *
 * Accounts put in the "Membre" group by hand, without any membership on the site (members of the
 * Yapla years), are never removed from it: only the end of a membership recorded here removes it.
 */
final readonly class MembershipManager
{
    /** Days of January during which the members of the past season stay members. */
    public const int GRACE_DAYS = 31;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipRepository $memberships,
        private MembershipSeasonRepository $seasons,
        private GroupRepository $groups,
        private UserRepository $users,
        private MembershipMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    /** A new request, ready for the form (the data of the account's last membership copied). */
    public function prepare(User $user, MembershipSeason $season): Membership
    {
        $membership = new Membership($user, $season, $this->clock->now());
        $previous = $this->memberships->findByUser($user)[0] ?? null;
        if (null !== $previous) {
            $membership->setFirstName($previous->getFirstName())->setLastName($previous->getLastName())
                ->setBirthDate($previous->getBirthDate())->setAddress($previous->getAddress())
                ->setPostalCode($previous->getPostalCode())->setCity($previous->getCity())
                ->setCountry($previous->getCountry())->setPhone($previous->getPhone());
        }

        return $membership;
    }

    public function request(Membership $membership): void
    {
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->mailer->sendRequested($membership);
    }

    public function markPaid(Membership $membership, string $method, ?string $reference): void
    {
        $membership->markPaid($method, $reference, $this->clock->now());
        $this->entityManager->flush();
    }

    public function validate(Membership $membership, ?User $by): void
    {
        $membership->validate($by, $this->clock->now());
        $membership->getUser()?->addGroup($this->memberGroup());
        $this->entityManager->flush();
        $this->mailer->sendValidated($membership);
    }

    public function refuse(Membership $membership, ?User $by): void
    {
        $membership->refuse($by, $this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * Removes the "Membre" group from the accounts whose recorded membership has ended: no validated
     * membership for the current year (nor for the past one during the grace month).
     *
     * @return list<User> the accounts removed
     */
    public function expire(bool $dryRun = false): array
    {
        $now = $this->clock->now();
        $year = (int) $now->format('Y');
        $inGrace = (int) $now->format('z') < self::GRACE_DAYS;
        $group = $this->memberGroup();
        $removed = [];
        foreach ($this->users->findInGroup($group) as $user) {
            if ([] === $this->memberships->findByUser($user)
                || $this->memberships->isMember($user, $year)
                || ($inGrace && $this->memberships->isMember($user, $year - 1))) {
                continue;
            }
            if (!$dryRun) {
                $user->removeGroup($group);
            }
            $removed[] = $user;
        }
        $this->entityManager->flush();

        return $removed;
    }

    /**
     * Reminds the members of the season before an open season who have not renewed yet, once.
     *
     * @return list<Membership> the memberships of the past season whose member was reminded
     */
    public function remind(): array
    {
        $reminded = [];
        foreach ($this->seasons->findOpen() as $season) {
            $past = $this->seasons->findOneByYear($season->getYear() - 1);
            if (null === $past) {
                continue;
            }
            foreach ($this->memberships->findValidated($past) as $membership) {
                $user = $membership->getUser();
                if (null === $user || null !== $membership->getReminderSentAt() || null !== $this->memberships->findActive($user, $season)) {
                    continue;
                }
                $this->mailer->sendReminder($membership, $season);
                $membership->markReminderSent($this->clock->now());
                $reminded[] = $membership;
            }
        }
        $this->entityManager->flush();

        return $reminded;
    }

    private function memberGroup(): Group
    {
        return $this->groups->findOneByCode(DefaultGroup::Member->value) ?? throw new \LogicException('The "member" group is missing: run the migrations.');
    }
}
