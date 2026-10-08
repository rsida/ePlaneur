<?php

declare(strict_types=1);

namespace App\Command;

use App\Membership\MembershipManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the renewal e-mail to the members of the season before an open season who have not renewed
 * yet, once each. Run daily: nothing happens until the next season is opened in the back-office.
 */
#[AsCommand(name: 'app:membership:remind', description: 'Remind past members to renew when a new season is open')]
final readonly class MembershipRemindCommand
{
    public function __construct(
        private MembershipManager $manager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $reminded = $this->manager->remind();
        foreach ($reminded as $membership) {
            $io->writeln(\sprintf('Reminded %s', $membership->getEmail()));
        }
        $io->success(\sprintf('%d reminder(s) sent.', \count($reminded)));

        return Command::SUCCESS;
    }
}
