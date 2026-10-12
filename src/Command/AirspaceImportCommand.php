<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Airspace;
use App\Flight\Condor\CondorAirspaces;
use App\Repository\AirspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Records the airspaces of a Condor landscape from its `.air` file, replacing those already read:
 * run it again after a Condor update. The landscape is the name of the file unless given.
 */
#[AsCommand(name: 'app:condor:airspaces', description: 'Import the airspaces of a Condor landscape (.air file)')]
final readonly class AirspaceImportCommand
{
    public function __construct(
        private AirspaceRepository $airspaces,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The .air file of the landscape (Condor3/Landscapes/<name>/<name>.air), or a copy of it')]
        string $file,
        #[Option('The landscape, as the tracks name it (default: the name of the file)')]
        ?string $landscape = null,
    ): int {
        if (!is_file($file)) {
            $io->error(\sprintf('The file "%s" does not exist.', $file));

            return Command::FAILURE;
        }
        $landscape ??= pathinfo($file, \PATHINFO_FILENAME);
        $found = CondorAirspaces::read((string) file_get_contents($file));
        if ([] === $found) {
            $io->warning('No airspace found: the landscape may have none, or it is not an OpenAir file.');

            return Command::FAILURE;
        }

        $removed = $this->airspaces->removeLandscape($landscape);
        foreach ($found as $row) {
            $this->entityManager->persist(new Airspace($landscape, $row['name'], $row['class'], $row['floor'], $row['ceiling'], $row['latitude'], $row['longitude']));
        }
        $this->entityManager->flush();
        $io->success(\sprintf('%s: %d airspaces read (%d replaced).', $landscape, \count($found), $removed));

        return Command::SUCCESS;
    }
}
