<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Post;

/**
 * Everything the article page renders, prepared by {@see PostPresenter}.
 */
final readonly class ArticleView
{
    /**
     * @param list<PlacedBlock> $body
     * @param list<PlacedBlock> $aside
     * @param list<PlacedBlock> $outro
     * @param list<TocEntry>    $toc
     * @param list<Post>        $related
     */
    public function __construct(
        public Post $post,
        public array $body,
        public array $aside,
        public array $outro,
        public array $toc,
        public int $readingMinutes,
        public array $related,
    ) {
    }
}
