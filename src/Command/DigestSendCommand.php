<?php

declare(strict_types=1);

namespace App\Command;

use App\Digest\MonthlyDigest;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the monthly digest of the last month (or of `--month=2026-09`) to the accounts that asked
 * for it (UserNotification::News). Run once a month, on the first day: a second run sends it again.
 */
#[AsCommand(name: 'app:digest:send', description: 'Send the monthly digest of the club to the accounts that asked for it')]
final readonly class DigestSendCommand
{
    public function __construct(
        private MonthlyDigest $digest,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Month of the digest, YYYY-MM (default: the last month)')] ?string $month = null,
        #[Option(description: 'List the readers without sending')] bool $dryRun = false,
    ): int {
        $first = null !== $month ? \DateTimeImmutable::createFromFormat('!Y-m', $month) : $this->clock->now()->modify('first day of last month');
        if (false === $first) {
            $io->error('The month must read YYYY-MM.');

            return Command::INVALID;
        }
        $readers = $this->digest->send($first, $dryRun);
        foreach ($readers as $reader) {
            $io->writeln(\sprintf('%s %s', $dryRun ? 'Would send to' : 'Sent to', $reader->getEmail()));
        }
        $io->success(\sprintf('%d digest(s) of %s %s.', \count($readers), $first->format('Y-m'), $dryRun ? 'to send' : 'sent'));

        return Command::SUCCESS;
    }
}
