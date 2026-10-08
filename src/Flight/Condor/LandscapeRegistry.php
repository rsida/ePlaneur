<?php

declare(strict_types=1);

namespace App\Flight\Condor;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Landscapes declared in config/condor/landscapes.yaml.
 */
final class LandscapeRegistry
{
    /** @var array<string, Landscape>|null */
    private ?array $landscapes = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/condor/landscapes.yaml')]
        private readonly string $configFile,
    ) {
    }

    public function find(string $name): ?Landscape
    {
        return $this->all()[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, Landscape> by lower-case name
     */
    public function all(): array
    {
        if (null === $this->landscapes) {
            /** @var array{landscapes: array<string, array{utm_zone: int, northern: bool, easting_origin: float|int, northing_origin: float|int}>} $config */
            $config = Yaml::parseFile($this->configFile);
            $this->landscapes = [];
            foreach ($config['landscapes'] as $name => $values) {
                $this->landscapes[strtolower($name)] = new Landscape($name, $values['utm_zone'], $values['northern'], (float) $values['easting_origin'], (float) $values['northing_origin']);
            }
        }

        return $this->landscapes;
    }
}
