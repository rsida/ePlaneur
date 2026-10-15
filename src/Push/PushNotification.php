<?php

declare(strict_types=1);

namespace App\Push;

use App\Security\UserNotification;

/**
 * A notification to send later to the devices of some accounts (PushNotifier::later()), handled by
 * the messenger worker so that a page does not wait for the push services.
 */
final readonly class PushNotification
{
    /**
     * @param list<int> $userIds
     */
    public function __construct(
        public array $userIds,
        public UserNotification $kind,
        public PushMessage $message,
    ) {
    }
}
