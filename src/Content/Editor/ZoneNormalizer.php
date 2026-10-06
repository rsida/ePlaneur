<?php

declare(strict_types=1);

namespace App\Content\Editor;

use App\Content\Block\BlockFactory;
use App\Content\Block\InvalidBlockException;

/**
 * Turns a block zone sent by the editor into stored blocks: each block is read through its class
 * (so only known types and fields are kept, with their types) and written back. Editor-only keys,
 * such as the block ids, are dropped.
 */
final readonly class ZoneNormalizer
{
    public function __construct(
        private BlockFactory $blockFactory,
    ) {
    }

    /**
     * @param string $contentKind "post" or "page"
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     *
     * @throws InvalidZoneException listing the blocks that cannot be saved
     */
    public function normalize(mixed $zone, string $contentKind): array
    {
        if (!\is_array($zone) || !array_is_list($zone)) {
            throw new InvalidZoneException(['La liste des blocs est illisible.']);
        }

        $blocks = [];
        $errors = [];
        foreach ($zone as $index => $item) {
            try {
                $block = $this->blockFactory->createOrFail(\is_array($item) ? $item : []);
                if (!$block::type()->allowedIn($contentKind)) {
                    throw new InvalidBlockException($block::type(), 'not allowed here');
                }
                $blocks[] = $block;
            } catch (InvalidBlockException $exception) {
                $errors[] = \sprintf('Bloc %d (%s) : contenu invalide.', $index + 1, $exception->type?->label() ?? 'type inconnu');
            }
        }

        if ([] !== $errors) {
            throw new InvalidZoneException($errors);
        }

        return $this->blockFactory->serializeAll($blocks);
    }
}
