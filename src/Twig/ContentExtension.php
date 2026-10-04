<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\PostPresenter;
use App\Document\DocumentLibrary;
use App\Entity\Document;
use App\Entity\Post;
use Twig\Attribute\AsTwigFunction;

/**
 * Helpers for content listings: `reading_minutes(post)`, `document(id)`, `documents(categoryId)`.
 */
final readonly class ContentExtension
{
    public function __construct(
        private DocumentLibrary $documents,
    ) {
    }

    /** A document the reader may see, or null. */
    #[AsTwigFunction('document')]
    public function document(int $id): ?Document
    {
        return $this->documents->find($id);
    }

    /**
     * @return array<string, list<Document>> documents the reader may see, by category name
     */
    #[AsTwigFunction('documents')]
    public function documents(?int $categoryId = null): array
    {
        return $this->documents->visibleByCategory($categoryId);
    }

    #[AsTwigFunction('reading_minutes')]
    public function readingMinutes(Post $post): int
    {
        return PostPresenter::readingMinutes($post);
    }
}
