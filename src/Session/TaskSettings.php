<?php

declare(strict_types=1);

namespace App\Session;

use App\Flight\Analysis\Launch;

/**
 * The settings of a Condor task: date and time, start, penalties, realism options and weather, read
 * from its flight plan (.fpl, an INI file Condor also writes next to the results of each race) or
 * from its Condor Club page (`https://condor.club/api/task/<id>`, the same values as JSON).
 *
 * Read on the C3-s117 race of 2026-03-05 against its Condor Club page: TaskDate is a Delphi date
 * (days since 1899-12-30); StartTime is in hours, StartTimeWindow and RaceStartDelay in hours too
 * (a window of 0 is a regatta start); StartType 0 is an aerotow, 2 an airborne start; the finish
 * minimum height is the TPWidth of the finish (an altitude), the start maximum height the TPHeight
 * of the start; wind speeds are in m/s in the .fpl (km/h on Condor Club); the cloud base above the
 * ground is 125 m per degree between temperature and dew point.
 */
final readonly class TaskSettings
{
    /** Penalty settings, in the order Condor Club lists them, with their French label. */
    public const array PENALTIES = [
        'CloudFlying' => 'Vol dans les nuages (par minute)',
        'PlaneRecovery' => 'Réparation du planeur',
        'HeightRecovery' => 'Récupération d’altitude',
        'WrongWindowEnterance' => 'Mauvais passage de fenêtre',
        'WindowCollision' => 'Collision avec une fenêtre',
        'AirspaceEnterance' => 'Pénétration d’espace aérien',
        'PenaltyZoneEnterance' => 'Pénétration de zone interdite',
        'ThermalHelpers' => 'Visualisation des thermiques (par minute)',
        'StartSpeed' => 'Vitesse excessive au départ (par km/h)',
        'HighStart' => 'Départ trop haut (par mètre)',
        'LowFinish' => 'Arrivée trop basse (par mètre)',
    ];

    /** Realism options that are allowed or not, with their French label. */
    public const array OPTIONS = [
        'Bugwipers' => 'Essuie-insectes',
        'PDA' => 'PDA',
        'RealtimeScoring' => 'Score en temps réel',
        'ExternalView' => 'Vue externe',
        'PadlockView' => 'Vue verrouillée',
        'Smoke' => 'Fumigène',
        'PlaneRecovery' => 'Réparation du planeur',
        'HeightRecovery' => 'Récupération d’altitude',
        'MidairCollisionRecovery' => 'Réparation après collision en vol',
        'InstructorActions' => 'Actions instructeur',
    ];

    /**
     * @param array<string, int>                                                                                                                                               $penalties keys of PENALTIES → points
     * @param array<string, bool>                                                                                                                                              $options   keys of OPTIONS → allowed
     * @param list<array{name: string, windDirection: float, windSpeed: float, thermalStrength: int, thermalActivity: int, cloudBase: ?int, inversion: ?int, highClouds: int}> $weather   zones, the base one first; wind in km/h, heights in metres
     */
    public function __construct(
        public string $source,
        public ?string $name,
        public ?\DateTimeImmutable $date,
        public ?int $startTime,
        public int $startWindow,
        public int $startDelay,
        public ?Launch $launch,
        public ?int $startHeight,
        public ?int $startMaxHeight,
        public ?int $finishMinHeight,
        public ?int $maxStartSpeed,
        public ?float $maxWingLoading,
        public array $penalties,
        public array $options,
        public ?int $iconsRange,
        public ?float $thermalHelpersRange,
        public ?float $turnpointHelpersRange,
        public array $weather,
    ) {
    }

    /**
     * A Condor flight plan (.fpl); source "igc" when it comes from the IGC export of a flight.
     *
     * @throws \InvalidArgumentException when the text is not a flight plan
     */
    public static function fromFpl(string $content, string $source = 'fpl'): self
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $ini = @parse_ini_string($content, true, \INI_SCANNER_RAW);
        if (!\is_array($ini) || !isset($ini['Task'], $ini['GameOptions'])) {
            throw new \InvalidArgumentException('This is not a Condor flight plan.');
        }
        $task = $ini['Task'];
        $options = $ini['GameOptions'];
        $last = max(0, (int) ($task['Count'] ?? 0) - 1);
        $zones = [];
        for ($k = 0; $k < (int) ($ini['Weather']['WZCount'] ?? 0); ++$k) {
            $zone = $ini['WeatherZone'.$k] ?? null;
            if (!\is_array($zone)) {
                continue;
            }
            $zones[] = self::zone(
                (string) ($zone['Name'] ?? ''),
                (float) ($zone['WindDir'] ?? 0),
                (float) ($zone['WindSpeed'] ?? 0) * 3.6,
                (int) ($zone['ThermalsStrength'] ?? 0),
                (int) ($zone['ThermalsActivity'] ?? 0),
                isset($zone['ThermalsTemp'], $zone['ThermalsDew']) ? ((float) $zone['ThermalsTemp'] - (float) $zone['ThermalsDew']) * 125 : null,
                isset($zone['ThermalsInversionheight']) ? (float) $zone['ThermalsInversionheight'] : null,
                (int) ($zone['HighCloudsCoverage'] ?? 0),
            );
        }

        return self::build($source, (string) ($ini['Description']['Text'] ?? '') ?: null, $options, (int) ($task['TPWidth'.$last] ?? 0), (int) ($task['TPHeight1'] ?? 0), $zones);
    }

    /**
     * The JSON of a Condor Club task (`/api/task/<id>`).
     *
     * @param array<string, mixed> $task
     *
     * @throws \InvalidArgumentException when the JSON is not a task
     */
    public static function fromCondorClub(array $task): self
    {
        if (!isset($task['gameOptions']) || !\is_array($task['gameOptions'])) {
            throw new \InvalidArgumentException('This is not a Condor Club task.');
        }
        $options = [];
        foreach ($task['gameOptions'] as $key => $value) {
            $options[ucfirst((string) $key)] = $value;
        }
        // Condor Club spells a few names differently
        $options['AllowPDA'] = $task['gameOptions']['allowPDA'] ?? null;
        $options['MaxTeams'] = $task['gameOptions']['maxTeam'] ?? null;
        $turnpoints = \is_array($task['turnPoints'] ?? null) ? array_values($task['turnPoints']) : [];
        $finish = $turnpoints[array_key_last($turnpoints) ?? 0] ?? [];
        $start = $turnpoints[1] ?? [];
        $zones = [];
        foreach ($task['weather']['weatherZones'] ?? [] as $zone) {
            if (!\is_array($zone)) {
                continue;
            }
            $thermals = $zone['thermals'] ?? [];
            $zones[] = self::zone(
                (string) ($zone['name'] ?? ''),
                (float) ($zone['wind']['fDir'] ?? 0),
                (float) ($zone['wind']['fSpeed'] ?? 0),
                (int) ($thermals['iStrength'] ?? 0),
                (int) ($thermals['iActivity'] ?? 0),
                isset($thermals['heightCloudBase']) ? (float) $thermals['heightCloudBase'] : null,
                isset($thermals['fInversionHeight']) ? (float) $thermals['fInversionHeight'] : null,
                (int) ($zone['highClouds']['iCoverage'] ?? 0),
            );
        }

        return self::build('condor.club', (string) ($task['name'] ?? '') ?: null, $options, (int) ($finish['winWidth'] ?? 0), (int) ($start['winHeight'] ?? 0), $zones);
    }

    /**
     * @param array<string, mixed> $stored toArray()
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            (string) $stored['source'],
            $stored['name'] ?? null,
            isset($stored['date']) ? new \DateTimeImmutable($stored['date']) : null,
            $stored['startTime'] ?? null,
            (int) ($stored['startWindow'] ?? 0),
            (int) ($stored['startDelay'] ?? 0),
            isset($stored['launch']) ? Launch::tryFrom($stored['launch']) : null,
            $stored['startHeight'] ?? null,
            $stored['startMaxHeight'] ?? null,
            $stored['finishMinHeight'] ?? null,
            $stored['maxStartSpeed'] ?? null,
            $stored['maxWingLoading'] ?? null,
            $stored['penalties'] ?? [],
            $stored['options'] ?? [],
            $stored['iconsRange'] ?? null,
            $stored['thermalHelpersRange'] ?? null,
            $stored['turnpointHelpersRange'] ?? null,
            $stored['weather'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'name' => $this->name,
            'date' => $this->date?->format('Y-m-d'),
            'startTime' => $this->startTime,
            'startWindow' => $this->startWindow,
            'startDelay' => $this->startDelay,
            'launch' => $this->launch?->value,
            'startHeight' => $this->startHeight,
            'startMaxHeight' => $this->startMaxHeight,
            'finishMinHeight' => $this->finishMinHeight,
            'maxStartSpeed' => $this->maxStartSpeed,
            'maxWingLoading' => $this->maxWingLoading,
            'penalties' => $this->penalties,
            'options' => $this->options,
            'iconsRange' => $this->iconsRange,
            'thermalHelpersRange' => $this->thermalHelpersRange,
            'turnpointHelpersRange' => $this->turnpointHelpersRange,
            'weather' => $this->weather,
        ];
    }

    /** A regatta start: everybody starts when the gate opens, and the task time counts from it. */
    public function isRegatta(): bool
    {
        return 0 === $this->startWindow;
    }

    /** Minutes after midnight when the gate opens (Condor time). */
    public function gateOpening(): ?int
    {
        return null !== $this->startTime ? $this->startTime + $this->startDelay : null;
    }

    /** French label of a thermal strength of Condor (2 is "Faible" on Condor Club). */
    public static function strengthLabel(int $value): string
    {
        return ['Très faible', 'Très faible', 'Faible', 'Normale', 'Forte', 'Très forte'][$value] ?? (string) $value;
    }

    /** French label of a thermal activity of Condor (3 is "Faible", 4 "Normale" on Condor Club). */
    public static function activityLabel(int $value): string
    {
        return ['Très faible', 'Très faible', 'Très faible', 'Faible', 'Normale', 'Forte', 'Très forte'][$value] ?? (string) $value;
    }

    /** Season of the task date, as Condor Club names it. */
    public function season(): ?string
    {
        if (null === $this->date) {
            return null;
        }

        return match ((int) $this->date->format('n')) {
            3, 4, 5 => 'Printemps',
            6, 7, 8 => 'Été',
            9, 10, 11 => 'Automne',
            default => 'Hiver',
        };
    }

    /** Compass point of a wind direction: "Sud", "Nord-Ouest"... */
    public static function compass(float $degrees): string
    {
        $points = ['Nord', 'Nord-Est', 'Est', 'Sud-Est', 'Sud', 'Sud-Ouest', 'Ouest', 'Nord-Ouest'];

        return $points[(int) round(fmod($degrees + 360, 360) / 45) % 8];
    }

    /**
     * @param array<string, mixed>                                                                                                                                             $options GameOptions of the .fpl (Condor Club keys capitalised)
     * @param list<array{name: string, windDirection: float, windSpeed: float, thermalStrength: int, thermalActivity: int, cloudBase: ?int, inversion: ?int, highClouds: int}> $weather
     */
    private static function build(string $source, ?string $name, array $options, int $finishMinHeight, int $startMaxHeight, array $weather): self
    {
        $number = static fn (string $key): ?float => isset($options[$key]) && is_numeric($options[$key]) ? (float) $options[$key] : null;
        $penalties = [];
        foreach (array_keys(self::PENALTIES) as $key) {
            if (null !== ($value = $number('Penalty'.$key))) {
                $penalties[$key] = (int) round($value);
            }
        }
        $allowed = [];
        foreach (array_keys(self::OPTIONS) as $key) {
            if (null !== ($value = $number('Allow'.$key))) {
                $allowed[$key] = 0.0 !== $value;
            }
        }
        $date = $number('TaskDate');
        $startTime = $number('StartTime');

        return new self(
            $source,
            null !== $name ? mb_substr(trim($name), 0, 200) : null,
            null !== $date && $date > 0 ? new \DateTimeImmutable('1899-12-30')->modify('+'.(int) $date.' days') : null,
            null !== $startTime ? (int) round($startTime * 60) : null,
            (int) round(($number('StartTimeWindow') ?? 0) * 60),
            (int) round(($number('RaceStartDelay') ?? 0) * 60),
            match ((int) ($number('StartType') ?? -1)) {
                0 => Launch::Aerotow,
                1 => Launch::Winch,
                2 => Launch::Airborne,
                default => null,
            },
            null !== ($height = $number('StartHeight')) ? (int) $height : null,
            $startMaxHeight > 0 ? $startMaxHeight : null,
            $finishMinHeight > 0 ? $finishMinHeight : null,
            null !== ($speed = $number('MaxStartGroundSpeed')) && $speed > 0 ? (int) $speed : null,
            $number('MaxWingLoading'),
            $penalties,
            $allowed,
            null !== ($range = $number('IconsVisibleRange')) ? (int) $range : null,
            $number('ThermalHelpersRange'),
            $number('TurnpointHelpersRange'),
            $weather,
        );
    }

    /**
     * @return array{name: string, windDirection: float, windSpeed: float, thermalStrength: int, thermalActivity: int, cloudBase: ?int, inversion: ?int, highClouds: int}
     */
    private static function zone(string $name, float $windDirection, float $windSpeed, int $strength, int $activity, ?float $cloudBase, ?float $inversion, int $highClouds): array
    {
        return [
            'name' => mb_substr($name, 0, 60),
            'windDirection' => round($windDirection),
            'windSpeed' => round($windSpeed, 1),
            'thermalStrength' => $strength,
            'thermalActivity' => $activity,
            'cloudBase' => null !== $cloudBase ? (int) round($cloudBase) : null,
            'inversion' => null !== $inversion ? (int) round($inversion) : null,
            'highClouds' => $highClouds,
        ];
    }
}
