<?php

declare(strict_types=1);

namespace App\Repository;

use App\Security\RestrictedContentInterface;
use App\Security\Visibility;

/**
 * For repositories of restricted content (posts, pages): loads the allowed groups of a list of
 * contents in one query, instead of one per content when the list checks access (ContentVoter) or
 * shows the audience (AccessLabel).
 */
trait LoadsAllowedGroups
{
    /**
     * @template C of RestrictedContentInterface
     *
     * @param iterable<C> $contents managed entities of this repository
     *
     * @return list<C>
     */
    public function loadAllowedGroups(iterable $contents): array
    {
        $contents = [...$contents];
        $restricted = array_filter($contents, static fn (RestrictedContentInterface $content): bool => Visibility::Groups === $content->getVisibility());
        if ([] !== $restricted) {
            // Fetch-joining fills the group collections of the entities already loaded
            $this->createQueryBuilder('content')
                ->select('content', 'allowedGroup')
                ->leftJoin('content.allowedGroups', 'allowedGroup')
                ->where('content IN (:contents)')
                ->setParameter('contents', array_values($restricted))
                ->getQuery()
                ->getResult();
        }

        return array_values($contents);
    }
}
