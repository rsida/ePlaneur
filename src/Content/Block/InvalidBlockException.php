<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * A stored block that cannot be read: unknown type, or data that no longer fits its class.
 */
final class InvalidBlockException extends \RuntimeException
{
    public function __construct(
        public readonly ?BlockType $type,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('%s: %s', $type->value ?? 'unknown', $reason), 0, $previous);
    }
}
