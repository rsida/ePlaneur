<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Membership;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MembershipRepository;
use App\Repository\SchoolClassRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * What the member space shows around every page (templates/account/_layout.html.twig): the latest
 * membership of the logged-in account and its unread conversations; and the membership of the year
 * of any account.
 */
final readonly class AccountExtension
{
    public function __construct(
        private Security $security,
        private MembershipRepository $memberships,
        private ConversationRepository $conversations,
        private SchoolClassRepository $classes,
        private ClockInterface $clock,
    ) {
    }

    /** Conversations with a message the logged-in account has not read. */
    #[AsTwigFunction('unread_messages')]
    public function unreadMessages(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->conversations->countUnread($user) : 0;
    }

    /** Whether the logged-in account joined a class (doc/teacher-space.md). */
    #[AsTwigFunction('account_in_class')]
    public function inClass(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof User && [] !== $this->classes->findOfPupil($user);
    }

    #[AsTwigFunction('account_membership')]
    public function membership(): ?Membership
    {
        $user = $this->security->getUser();

        return $user instanceof User ? ($this->memberships->findByUser($user)[0] ?? null) : null;
    }

    /** Membership of an account for the current year (back-office list of members). */
    #[AsTwigFunction('membership_of_year')]
    public function ofYear(User $user): ?Membership
    {
        $year = (int) $this->clock->now()->format('Y');

        return array_find($this->memberships->findByUser($user), static fn (Membership $membership): bool => $membership->getSeason()->getYear() === $year);
    }
}
