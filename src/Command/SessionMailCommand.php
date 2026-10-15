<?php

declare(strict_types=1);

namespace App\Command;

use App\Session\SessionMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * E-mails the reminders of tomorrow's sessions and the results of the last ones, to the accounts
 * that asked for them. Run daily (in the evening): each is sent once per session.
 */
#[AsCommand(name: 'app:sessions:mail', description: 'E-mail the reminders of tomorrow\'s sessions and the results of the last ones')]
final readonly class SessionMailCommand
{
    public function __construct(
        private SessionMailer $mailer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'List what would be sent without sending')] bool $dryRun = false,
    ): int {
        foreach (['Reminder' => $this->mailer->remind($dryRun), 'Results' => $this->mailer->results($dryRun)] as $what => ['sessions' => $sessions, 'readers' => $readers, 'devices' => $devices]) {
            foreach ($sessions as $session) {
                $io->writeln(\sprintf('%s of the session of %s (%s): %d reader(s), %d device(s)%s', $what, $session->getDate()->format('Y-m-d'), $session->getFlightPlan()->getCode(), \count($readers), \count($devices), $dryRun ? ', not sent' : ''));
            }
        }
        $io->success($dryRun ? 'Nothing sent (dry run).' : 'Done.');

        return Command::SUCCESS;
    }
}
