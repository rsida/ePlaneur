<?php

declare(strict_types=1);

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Makes the VAPID keys of the notifications (doc/installable-site.md#notifications). Changing them
 * later ends every subscription: the members turn the notifications on again.
 */
#[AsCommand(name: 'app:push:keys', description: 'Make a pair of VAPID keys for the notifications of the installable site')]
final class PushKeysCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $keys = VAPID::createVapidKeys();
        $io->writeln([
            'VAPID_PUBLIC_KEY='.$keys['publicKey'],
            'VAPID_PRIVATE_KEY='.$keys['privateKey'],
        ]);
        $io->note('Put both lines in .env.local (the private key is a secret). Changing them ends every subscription.');

        return Command::SUCCESS;
    }
}
