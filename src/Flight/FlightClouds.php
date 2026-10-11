<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;
use App\Flight\Condor\LandscapeRegistry;
use App\Session\TaskSettings;

/**
 * The cumulus of the thermals of a flight, for the 3D replay: one cloud over each thermal climbed,
 * at the cloud base of its weather zone, as Condor forms them.
 *
 * The Condor manual (Weather, thermals panel): the cloud base depends on the surface temperature and
 * dew point (125 m per degree between them, TaskSettings), and cumulus form only when the inversion
 * is above the cloud base; below it, the thermals are blue. The base is taken above the ground under
 * the thermal (its lowest point under the climb): with the base above sea level, the pilots of the
 * races of the dev data would have flown in cloud most of the time, which their penalties do not
 * show. The zone of a thermal is the first drawn zone (polygon of the flight plan, in landscape
 * metres) around its centre, the base zone otherwise; the blending border of the zones is left out.
 */
final readonly class FlightClouds
{
    /** Metres a climb gains at least to have its cloud. */
    private const float MIN_GAIN = 150.0;

    public function __construct(
        private LandscapeRegistry $landscapes,
    ) {
    }

    /**
     * The settings of the task of a flight: those of its session, otherwise those of the flight plan
     * Condor writes in its IGC export.
     */
    public static function settingsOf(Flight $flight): ?TaskSettings
    {
        $settings = $flight->getSession()?->getTaskSettings();
        $condor = $flight->getAnalysis()['condor'] ?? null;
        if (null !== $settings || null === ($condor['flightPlan'] ?? null)) {
            return $settings;
        }
        try {
            $settings = TaskSettings::fromFpl($condor['flightPlan'], 'igc');
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $settings->withJoinTime(null !== ($condor['taskStart'] ?? null) ? $settings->joinTimeFrom($condor['taskStart']) : null);
    }

    /**
     * @return list<array{at: int, latitude: float, longitude: float, base: int, zone: string}> by time of the thermal
     */
    public function of(Flight $flight): array
    {
        $settings = self::settingsOf($flight);
        $analysis = $flight->getAnalysis();
        $ground = $analysis['series']['ground'] ?? null;
        if (null === $settings || [] === $settings->weather || !\is_array($ground)) {
            return [];
        }
        $zones = $this->zones($settings, $flight->getLandscape());
        $series = $analysis['series'];
        $clouds = [];
        foreach ($analysis['phases'] ?? [] as $phase) {
            if ('thermal' !== $phase['type'] || $phase['gain'] < self::MIN_GAIN) {
                continue;
            }
            $latitude = $longitude = 0.0;
            $count = 0;
            $low = null;
            for ($i = $phase['start']; $i <= $phase['end']; ++$i) {
                $latitude += $series['latitude'][$i];
                $longitude += $series['longitude'][$i];
                ++$count;
                if (null !== ($ground[$i] ?? null)) {
                    $low = min($low ?? \INF, $ground[$i]);
                }
            }
            if (0 === $count || null === $low) {
                continue;
            }
            [$latitude, $longitude] = [$latitude / $count, $longitude / $count];
            $zone = self::zoneAt($zones, $latitude, $longitude);
            if (null === $zone['cloudBase']) {
                continue;
            }
            $base = $low + $zone['cloudBase'];
            // Inversion under the cloud base: a blue thermal
            if (null !== $zone['inversion'] && $base >= $zone['inversion']) {
                continue;
            }
            $clouds[] = ['at' => $phase['start'], 'latitude' => round($latitude, 6), 'longitude' => round($longitude, 6), 'base' => (int) round($base), 'zone' => $zone['name']];
        }

        return $clouds;
    }

    /**
     * Adds the clouds of each flight to the replay data of a session (FlightSession::replayData()).
     *
     * @param array{task: mixed, flights: list<array<string, mixed>>} $replay
     * @param list<Flight>                                            $flights
     *
     * @return array{task: mixed, flights: list<array<string, mixed>>}
     */
    public function addTo(array $replay, array $flights): array
    {
        $byId = [];
        foreach ($flights as $flight) {
            $byId[$flight->getId()] = $flight;
        }
        foreach ($replay['flights'] as $k => $track) {
            $flight = $byId[$track['id'] ?? null] ?? null;
            $replay['flights'][$k]['clouds'] = null !== $flight ? $this->of($flight) : [];
        }

        return $replay;
    }

    /**
     * The zones with their polygon in latitude and longitude, the drawn ones first.
     *
     * @return list<array{name: string, cloudBase: ?int, inversion: ?int, polygon: list<array{float, float}>}>
     */
    private function zones(TaskSettings $settings, ?string $landscapeName): array
    {
        $landscape = null !== $landscapeName ? $this->landscapes->find($landscapeName) : null;
        $zones = [];
        foreach ($settings->weather as $zone) {
            $polygon = [];
            foreach (null !== $landscape ? $zone['polygon'] ?? [] : [] as [$x, $y]) {
                $polygon[] = $landscape->toLatLon($x, $y);
            }
            $zones[] = ['name' => $zone['name'], 'cloudBase' => $zone['cloudBase'], 'inversion' => $zone['inversion'], 'polygon' => $polygon];
        }
        $base = array_shift($zones);

        return null !== $base ? [...$zones, $base] : $zones;
    }

    /**
     * @param non-empty-list<array{name: string, cloudBase: ?int, inversion: ?int, polygon: list<array{float, float}>}> $zones the base one last
     *
     * @return array{name: string, cloudBase: ?int, inversion: ?int, polygon: list<array{float, float}>}
     */
    private static function zoneAt(array $zones, float $latitude, float $longitude): array
    {
        $base = array_pop($zones);
        foreach ($zones as $zone) {
            // A drawn zone whose landscape is not known has no polygon: left out
            if ([] !== $zone['polygon'] && self::inside($zone['polygon'], $latitude, $longitude)) {
                return $zone;
            }
        }

        return $base;
    }

    /**
     * Ray casting in latitude and longitude, close enough at the size of a landscape.
     *
     * @param list<array{float, float}> $polygon latitude, longitude
     */
    private static function inside(array $polygon, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = \count($polygon);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lonI] = $polygon[$i];
            [$latJ, $lonJ] = $polygon[$j];
            if (($latI > $latitude) !== ($latJ > $latitude) && $longitude < ($lonJ - $lonI) * ($latitude - $latI) / ($latJ - $latI) + $lonI) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
