<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Post;
use App\Repository\PostRepository;
use App\Security\Voter\ContentVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Prepares a post for its page: blocks, numbered table of contents, reading time, related posts
 * the reader is allowed to see, and media preloaded in one query.
 */
final readonly class PostPresenter
{
    private const int RELATED_COUNT = 3;

    public function __construct(
        private BlockPlacer $placer,
        private PostRepository $posts,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function present(Post $post): ArticleView
    {
        $related = $this->related($post);
        $covers = [];
        foreach ([$post, ...$related] as $item) {
            if (null !== $item->getCover()?->getId()) {
                $covers[] = (int) $item->getCover()->getId();
            }
        }

        $placed = $this->placer->place(['body' => $post->getBody(), 'aside' => $post->getAside(), 'outro' => $post->getOutro()], $covers);

        return new ArticleView(
            $post,
            $placed['zones']['body'],
            $placed['zones']['aside'],
            $placed['zones']['outro'],
            $placed['toc'],
            self::readingMinutes($post),
            $related,
        );
    }

    public static function readingMinutes(Post $post): int
    {
        return BlockPlacer::readingMinutes([...$post->getBody(), ...$post->getOutro()]);
    }

    /**
     * @return list<Post>
     */
    private function related(Post $post): array
    {
        $visible = array_filter(
            $this->posts->findRelatedCandidates($post),
            fn (Post $candidate): bool => $this->authorizationChecker->isGranted(ContentVoter::VIEW, $candidate),
        );

        return \array_slice(array_values($visible), 0, self::RELATED_COUNT);
    }
}
