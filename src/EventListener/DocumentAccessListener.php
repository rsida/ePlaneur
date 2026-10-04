<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Copies the visibility of official documents onto their file before each flush, so the file URL
 * is exactly as restricted as the document itself. preFlush runs before Doctrine computes the
 * changes, so the media updates are saved in the same flush.
 */
#[AsDoctrineListener(event: Events::preFlush)]
final class DocumentAccessListener
{
    public function preFlush(PreFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $documents = [
            ...array_filter($unitOfWork->getScheduledEntityInsertions(), static fn (object $entity): bool => $entity instanceof Document),
            ...($unitOfWork->getIdentityMap()[Document::class] ?? []),
        ];

        foreach ($documents as $document) {
            \assert($document instanceof Document);
            $file = $document->getFile();
            $file->setVisibility($document->getVisibility());
            foreach ($file->getAllowedGroups()->toArray() as $group) {
                if (!$document->getAllowedGroups()->contains($group)) {
                    $file->removeAllowedGroup($group);
                }
            }
            foreach ($document->getAllowedGroups() as $group) {
                $file->addAllowedGroup($group);
            }
        }
    }
}
