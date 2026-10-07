<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Group;

/**
 * Content whose visibility is restricted (menu links, posts, pages, documents...).
 *
 * Check it with `is_granted('CONTENT_VIEW', content)`; see {@see Voter\ContentVoter}.
 */
interface RestrictedContentInterface
{
    public function getVisibility(): Visibility;

    /**
     * Groups allowed to see the content when the visibility is {@see Visibility::Groups}.
     *
     * @return iterable<Group>
     */
    public function getAllowedGroups(): iterable;

    /**
     * For readers outside its audience: announced (listed with a padlock; its address says it is
     * reserved) or private (listed nowhere; its address answers "not found").
     */
    public function isAnnounced(): bool;
}
