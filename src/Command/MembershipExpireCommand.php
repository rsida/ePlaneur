<?php

declare(strict_types=1);

namespace App\Command;

use App\Membership\MembershipManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the "Membre" group from the accounts whose membership recorded on the site has ended (no
 * validated membership for the current year; members of the past year keep it during January).
 * Accounts made members by hand, without any membership on the site, are left alone. Run daily.
 */
#[AsCommand(name: 'app:membership:expire', description: 'Remove the "Membre" group from accounts whose membership has ended')]
final readonly class MembershipExpireCommand
{
    public function __construct(
        private MembershipManager $manager,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('List the accounts without changing anything')] bool $dryRun = false): int
    {
        $removed = $this->manager->expire($dryRun);
        foreach ($removed as $user) {
            $io->writeln(\sprintf('%s %s', $dryRun ? 'Would remove' : 'Removed', $user->getEmail()));
        }
        $io->success(\sprintf('%d account(s) %s the "Membre" group.', \count($removed), $dryRun ? 'would leave' : 'left'));

        return Command::SUCCESS;
    }
}
