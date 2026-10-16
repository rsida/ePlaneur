<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\SchoolClass;
use App\Repository\FlightRepository;

/**
 * The progression of the pupils of a class (roadmap 11f, doc/teacher-space.md): the steps of their
 * path and the flights they uploaded, nothing else of their account.
 */
final readonly class ClassProgress
{
    public function __construct(
        private PilotPath $path,
        private FlightRepository $flights,
    ) {
    }

    /**
     * @return list<PupilProgress>
     */
    public function of(SchoolClass $class): array
    {
        $rows = [];
        foreach ($class->getPupils() as $pupil) {
            $flights = $this->flights->findLogbook($pupil);
            $last = [] === $flights ? null : $flights[array_key_last($flights)]->getDate();
            $seconds = array_sum(array_map(static fn ($flight): int => $flight->getDuration(), $flights));
            $rows[] = new PupilProgress($pupil, $this->path->of($pupil), \count($flights), $seconds, $last);
        }

        return $rows;
    }
}
