<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Block\BlockFactory;
use App\Content\Block\BlockInterface;
use App\Content\Block\SectionBlock;
use App\Content\Block\TakeawaysBlock;
use App\Media\MediaLibrary;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Turns stored block zones into placed blocks: numbered table of contents with unique anchors
 * (sections and takeaways, in zone order) and every referenced media preloaded in one query.
 */
final readonly class BlockPlacer
{
    public function __construct(
        private BlockFactory $blockFactory,
        private MediaLibrary $mediaLibrary,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @param array<string, list<array{type: string, data: array<string, mixed>}>> $zones         stored blocks by zone name, in reading order
     * @param list<int>                                                            $extraMediaIds media shown around the blocks (covers...)
     *
     * @return array{zones: array<string, list<PlacedBlock>>, toc: list<TocEntry>}
     */
    public function place(array $zones, array $extraMediaIds = []): array
    {
        return $this->arrange(array_map($this->blockFactory->createAll(...), $zones), $extraMediaIds);
    }

    /**
     * Places block objects. Their array keys make the block keys ("body-3"); the editor uses its own
     * block ids there.
     *
     * @param array<string, array<array-key, BlockInterface>> $zones         blocks by zone name, in reading order
     * @param list<int>                                       $extraMediaIds
     * @param array<string, mixed>                            $context       given to every placed block
     *
     * @return array{zones: array<string, list<PlacedBlock>>, toc: list<TocEntry>}
     */
    public function arrange(array $zones, array $extraMediaIds = [], array $context = []): array
    {
        $toc = [];
        $anchors = [];
        $placedZones = [];
        $mediaIds = $extraMediaIds;

        foreach ($zones as $zone => $blocks) {
            $placedZones[$zone] = [];
            foreach ($blocks as $key => $block) {
                $entry = null;
                if ($block instanceof SectionBlock || $block instanceof TakeawaysBlock) {
                    $entry = new TocEntry(\sprintf('%02d', \count($toc) + 1), $block->tocTitle(), $this->uniqueAnchor($block->tocTitle(), $anchors));
                    $toc[] = $entry;
                }
                $placedZones[$zone][] = new PlacedBlock($block, $zone.'-'.$key, $entry, $context);
                array_push($mediaIds, ...$block->mediaIds());
            }
        }

        $this->mediaLibrary->preload($mediaIds);

        return ['zones' => $placedZones, 'toc' => $toc];
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

        return max(1, (int) ceil($words / 200));
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
