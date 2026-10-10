<?php

declare(strict_types=1);

namespace App\Flight\Glider;

/**
 * Reads the gliders of a Condor installation: each folder of Condor3/Planes holding a sheet
 * (<name>.txt) and a polar (<name>.pol). Tow planes and the gliders without a polar are left out.
 *
 * The sheet is the text Condor shows when choosing a glider: the maker and model on the first
 * line, then one line per figure, "Label, metric, imperial, mixed" ("Wing span, 15 m, 49.21 ft,
 * 15 m"). Labels change a little from one glider to another ("Empty mass" or "Empty weight", a
 * single "Wing loading" without water ballast, engine lines on motor gliders).
 */
final readonly class CondorGliders
{
    /** Labels of the sheet (lower case) and what they give. */
    private const array FIELDS = [
        'wing span' => 'span',
        'wing area' => 'wingArea',
        'length' => 'length',
        'empty weight' => 'emptyMass',
        'empty mass' => 'emptyMass',
        'max weight' => 'maxMass',
        'max mass' => 'maxMass',
        'water ballast' => 'waterBallast',
        'min wing loading' => 'minWingLoading',
        'max wing loading' => 'maxWingLoading',
        'wing loading' => 'wingLoading',
        'max speed' => 'maxSpeed',
        'maneuvering speed' => 'maneuveringSpeed',
        'min speed' => 'minSpeed',
        'min sink' => 'minSink',
        'best glide ratio' => 'bestGlide',
        'daec index' => 'handicap',
    ];

    /** Kilograms of a pilot with parachute, for the sheets without a minimum wing loading. */
    private const float PILOT_MASS = 90.0;

    /** Engine lines, in French for the sheet of the site. */
    private const array ENGINE = [
        'engine thrust' => 'poussée',
        'engine power' => 'puissance',
        'rate of climb' => 'montée',
        'fuel capacity' => 'carburant',
    ];

    /**
     * Every glider of a Planes folder, by name.
     *
     * @return array<string, array{sheet: array{model: string, span: float, wingArea: float, length: ?float, emptyMass: float, maxMass: float, waterBallast: int, minWingLoading: float, maxWingLoading: float, maxSpeed: float, maneuveringSpeed: ?float, minSpeed: ?float, minSink: ?float, bestGlide: ?float, bestGlideSpeed: ?float, handicap: ?int, engine: ?string}, polar: Polar}>
     */
    public function read(string $directory): array
    {
        $gliders = [];
        foreach (glob(rtrim($directory, '/').'/*', \GLOB_ONLYDIR) ?: [] as $folder) {
            $name = basename($folder);
            $sheet = @file_get_contents($folder.'/'.$name.'.txt');
            $polar = @file_get_contents($folder.'/'.$name.'.pol');
            if (false === $sheet || false === $polar) {
                continue;
            }
            $sheet = self::sheet($sheet);
            $polar = Polar::fromCondor($polar);
            if (null !== $sheet && null !== $polar) {
                $gliders[$name] = ['sheet' => $sheet, 'polar' => $polar];
            }
        }
        ksort($gliders, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $gliders;
    }

    /**
     * The figures of a sheet, null when it lacks the ones the site needs (span, area, masses, wing
     * loading, maximum speed).
     *
     * @return array{model: string, span: float, wingArea: float, length: ?float, emptyMass: float, maxMass: float, waterBallast: int, minWingLoading: float, maxWingLoading: float, maxSpeed: float, maneuveringSpeed: ?float, minSpeed: ?float, minSink: ?float, bestGlide: ?float, bestGlideSpeed: ?float, handicap: ?int, engine: ?string}|null
     */
    public static function sheet(string $content): ?array
    {
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = array_values(array_filter(array_map(trim(...), preg_split('/\R/', $content) ?: []), static fn (string $line): bool => '' !== $line));
        if ([] === $lines) {
            return null;
        }
        $values = [];
        $engine = [];
        $bestGlideSpeed = null;
        foreach (\array_slice($lines, 1) as $line) {
            $parts = array_map(trim(...), explode(',', $line));
            $label = strtolower($parts[0]);
            $value = $parts[1] ?? '';
            if (isset(self::ENGINE[$label])) {
                $engine[] = self::ENGINE[$label].' '.$value;
                continue;
            }
            if (!isset(self::FIELDS[$label]) || !preg_match('/-?\d+(?:\.\d+)?/', $value, $number)) {
                continue;
            }
            $values[self::FIELDS[$label]] = (float) $number[0];
            if ('best glide ratio' === $label && preg_match('/at\s+(\d+(?:\.\d+)?)\s*km\/h/i', $value, $speed)) {
                $bestGlideSpeed = (float) $speed[1];
            }
        }
        foreach (['span', 'wingArea', 'emptyMass', 'maxMass', 'maxSpeed'] as $required) {
            if (!isset($values[$required]) || $values[$required] <= 0) {
                return null;
            }
        }
        // A few sheets give no minimum wing loading (Genesis 2, Pilatus B4...): empty with a pilot
        $minWingLoading = $values['minWingLoading'] ?? $values['wingLoading'] ?? ($values['emptyMass'] + self::PILOT_MASS) / $values['wingArea'];
        $handicap = $values['handicap'] ?? null;
        $maxWingLoading = $values['maxWingLoading'] ?? $values['maxMass'] / $values['wingArea'];

        return [
            'model' => mb_substr(self::model($lines[0]), 0, 120),
            'span' => $values['span'],
            'wingArea' => $values['wingArea'],
            'length' => $values['length'] ?? null,
            'emptyMass' => $values['emptyMass'],
            'maxMass' => $values['maxMass'],
            'waterBallast' => (int) round($values['waterBallast'] ?? 0),
            'minWingLoading' => round($minWingLoading, 1),
            'maxWingLoading' => round(max($minWingLoading, $maxWingLoading), 1),
            'maxSpeed' => $values['maxSpeed'],
            'maneuveringSpeed' => $values['maneuveringSpeed'] ?? null,
            'minSpeed' => $values['minSpeed'] ?? null,
            'minSink' => $values['minSink'] ?? null,
            'bestGlide' => $values['bestGlide'] ?? null,
            'bestGlideSpeed' => $bestGlideSpeed,
            'handicap' => null !== $handicap ? (int) $handicap : null,
            'engine' => [] !== $engine ? mb_substr(ucfirst(implode(', ', $engine)), 0, 120) : null,
        ];
    }

    /** "ALEXANDER SCHLEICHER ASK21" reads "Alexander Schleicher ASK21"; mixed case is kept. */
    private static function model(string $line): string
    {
        return implode(' ', array_map(
            static fn (string $word): string => preg_match('/^[A-Z]{4,}$/', $word) ? ucfirst(strtolower($word)) : $word,
            explode(' ', preg_replace('/\s+/', ' ', $line) ?? $line),
        ));
    }
}
