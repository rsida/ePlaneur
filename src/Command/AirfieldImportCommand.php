<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Airfield;
use App\Flight\Condor\CondorAirfields;
use App\Repository\AirfieldRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Records the airfields of a Condor landscape from its `.apt` file, or updates them: run it again
 * after a Condor update. The landscape is the name of the file unless given.
 */
#[AsCommand(name: 'app:condor:airfields', description: 'Import the airfields of a Condor landscape (.apt file)')]
final readonly class AirfieldImportCommand
{
    public function __construct(
        private AirfieldRepository $airfields,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The .apt file of the landscape (Condor3/Landscapes/<name>/<name>.apt), or a copy of it')]
        string $file,
        #[Option('The landscape, as the tracks name it (default: the name of the file)')]
        ?string $landscape = null,
    ): int {
        if (!is_file($file)) {
            $io->error(\sprintf('The file "%s" does not exist.', $file));

            return Command::FAILURE;
        }
        $landscape ??= pathinfo($file, \PATHINFO_FILENAME);
        $found = CondorAirfields::read((string) file_get_contents($file));
        if ([] === $found) {
            $io->warning('No airfield found: is it the .apt file of a Condor landscape?');

            return Command::FAILURE;
        }
        $known = [];
        foreach ($this->airfields->findBy(['landscape' => $landscape]) as $airfield) {
            $known[$airfield->getName()] = $airfield;
        }
        $created = 0;
        foreach ($found as $row) {
            $airfield = $known[$row['name']] ?? null;
            if (null === $airfield) {
                $airfield = new Airfield($landscape, $row['name'], $row['latitude'], $row['longitude'], $row['altitude'], $row['length']);
                $this->entityManager->persist($airfield);
                $known[$row['name']] = $airfield;
                ++$created;
            } else {
                $airfield->update($row['latitude'], $row['longitude'], $row['altitude'], $row['length']);
            }
        }
        $this->entityManager->flush();
        $io->success(\sprintf('%s: %d airfields read, %d new, %d updated.', $landscape, \count($found), $created, \count($found) - $created));

        return Command::SUCCESS;
    }
}
