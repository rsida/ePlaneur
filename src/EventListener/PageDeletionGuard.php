<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Refuses to delete a page that still has child pages: the whole section would disappear with it.
 * Move or delete the children first.
 */
#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
final class PageDeletionGuard
{
    public function preRemove(Page $page): void
    {
        if (!$page->getChildren()->isEmpty()) {
            throw new \LogicException(\sprintf('The page "%s" still has %d child page(s): move or delete them first.', $page->getTitle(), $page->getChildren()->count()));
        }
    }
}
