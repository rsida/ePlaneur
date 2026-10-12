<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Airfield;
use App\Entity\Airspace;
use App\Flight\Condor\Landscape;
use App\Flight\Condor\LandscapeRegistry;
use App\Repository\AirfieldRepository;
use App\Repository\AirspaceRepository;
use App\Repository\GliderRepository;

/**
 * Data of the flight plan workshop (/plans-de-vol/atelier): the landscapes whose airfields were read,
 * their regions, airfields and airspaces, and the gliders by class. The page composes from them the
 * instructions given to Claude to write a Condor 3 flight plan.
 */
final readonly class FlightPlanWorkshop
{
    /**
     * Regions of a landscape: name, then south, north, west and east limits (degrees). An airfield or
     * an airspace belongs to the first region holding it, to "Autres" otherwise.
     *
     * @var array<string, list<array{0: string, 1: float, 2: float, 3: float, 4: float}>>
     */
    private const array REGIONS = [
        'aa3' => [
            ['Provence et Côte d’Azur', 43.3, 44.15, 4.5, 7.9],
            ['Alpes du Sud', 44.15, 45.0, 4.5, 7.1],
            ['Alpes du Nord', 45.0, 46.45, 4.5, 7.0],
            ['Piémont et Val d’Aoste', 44.0, 46.0, 7.0, 9.0],
            ['Suisse romande et Valais', 46.0, 47.0, 6.0, 8.4],
            ['Jura et Bourgogne', 45.9, 48.4, 4.5, 7.5],
            ['Suisse alémanique et Grisons', 46.2, 48.4, 7.5, 10.5],
            ['Lombardie et Trentin', 45.0, 46.6, 9.0, 12.0],
            ['Dolomites et Frioul', 45.5, 46.8, 11.0, 14.0],
            ['Tyrol et Bavière', 46.6, 48.4, 10.0, 13.0],
            ['Autriche orientale', 46.6, 48.4, 13.0, 16.4],
            ['Slovénie', 45.3, 46.6, 13.5, 16.4],
        ],
        'slovenia3' => [
            ['Slovénie occidentale', 45.3, 46.6, 13.0, 14.8],
            ['Slovénie orientale', 45.3, 46.6, 14.8, 16.7],
            ['Carinthie et Styrie', 46.6, 48.0, 12.4, 16.7],
            ['Frioul', 45.5, 46.6, 12.4, 13.0],
        ],
        'bigpyrenees2' => [
            ['Pyrénées occidentales', 42.3, 43.6, -1.9, 0.0],
            ['Pyrénées centrales', 42.3, 43.6, 0.0, 1.6],
            ['Pyrénées orientales', 42.3, 43.6, 1.6, 3.2],
        ],
    ];

    private const string OTHER_REGION = 'Autres';

    /**
     * Classes of the gliders of Condor 3, as the club groups them; a glider not listed is in "Autres".
     *
     * @var array<string, list<string>>
     */
    private const array CLASSES = [
        'Écolage et anciens' => ['SG38', 'GrunauBaby', 'SGS1-26', 'K8', 'Ka6CR', 'ASK13', 'Blanik', 'SF28', 'SwiftS1'],
        'Club' => ['PilatusB4', 'AstirCS', 'ASW15', 'ASW19', 'StdCirrus', 'Libelle', 'JantarStd2', 'Pegase', 'DG101G', 'LS3a', 'ASW20', 'MiniLAKFES'],
        'Standard' => ['Discus2a', 'LS4a', 'LS8neo', 'ASW28-15', 'Genesis2'],
        '15 mètres' => ['Diana2', 'JS3-15', 'Ventus3-15', 'AS33Me-15', 'DG808C-15'],
        '18 mètres' => ['JS1-18', 'JS3-18', 'ASG29-18', 'ASG29Es-18', 'Ventus3-18', 'AS33Me-18', 'DG808C-18', 'Shark304ES', 'Antares18S'],
        'Classe libre' => ['EB29R', 'JS1-21', 'JS5-24'],
        'Biplaces' => ['ASK21', 'Puchacz', 'DG1000S', 'DuoDiscus', 'DuoDiscusFES', 'Arcus', 'ASG32Mi', 'StemmeS12'],
    ];

    public function __construct(
        private LandscapeRegistry $landscapes,
        private AirfieldRepository $airfields,
        private AirspaceRepository $airspaces,
        private GliderRepository $gliders,
    ) {
    }

    /**
     * The landscapes the workshop offers: declared and with their airfields read.
     *
     * @return list<string>
     */
    public function landscapes(): array
    {
        $read = array_map('strtolower', $this->airfields->landscapes());

        return array_values(array_map(
            static fn (Landscape $landscape): string => $landscape->name,
            array_filter($this->landscapes->all(), static fn (Landscape $landscape): bool => \in_array(strtolower($landscape->name), $read, true)),
        ));
    }

    /** Whether the workshop offers this landscape (name as declared). */
    public function offers(string $landscape): bool
    {
        return \in_array($landscape, $this->landscapes(), true);
    }

    /**
     * Regions, airfields ([name, altitude, runway length, region]) and airspaces ([name, class,
     * floor, ceiling, region]) of a landscape.
     *
     * @return array{regions: list<string>, airfields: list<array{0: string, 1: int, 2: int, 3: string}>, airspaces: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>}
     */
    public function landscape(string $landscape): array
    {
        $boxes = self::REGIONS[strtolower($landscape)] ?? [];
        $region = static function (float $latitude, float $longitude) use ($boxes): string {
            foreach ($boxes as [$name, $south, $north, $west, $east]) {
                if ($latitude >= $south && $latitude < $north && $longitude >= $west && $longitude < $east) {
                    return $name;
                }
            }

            return self::OTHER_REGION;
        };

        $airfields = array_map(static fn (Airfield $airfield): array => [
            $airfield->getName(),
            (int) round($airfield->getAltitude()),
            $airfield->getLength(),
            $region($airfield->getLatitude(), $airfield->getLongitude()),
        ], $this->airfields->findBy(['landscape' => $landscape], ['name' => 'ASC']));
        $airspaces = array_map(static fn (Airspace $airspace): array => [
            $airspace->getName(),
            $airspace->getCategory(),
            $airspace->getFloor(),
            $airspace->getCeiling(),
            $region($airspace->getLatitude(), $airspace->getLongitude()),
        ], $this->airspaces->findBy(['landscape' => $landscape], ['name' => 'ASC']));

        // Regions in their order, those with an airfield only
        $used = array_column($airfields, 3);
        $regions = array_values(array_filter(
            [...array_column($boxes, 0), self::OTHER_REGION],
            static fn (string $name): bool => \in_array($name, $used, true),
        ));

        return ['regions' => $regions, 'airfields' => $airfields, 'airspaces' => $airspaces];
    }

    /**
     * Gliders by class: [name, span in metres, water ballast in kg].
     *
     * @return array<string, list<array{0: string, 1: float, 2: int}>>
     */
    public function gliders(): array
    {
        $classOf = [];
        foreach (self::CLASSES as $class => $names) {
            foreach ($names as $name) {
                $classOf[$name] = $class;
            }
        }
        $byClass = array_fill_keys(array_keys(self::CLASSES), []);
        foreach ($this->gliders->findBy([], ['name' => 'ASC']) as $glider) {
            $byClass[$classOf[$glider->getName()] ?? 'Autres'][] = [$glider->getName(), $glider->getSpan(), $glider->getWaterBallast()];
        }

        return array_filter($byClass, static fn (array $gliders): bool => [] !== $gliders);
    }
}
