<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AirfieldRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An airfield of a Condor landscape, read from its `.apt` file (App\Flight\Condor\CondorAirfields)
 * by `app:condor:airfields`: where a glider can land, for the glide cone of the flight viewer.
 */
#[ORM\Entity(repositoryClass: AirfieldRepository::class)]
#[ORM\UniqueConstraint(name: 'airfield_landscape_name', columns: ['landscape', 'name'])]
#[ORM\Index(name: 'airfield_landscape', columns: ['landscape'])]
class Airfield
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param float $altitude metres above sea level
     * @param int   $length   metres of runway
     */
    public function __construct(
        #[ORM\Column(length: 60)]
        private string $landscape,
        #[ORM\Column(length: 60)]
        private string $name,
        #[ORM\Column]
        private float $latitude,
        #[ORM\Column]
        private float $longitude,
        #[ORM\Column]
        private float $altitude,
        #[ORM\Column]
        private int $length,
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

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getAltitude(): float
    {
        return $this->altitude;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    /** The same airfield read again (after a Condor update). */
    public function update(float $latitude, float $longitude, float $altitude, int $length): void
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->altitude = $altitude;
        $this->length = $length;
    }
}
