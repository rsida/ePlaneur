<?php

declare(strict_types=1);

namespace App\Push;

use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PushNotificationHandler
{
    public function __construct(
        private PushNotifier $notifier,
        private UserRepository $users,
    ) {
    }

    public function __invoke(PushNotification $notification): void
    {
        $users = [] !== $notification->userIds ? $this->users->findBy(['id' => $notification->userIds]) : [];
        $this->notifier->notify($users, $notification->kind, $notification->message);
    }
}
