<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The full analysis of a flight (FlightAnalysis::toArray(): 1 s series, phases, task, relief...),
 * about 250 KB of JSON: in its own table, loaded only when a flight is shown or compared, not with
 * every list of flights (which read the columns and the statistics of Flight).
 */
#[ORM\Entity]
class FlightData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<string, mixed> $analysis
     */
    public function __construct(
        #[ORM\Column(type: Types::JSON)]
        private array $analysis,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalysis(): array
    {
        return $this->analysis;
    }

    /**
     * @param array<string, mixed> $analysis
     */
    public function setAnalysis(array $analysis): void
    {
        $this->analysis = $analysis;
    }
}
