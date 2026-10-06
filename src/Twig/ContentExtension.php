<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\PlacedBlock;
use App\Content\PostPresenter;
use App\Document\DocumentLibrary;
use App\Entity\Document;
use App\Entity\Post;
use Twig\Attribute\AsTwigFunction;

/**
 * Helpers for content listings: `reading_minutes(post)`, `document(id)`, `documents(categoryId)`, and
 * `edit_field(placed, path)` for the block editor.
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

    /**
     * Marks the element holding a block value as editable in place, in the back-office canvas only:
     * `<h3{{ edit_field(placed, 'title') }}>`, `<li{{ edit_field(placed, 'items.' ~ loop.index0, 'rich') }}>`.
     * Prints nothing on the site.
     *
     * @param string $kind "plain" (text only) or "rich" (limited HTML)
     */
    #[AsTwigFunction('edit_field', isSafe: ['html'])]
    public function editField(PlacedBlock $placed, string $path, string $kind = 'plain'): string
    {
        if (true !== ($placed->context['editing'] ?? false)) {
            return '';
        }

        return \sprintf(' data-ep-field="%s" data-ep-kind="%s"', htmlspecialchars($path, \ENT_QUOTES), 'rich' === $kind ? 'rich' : 'plain');
    }
}
