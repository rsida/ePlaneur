<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Glider;
use App\Flight\Glider\CondorGliders;
use App\Repository\GliderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Records the gliders of a Condor installation (sheet and polar of each folder of Condor3/Planes),
 * or updates them: run it again after a Condor update.
 */
#[AsCommand(name: 'app:gliders:import', description: 'Import the gliders (sheet and polar) of a Condor3/Planes folder')]
final readonly class GliderImportCommand
{
    public function __construct(
        private CondorGliders $condorGliders,
        private GliderRepository $gliders,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The Planes folder of Condor 3, or a copy of it (only the .txt and .pol files are read)')]
        string $directory,
    ): int {
        if (!is_dir($directory)) {
            $io->error(\sprintf('The folder "%s" does not exist.', $directory));

            return Command::FAILURE;
        }
        $found = $this->condorGliders->read($directory);
        if ([] === $found) {
            $io->warning('No glider found: the folder should hold one folder per glider with its .txt and .pol files.');

            return Command::FAILURE;
        }
        $now = $this->clock->now();
        $created = 0;
        foreach ($found as $name => ['sheet' => $sheet, 'polar' => $polar]) {
            $glider = $this->gliders->findOneByName($name);
            if (null === $glider) {
                $glider = new Glider($name, $now);
                $this->entityManager->persist($glider);
                ++$created;
            }
            $glider->update($sheet, $polar, $now);
        }
        $this->entityManager->flush();
        $io->success(\sprintf('%d gliders read: %d new, %d updated.', \count($found), $created, \count($found) - $created));

        return Command::SUCCESS;
    }
}
