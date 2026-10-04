<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Block\BlockFactory;
use App\Content\Block\BlockInterface;
use App\Content\Block\SectionBlock;
use App\Content\Block\TakeawaysBlock;
use App\Entity\Post;
use App\Media\MediaLibrary;
use App\Repository\PostRepository;
use App\Security\Voter\ContentVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Prepares a post for its page: blocks, numbered table of contents, reading time, related posts
 * the reader is allowed to see, and media preloaded in one query.
 */
final readonly class PostPresenter
{
    private const int WORDS_PER_MINUTE = 200;
    private const int RELATED_COUNT = 3;

    public function __construct(
        private BlockFactory $blockFactory,
        private MediaLibrary $mediaLibrary,
        private PostRepository $posts,
        private AuthorizationCheckerInterface $authorizationChecker,
        private SluggerInterface $slugger,
    ) {
    }

    public function present(Post $post): ArticleView
    {
        $body = $this->blockFactory->createAll($post->getBody());
        $aside = $this->blockFactory->createAll($post->getAside());
        $outro = $this->blockFactory->createAll($post->getOutro());

        $toc = [];
        $anchors = [];
        $place = function (array $blocks, string $zone) use (&$toc, &$anchors): array {
            $placed = [];
            foreach ($blocks as $index => $block) {
                $entry = null;
                if ($block instanceof SectionBlock || $block instanceof TakeawaysBlock) {
                    $entry = new TocEntry(\sprintf('%02d', \count($toc) + 1), $block->tocTitle(), $this->uniqueAnchor($block->tocTitle(), $anchors));
                    $toc[] = $entry;
                }
                $placed[] = new PlacedBlock($block, $zone.'-'.$index, $entry);
            }

            return $placed;
        };

        $related = $this->related($post);
        $mediaIds = array_merge(...array_map(static fn (BlockInterface $block): array => $block->mediaIds(), [...$body, ...$aside, ...$outro]));
        foreach ([$post, ...$related] as $item) {
            if (null !== $item->getCover()?->getId()) {
                $mediaIds[] = (int) $item->getCover()->getId();
            }
        }
        $this->mediaLibrary->preload($mediaIds);

        return new ArticleView(
            $post,
            $place($body, 'body'),
            $place($aside, 'aside'),
            $place($outro, 'outro'),
            $toc,
            self::readingMinutes([...$post->getBody(), ...$post->getOutro()]),
            $related,
        );
    }

    /**
     * Reading time of stored blocks: words of every text value, at 200 words per minute.
     *
     * @param list<array<string, mixed>> $storedBlocks
     */
    public static function readingMinutes(array $storedBlocks): int
    {
        $words = 0;
        array_walk_recursive($storedBlocks, static function (mixed $value, int|string $key) use (&$words): void {
            if (\is_string($value) && 'type' !== $key && !str_starts_with($value, 'http')) {
                $words += (int) preg_match_all('/[\p{L}\p{N}’\'-]+/u', strip_tags($value));
            }
        });

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
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

    /**
     * @param array<string, true> $used
     */
    private function uniqueAnchor(string $title, array &$used): string
    {
        $base = $this->slugger->slug($title)->lower()->toString() ?: 'section';
        $anchor = $base;
        for ($i = 2; isset($used[$anchor]); ++$i) {
            $anchor = $base.'-'.$i;
        }
        $used[$anchor] = true;

        return $anchor;
    }
}
