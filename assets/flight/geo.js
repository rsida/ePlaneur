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
