import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { MapboxOverlay } from '@deck.gl/mapbox';

/*
 * The map shared by the flight viewers (flight_viewer and session_viewer controllers): MapLibre with
 * a raster basemap, the relief of AWS Terrain Tiles (terrarium encoding, no key) for the 3D view,
 * and a deck.gl overlay interleaved with the map (mountains hide what is behind them).
 */

export const BASEMAPS = {
    topo: {
        tiles: ['https://a.tile.opentopomap.org/{z}/{x}/{y}.png', 'https://b.tile.opentopomap.org/{z}/{x}/{y}.png', 'https://c.tile.opentopomap.org/{z}/{x}/{y}.png'],
        maxzoom: 17,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, SRTM · © <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
    },
    satellite: {
        tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
        maxzoom: 18,
        attribution: 'Imagerie © Esri, Maxar, Earthstar Geographics',
    },
    osm: {
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        maxzoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    },
};
const TERRAIN_TILES = 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png';

/** Map fitted to `bounds` ([[lon, lat], ...] corners), with its deck.gl overlay. */
export function createMap(container, bounds) {
    const map = new maplibregl.Map({
        container,
        style: {
            version: 8,
            sources: {
                base: { type: 'raster', tileSize: 256, ...BASEMAPS.topo },
                terrain: { type: 'raster-dem', tiles: [TERRAIN_TILES], tileSize: 256, maxzoom: 14, encoding: 'terrarium', attribution: 'Relief : AWS Terrain Tiles (Mapzen)' },
            },
            // Toned down so the tracks stand out
            layers: [{ id: 'base', type: 'raster', source: 'base', paint: { 'raster-saturation': -0.35, 'raster-opacity': 0.85 } }],
        },
        bounds,
        fitBoundsOptions: { padding: 40 },
        maxPitch: 80,
        // In 3D the camera looks at the glider in the air, not at the ground below it
        centerClampedToGround: false,
        attributionControl: { compact: true },
    });
    map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
    const overlay = new MapboxOverlay({ interleaved: true, layers: [] });
    map.addControl(overlay);

    return { map, overlay };
}

/** Bounds of a track, from every tenth point. */
export function boundsOf(latitude, longitude, bounds = new maplibregl.LngLatBounds()) {
    for (let i = 0; i < latitude.length; i += 10) bounds.extend([longitude[i], latitude[i]]);

    return bounds;
}

export function newBounds() {
    return new maplibregl.LngLatBounds();
}

/** The task: dashed line between the turnpoints and their cylinders, lying on the relief. */
export function addTask(map, task) {
    if (!task) return;
    const points = task.turnpoints.slice(1);
    const circle = (point) => {
        const ring = [];
        const dLat = point.radius / 111320;
        const dLon = point.radius / (111320 * Math.cos(point.latitude * Math.PI / 180));
        for (let a = 0; a <= 64; a += 1) {
            const angle = (a / 64) * 2 * Math.PI;
            ring.push([point.longitude + dLon * Math.cos(angle), point.latitude + dLat * Math.sin(angle)]);
        }

        return { type: 'Feature', properties: { name: point.name }, geometry: { type: 'LineString', coordinates: ring } };
    };
    map.addSource('task', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: [
                { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: points.map((p) => [p.longitude, p.latitude]) } },
                ...points.map(circle),
            ],
        },
    });
    map.addLayer({ id: 'task', type: 'line', source: 'task', paint: { 'line-color': '#192630', 'line-width': 1.5, 'line-dasharray': [3, 2], 'line-opacity': 0.8 } });
    map.addSource('turnpoints', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: points.map((p, i) => ({ type: 'Feature', properties: { label: `${i === 0 ? 'Départ' : i === points.length - 1 ? 'Arrivée' : i}. ${p.name}` }, geometry: { type: 'Point', coordinates: [p.longitude, p.latitude] } })) },
    });
    map.addLayer({ id: 'turnpoints', type: 'circle', source: 'turnpoints', paint: { 'circle-radius': 4, 'circle-color': '#192630', 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 1.5 } });
}

/** Changes the basemap; `redraw` runs once the style is updated. */
export function setBasemap(map, name, redraw) {
    const style = map.getStyle();
    style.sources.base = { type: 'raster', tileSize: 256, ...BASEMAPS[name] };
    map.setStyle(style, { diff: true });
    map.once('styledata', redraw);
}

/** Relief on (3D) or off (from above). */
export function setTerrain(map, on) {
    map.setTerrain(on ? { source: 'terrain', exaggeration: 1 } : null);
}

/**
 * Camera animation looking at a point `elevation` metres above sea level: only jumpTo() takes the
 * elevation of the centre, the animation keeps it.
 */
export function easeAt(map, options, elevation) {
    map.jumpTo({ elevation });
    map.easeTo(options);
}

/** Seconds as "1:21:49" (or "21:49"). */
export function formatTime(seconds) {
    const s = Math.max(0, Math.round(seconds));
    const h = Math.floor(s / 3600);
    const mm = String(Math.floor((s % 3600) / 60)).padStart(h > 0 ? 2 : 1, '0');
    const ss = String(s % 60).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}
