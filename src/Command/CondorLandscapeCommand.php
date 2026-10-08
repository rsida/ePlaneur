<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints the line of config/condor/landscapes.yaml for a Condor landscape, from the header of its
 * height map (Landscapes/<Name>/<Name>.trn): columns and rows (ints), cell size (float), two
 * floats, easting and northing of the south-east corner (floats), UTM zone (int), hemisphere
 * ("N" or "S"). The same header in Condor 2 and Condor 3.
 */
#[AsCommand(name: 'app:condor:landscape', description: 'Print the configuration line of a Condor landscape from its .trn file')]
final readonly class CondorLandscapeCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path of the landscape\'s <Name>.trn file (copy it into the project first: commands run in the php container)')]
        string $file,
    ): int {
        $header = is_file($file) ? (string) file_get_contents($file, false, null, 0, 33) : '';
        if (33 !== \strlen($header)) {
            $io->error(\sprintf('Cannot read %s.', $file));

            return Command::FAILURE;
        }
        $values = unpack('lcolumns/lrows/gcell/gunknown1/gunknown2/geasting/gnorthing/lzone/ahemisphere', $header);
        if (false === $values || $values['zone'] < 1 || $values['zone'] > 60 || !\in_array($values['hemisphere'], ['N', 'S'], true)) {
            $io->error('This file does not look like a Condor height map (.trn).');

            return Command::FAILURE;
        }

        $io->writeln('Add under "landscapes:" in config/condor/landscapes.yaml:');
        $io->writeln(\sprintf(
            '    %s: { utm_zone: %d, northern: %s, easting_origin: %s, northing_origin: %s }',
            basename($file, '.trn'),
            $values['zone'],
            'N' === $values['hemisphere'] ? 'true' : 'false',
            (string) round($values['easting'], 3),
            (string) round($values['northing'], 3),
        ));

        return Command::SUCCESS;
    }
}
