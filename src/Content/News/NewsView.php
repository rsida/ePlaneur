<?php

declare(strict_types=1);

namespace App\Content\News;

use App\Entity\Category;
use App\Entity\Post;

/**
 * Everything the news list renders, prepared by {@see NewsLister}.
 */
final readonly class NewsView
{
    /**
     * @param list<Post>                                  $posts      posts of the current page
     * @param list<array{category: Category, count: int}> $categories categories with published posts
     * @param array<int, list<int>>                       $periods    months with published posts, by year (newest first)
     */
    public function __construct(
        public NewsFilter $filter,
        public ?Category $category,
        public ?Post $featured,
        public array $posts,
        public int $total,
        public int $pages,
        public int $perPage,
        public int $allCount,
        public array $categories,
        public array $periods,
    ) {
    }

    /** Position of the first post of the page, from 1. */
    public function first(): int
    {
        return [] === $this->posts ? 0 : ($this->filter->page - 1) * $this->perPage + 1;
    }

    public function last(): int
    {
        return $this->first() + \count($this->posts) - ($this->posts ? 1 : 0);
    }
}
