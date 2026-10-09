<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\NetworkSession;
use App\Session\SessionImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports the network sessions published on eplaneur.fr (history since 2020, then the new ones
 * while they are still published there). Safe to run again: imported sessions are kept.
 */
#[AsCommand(name: 'app:sessions:import', description: 'Import the network sessions and their results from eplaneur.fr')]
final readonly class SessionImportCommand
{
    public function __construct(
        private SessionImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only the sessions from this day (YYYY-MM-DD)')] ?string $since = null,
        #[Option('Do not read the results table of each session')] bool $noResults = false,
        #[Option('Seconds to wait between two pages of eplaneur.fr')] float $delay = 0.5,
    ): int {
        $from = null;
        if (null !== $since) {
            $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $since);
            if (false === $from) {
                $io->error('--since expects a date like 2026-01-31.');

                return Command::INVALID;
            }
        }

        $report = $this->importer->import($from, !$noResults, $delay, static function (NetworkSession $session, string $state) use ($io): void {
            if ($io->isVerbose()) {
                $io->writeln(\sprintf('%s %s %s (%d pilots, %d tracks)', $state, $session->getDate()->format('Y-m-d'), $session->getFlightPlan()->getCode(), $session->getParticipantCount(), $session->getFlights()->count()));
            }
        });
        foreach ($report['failed'] as $failure) {
            $io->warning($failure);
        }
        $io->success(\sprintf('%d session(s) created, %d updated, %d already there.', $report['created'], $report['updated'], $report['kept']));

        return [] === $report['failed'] ? Command::SUCCESS : Command::FAILURE;
    }
}
