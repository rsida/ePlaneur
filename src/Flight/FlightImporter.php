<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Reader\InvalidTrackException;
use App\Flight\Reader\TrackReader;
use App\Session\SessionLinker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Adds a flight track to the flight viewer: reads it, analyses it and keeps the file (to analyse it
 * again when the analysis improves).
 */
final readonly class FlightImporter
{
    public function __construct(
        private TrackReader $reader,
        private FlightAnalyzer $analyzer,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private SessionLinker $linker,
        #[Autowire('%app.flights_dir%')]
        private string $directory,
    ) {
    }

    /**
     * @throws InvalidTrackException when the file is not a readable flight track
     */
    public function import(string $path, string $originalName, ?User $owner): Flight
    {
        $content = (string) file_get_contents($path);
        $analysis = $this->analyzer->analyze($this->reader->read($content));

        $extension = str_starts_with($content, 'FTR') ? 'ftr' : 'igc';
        $fileName = bin2hex(random_bytes(16)).'.'.$extension;
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->directory);
        $filesystem->copy($path, $this->directory.'/'.$fileName);

        $flight = new Flight($analysis, $fileName, $originalName, $owner, $this->clock->now());
        // The pilot's choice in "Confidentialité et données"
        $flight->setPublic($owner?->isFlightsPublicByDefault() ?? false);
        $this->linker->link($flight);
        $this->entityManager->persist($flight);
        $this->entityManager->flush();

        return $flight;
    }

    /**
     * Reads the stored file again with the current analysis.
     *
     * @throws InvalidTrackException when the file is missing or can no longer be read
     */
    public function reanalyse(Flight $flight): void
    {
        $path = $this->pathOf($flight);
        if ('' === $flight->getFileName() || !is_file($path)) {
            throw new InvalidTrackException(\sprintf('The file %s is missing.', $path));
        }
        $flight->setAnalysis($this->analyzer->analyze($this->reader->read((string) file_get_contents($path))));
    }

    /** Removes the stored file of a flight (deleted, or anonymised: the file holds the pilot's name). */
    public function removeFile(Flight $flight): void
    {
        if ('' !== $flight->getFileName()) {
            (new Filesystem())->remove($this->pathOf($flight));
        }
    }

    public function pathOf(Flight $flight): string
    {
        return $this->directory.'/'.$flight->getFileName();
    }

    public function delete(Flight $flight): void
    {
        $this->removeFile($flight);
        $this->entityManager->remove($flight);
        $this->entityManager->flush();
    }
}
