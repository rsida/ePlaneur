<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AirspaceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An airspace of a Condor landscape, read from its `.air` file (App\Flight\Condor\CondorAirspaces)
 * by `app:condor:airspaces`: offered by the flight plan workshop, to leave active or to disable.
 * Several airspaces may share a name (the parts of a zone).
 */
#[ORM\Entity(repositoryClass: AirspaceRepository::class)]
#[ORM\Index(name: 'airspace_landscape', columns: ['landscape'])]
class Airspace
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param string $category class of the airspace ("D", "UNCLASSIFIED", "CTR"...)
     * @param string $floor    lower limit as written in the file ("3300FT AGL", "GND")
     * @param string $ceiling  upper limit as written in the file ("FL195")
     * @param float  $latitude centre of its outline, degrees
     */
    public function __construct(
        #[ORM\Column(length: 60)]
        private string $landscape,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 30)]
        private string $category,
        #[ORM\Column(length: 30)]
        private string $floor,
        #[ORM\Column(length: 30)]
        private string $ceiling,
        #[ORM\Column]
        private float $latitude,
        #[ORM\Column]
        private float $longitude,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLandscape(): string
    {
        return $this->landscape;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getFloor(): string
    {
        return $this->floor;
    }

    public function getCeiling(): string
    {
        return $this->ceiling;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }
}
