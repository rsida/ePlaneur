<?php

declare(strict_types=1);

namespace App\Command;

use App\Flight\FlightImporter;
use App\Flight\Reader\InvalidTrackException;
use App\Repository\FlightRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Analyses the stored flights again from their files, after the analysis has changed (the viewer
 * reads the analysis stored on upload).
 */
#[AsCommand(name: 'app:flight:analyse', description: 'Analyse the stored flights again from their files')]
final readonly class FlightAnalyseCommand
{
    public function __construct(
        private FlightRepository $flights,
        private FlightImporter $importer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $failed = 0;
        $flights = $this->flights->findAll();
        foreach ($io->progressIterate($flights) as $flight) {
            try {
                $this->importer->reanalyse($flight);
            } catch (InvalidTrackException $exception) {
                ++$failed;
                $io->warning(\sprintf('Flight %d (%s): %s', $flight->getId(), $flight->getOriginalName(), $exception->getMessage()));
            }
            $this->entityManager->flush();
        }

        if ($failed > 0) {
            $io->error(\sprintf('%d flight(s) out of %d could not be analysed.', $failed, \count($flights)));

            return Command::FAILURE;
        }
        $io->success(\sprintf('%d flight(s) analysed.', \count($flights)));

        return Command::SUCCESS;
    }
}
