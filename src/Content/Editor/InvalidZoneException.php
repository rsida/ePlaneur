<?php

declare(strict_types=1);

namespace App\Content\Editor;

/**
 * A block zone sent by the editor contains blocks that cannot be saved.
 */
final class InvalidZoneException extends \RuntimeException
{
    /**
     * @param list<string> $errors one French message per invalid block
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct(implode(' ', $errors));
    }
}
