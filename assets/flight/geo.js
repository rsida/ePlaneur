/* Distances on the Earth for the flight viewers, as App\Flight\Geo\Geo does on the server. */

const EARTH_RADIUS = 6371008.8;

/** Metres between two points (haversine). */
export function distance(lat1, lon1, lat2, lon2) {
    const rad = Math.PI / 180;
    const a = Math.sin(((lat2 - lat1) * rad) / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(((lon2 - lon1) * rad) / 2) ** 2;

    return 2 * EARTH_RADIUS * Math.asin(Math.sqrt(a));
}

/** Initial bearing from a point to another, degrees from north. */
export function bearing(lat1, lon1, lat2, lon2) {
    const rad = Math.PI / 180;
    const y = Math.sin((lon2 - lon1) * rad) * Math.cos(lat2 * rad);
    const x = Math.cos(lat1 * rad) * Math.sin(lat2 * rad) - Math.sin(lat1 * rad) * Math.cos(lat2 * rad) * Math.cos((lon2 - lon1) * rad);

    return (Math.atan2(y, x) / rad + 360) % 360;
}

/** The point `east` and `north` metres away from a point, as [longitude, latitude]. */
export function offset(lat, lon, east, north) {
    return [lon + east / (111320 * Math.cos((lat * Math.PI) / 180)), lat + north / 111320];
}

/**
 * The gate of a 180° start or finish, as Condor shows it: a line across the leg, twice the radius
 * long, and the half disc of the zone, behind the line for the start (left when crossing it) and
 * beyond it for the finish (entered when crossing it), as App\Flight\Analysis\FlightAnalyzer
 * counts them. `points` are the task points from the start, `k` the start (0) or the finish (last);
 * null for any other point or zone.
 *
 * @return {{ line: number[][], arc: number[][], min: ?number, max: ?number }|null}
 */
export function taskGate(points, k) {
    const point = points[k];
    const last = points.length - 1;
    if (!point || Math.abs(point.angle - 180) >= 1 || last < 1 || (k !== 0 && k !== last)) return null;
    const [from, to] = k === 0 ? [point, points[1]] : [points[last - 1], point];
    const leg = (bearing(from.latitude, from.longitude, to.latitude, to.longitude) * Math.PI) / 180;
    // Direction of the half disc: back along the leg for the start, on along it for the finish
    const side = k === 0 ? leg + Math.PI : leg;
    const at = (angle) => offset(point.latitude, point.longitude, point.radius * Math.sin(angle), point.radius * Math.cos(angle));
    const arc = [];
    for (let a = 0; a <= 32; a += 1) arc.push(at(side - Math.PI / 2 + (a / 32) * Math.PI));

    return { line: [at(leg - Math.PI / 2), at(leg + Math.PI / 2)], arc, min: point.minAltitude ?? null, max: point.maxAltitude ?? null };
}
