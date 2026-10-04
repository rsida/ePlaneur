<?php

declare(strict_types=1);

namespace App\Document;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Security\Voter\ContentVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Official documents the current reader may see, for the `documents` block (Twig `documents(id)`).
 */
final readonly class DocumentLibrary
{
    public function __construct(
        private DocumentRepository $documents,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** A document the reader may see, or null. */
    public function find(int $id): ?Document
    {
        $document = $this->documents->find($id);

        return null !== $document && $this->authorizationChecker->isGranted(ContentVoter::VIEW, $document) ? $document : null;
    }

    /**
     * @return array<string, list<Document>> documents by category name ('' = no category)
     */
    public function visibleByCategory(?int $categoryId = null): array
    {
        $groups = [];
        foreach ($this->documents->findForListing($categoryId) as $document) {
            if ($this->authorizationChecker->isGranted(ContentVoter::VIEW, $document)) {
                $groups[$document->getCategory()?->getName() ?? ''][] = $document;
            }
        }

        return $groups;
    }
}
