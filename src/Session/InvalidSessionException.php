<?php

declare(strict_types=1);

namespace App\Session;

/**
 * What was sent for a session cannot be used (flight plan without code, unreadable results file);
 * the message, in French, is shown in the form.
 */
final class InvalidSessionException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }
}
