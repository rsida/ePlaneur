<?php

declare(strict_types=1);

namespace App\Content\Block;

use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Converts stored blocks (`{"type": "...", "data": {...}}`) to block objects and back.
 *
 * Reading never fails: a block whose type is unknown or whose data no longer matches its class is
 * skipped and logged, so one broken block cannot take a whole page down.
 */
final readonly class BlockFactory
{
    public function __construct(
        private DenormalizerInterface&NormalizerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<array{type: string, data: array<string, mixed>}> $stored
     *
     * @return list<BlockInterface>
     */
    public function createAll(array $stored): array
    {
        $blocks = [];
        foreach ($stored as $index => $item) {
            $block = $this->create($item, $index);
            if (null !== $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param array{type?: mixed, data?: mixed} $stored
     */
    public function create(array $stored, int $index = 0): ?BlockInterface
    {
        try {
            return $this->createOrFail($stored);
        } catch (InvalidBlockException $exception) {
            $this->logger->warning('Content block {index} skipped: {message}', ['index' => $index, 'message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param array{type?: mixed, data?: mixed} $stored
     *
     * @throws InvalidBlockException when the type is unknown or the data does not fit its class
     */
    public function createOrFail(array $stored): BlockInterface
    {
        $type = \is_string($stored['type'] ?? null) ? BlockType::tryFrom($stored['type']) : null;
        if (null === $type) {
            throw new InvalidBlockException(null, \sprintf('unknown type "%s"', \is_string($stored['type'] ?? null) ? $stored['type'] : get_debug_type($stored['type'] ?? null)));
        }

        try {
            /* @var BlockInterface */
            return $this->serializer->denormalize(\is_array($stored['data'] ?? null) ? $stored['data'] : [], $type->blockClass());
        } catch (ExceptionInterface|\TypeError $exception) {
            throw new InvalidBlockException($type, $exception->getMessage(), $exception);
        }
    }

    /**
     * @param iterable<BlockInterface> $blocks
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function serializeAll(iterable $blocks): array
    {
        $stored = [];
        foreach ($blocks as $block) {
            /** @var array<string, mixed> $data */
            $data = $this->serializer->normalize($block, null, [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
            $stored[] = ['type' => $block::type()->value, 'data' => $data];
        }

        return $stored;
    }
}
