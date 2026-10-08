<?php

declare(strict_types=1);

namespace App\Flight\Reader;

/**
 * A file that cannot be read as a flight track; the message is shown to the user.
 */
final class InvalidTrackException extends \RuntimeException
{
}
