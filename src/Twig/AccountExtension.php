<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Membership;
use App\Entity\User;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/**
 * What the member space shows around every page (templates/account/_layout.html.twig): the latest
 * membership of the logged-in account.
 */
final readonly class AccountExtension
{
    public function __construct(
        private Security $security,
        private MembershipRepository $memberships,
    ) {
    }

    #[AsTwigFunction('account_membership')]
    public function membership(): ?Membership
    {
        $user = $this->security->getUser();

        return $user instanceof User ? ($this->memberships->findByUser($user)[0] ?? null) : null;
    }
}
