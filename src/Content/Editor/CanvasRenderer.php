<?php

declare(strict_types=1);

namespace App\Content\Editor;

use App\Content\Block\BlockFactory;
use App\Content\Block\InvalidBlockException;
use App\Content\BlockPlacer;
use Twig\Environment;

/**
 * Renders the blocks of the editor canvas with their site components, one HTML fragment per block
 * id, plus the table of contents. Blocks are numbered and preloaded exactly as on the site; their
 * values carry `data-ep-field` markers (edit_field()) so the editor can edit them in place.
 */
final readonly class CanvasRenderer
{
    public function __construct(
        private BlockFactory $blockFactory,
        private BlockPlacer $placer,
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, list<array{id?: mixed, type?: mixed, data?: mixed}>> $zones   blocks by zone, each with the editor id
     * @param array<string, mixed>                                               $context data some blocks need (child pages...)
     *
     * @return array{blocks: array<string, string>, toc: string}
     */
    public function render(array $zones, array $context = []): array
    {
        $html = [];
        $valid = [];
        foreach ($zones as $zone => $items) {
            $valid[$zone] = [];
            foreach ($items as $item) {
                $id = \is_scalar($item['id'] ?? null) ? (string) $item['id'] : '';
                if ('' === $id) {
                    continue;
                }
                try {
                    $valid[$zone][$id] = $this->blockFactory->createOrFail($item);
                } catch (InvalidBlockException) {
                    $html[$id] = $this->twig->render('admin/editor/_invalid_block.html.twig');
                }
            }
        }

        $placed = $this->placer->arrange($valid, [], ['editing' => true, ...$context]);
        foreach ($placed['zones'] as $zone => $blocks) {
            foreach ($blocks as $block) {
                $html[substr($block->key, \strlen($zone) + 1)] = $this->twig->render('admin/editor/_block.html.twig', ['placed' => $block]);
            }
        }

        return [
            'blocks' => $html,
            'toc' => $this->twig->render('admin/editor/_toc.html.twig', ['entries' => $placed['toc']]),
        ];
    }

    /**
     * Block zones sent by the editor as JSON (`{"body": [{"id": "b1", "type": "text", "data": {...}}]}`),
     * limited to the given zone names; anything unreadable is dropped.
     *
     * @param list<string> $names
     *
     * @return array<string, list<array{id?: mixed, type?: mixed, data?: mixed}>>
     */
    public static function decodeZones(string $json, array $names): array
    {
        try {
            $zones = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return array_map(
            static fn (mixed $items): array => \is_array($items) ? array_values(array_filter($items, \is_array(...))) : [],
            array_intersect_key(\is_array($zones) ? $zones : [], array_flip($names)),
        );
    }
}
