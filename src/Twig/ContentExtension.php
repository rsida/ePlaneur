<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\PostPresenter;
use App\Entity\Post;
use Twig\Attribute\AsTwigFunction;

/**
 * Helpers for content listings: `reading_minutes(post)`.
 */
final class ContentExtension
{
    #[AsTwigFunction('reading_minutes')]
    public function readingMinutes(Post $post): int
    {
        return PostPresenter::readingMinutes([...$post->getBody(), ...$post->getOutro()]);
    }
}
