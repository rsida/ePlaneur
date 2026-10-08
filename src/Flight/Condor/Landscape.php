<?php

declare(strict_types=1);

namespace App\Flight\Condor;

use App\Flight\Geo\Utm;

/**
 * A Condor landscape and its projection: Condor positions are metres in a UTM zone, x towards the
 * west and y towards the north from the south-east corner of the landscape.
 */
final readonly class Landscape
{
    public function __construct(
        public string $name,
        public int $utmZone,
        public bool $northern,
        public float $eastingOrigin,
        public float $northingOrigin,
    ) {
    }

    /**
     * @return array{float, float} latitude and longitude in degrees (WGS 84)
     */
    public function toLatLon(float $x, float $y): array
    {
        return Utm::toLatLon($this->eastingOrigin - $x, $this->northingOrigin + $y, $this->utmZone, $this->northern);
    }
}
