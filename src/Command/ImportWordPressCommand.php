<?php

declare(strict_types=1);

namespace App\Command;

use App\WordPress\WordPressImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-wordpress', description: 'Import the pages, posts and files of the WordPress site (club.eplaneur.fr), with redirects of the old addresses')]
final readonly class ImportWordPressCommand
{
    public function __construct(
        private WordPressImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Read and convert everything, download and save nothing')]
        bool $dryRun = false,
        #[Option('Import only "pages" or "posts"')]
        ?string $only = null,
    ): int {
        if (null !== $only && !\in_array($only, ['pages', 'posts'], true)) {
            $io->error('--only accepts "pages" or "posts".');

            return Command::INVALID;
        }

        $io->title($dryRun ? 'WordPress import (dry run: nothing is saved)' : 'WordPress import');
        $report = $this->importer->import($only, $dryRun);

        $io->table(['', 'Count'], array_map(null, array_keys($report->counts()), array_values($report->counts())));
        if ([] !== $report->warnings()) {
            $io->section(\sprintf('To review (%d)', \count($report->warnings())));
            $io->listing($report->warnings());
        }
        $io->success($dryRun ? 'Dry run done: run again without --dry-run to import.' : 'Import done.');

        return Command::SUCCESS;
    }
}
