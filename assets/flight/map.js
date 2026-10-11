import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { MapboxOverlay } from '@deck.gl/mapbox';
import { taskGate } from './geo.js';

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
            // A sky and a haze at the horizon in 3D, instead of the blank of the page
            sky: { 'sky-color': '#79aee3', 'horizon-color': '#d9e8f5', 'fog-color': '#e6eef6', 'sky-horizon-blend': 0.6, 'horizon-fog-blend': 0.7, 'fog-ground-blend': 0.9, 'atmosphere-blend': 0 },
            // Toned down so the tracks stand out
            layers: [{ id: 'base', type: 'raster', source: 'base', paint: { 'raster-saturation': -0.35, 'raster-opacity': 0.85 } }],
        },
        bounds,
        fitBoundsOptions: { padding: 40 },
        // Up to the horizon for the pilot view
        maxPitch: 85,
        // The pilot view turns all around in a thermal: keep the tiles of the whole circle
        maxTileCacheSize: 2000,
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

/** Layers of the task, to show or hide together. */
export const TASK_LAYERS = ['task', 'task-gates', 'turnpoints'];

/**
 * The task, lying on the relief: dashed line between the turnpoints, their cylinders, and for a
 * 180° start or finish the gate as Condor shows it (the line across the leg) with its half disc,
 * fainter.
 */
export function addTask(map, task) {
    if (!task) return;
    const points = task.turnpoints.slice(1);
    const gates = points.map((point, k) => taskGate(points, k));
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
                ...points.map((point, k) => (gates[k]
                    ? { type: 'Feature', properties: { faded: true }, geometry: { type: 'LineString', coordinates: gates[k].arc } }
                    : circle(point))),
            ],
        },
    });
    map.addLayer({ id: 'task', type: 'line', source: 'task', paint: { 'line-color': '#192630', 'line-width': 1.5, 'line-dasharray': [3, 2], 'line-opacity': ['case', ['boolean', ['get', 'faded'], false], 0.35, 0.8] } });
    map.addSource('task-gates', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: gates.filter(Boolean).map((gate) => ({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: gate.line } })) },
    });
    map.addLayer({ id: 'task-gates', type: 'line', source: 'task-gates', layout: { 'line-cap': 'round' }, paint: { 'line-color': '#192630', 'line-width': 3.5, 'line-opacity': 0.9 } });
    map.addSource('turnpoints', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: points.map((p, i) => ({ type: 'Feature', properties: { label: `${i === 0 ? 'Départ' : i === points.length - 1 ? 'Arrivée' : i}. ${p.name}` }, geometry: { type: 'Point', coordinates: [p.longitude, p.latitude] } })) },
    });
    map.addLayer({ id: 'turnpoints', type: 'circle', source: 'turnpoints', paint: { 'circle-radius': 4, 'circle-color': '#192630', 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 1.5 } });
}

/**
 * The shadow of a track: a dark line lying on the relief under it, shown in 3D only (from above it
 * would be under the track itself).
 */
export function addShadow(map, latitude, longitude) {
    const coordinates = latitude.map((lat, i) => [longitude[i], lat]);
    map.addSource('shadow', { type: 'geojson', data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates } } });
    map.addLayer({ id: 'shadow', type: 'line', source: 'shadow', layout: { visibility: 'none', 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': '#192630', 'line-width': 2.5, 'line-opacity': 0.45, 'line-blur': 1 } });
}

export function showShadow(map, on) {
    if (map.getLayer('shadow')) map.setLayoutProperty('shadow', 'visibility', on ? 'visible' : 'none');
}

/**
 * The slopes expected to work in the wind of the task (FlightTerrain): squares of `slopes.cell`
 * metres lying on the relief, more opaque where the lift should be stronger.
 */
export function addSlopes(map, slopes) {
    if (!slopes?.cells?.length) return;
    const features = slopes.cells.map(([lat, lon, lift]) => {
        const dLat = slopes.cell / 2 / 111320;
        const dLon = slopes.cell / 2 / (111320 * Math.cos((lat * Math.PI) / 180));

        return { type: 'Feature', properties: { lift }, geometry: { type: 'Polygon', coordinates: [[[lon - dLon, lat - dLat], [lon + dLon, lat - dLat], [lon + dLon, lat + dLat], [lon - dLon, lat + dLat], [lon - dLon, lat - dLat]]] } };
    });
    map.addSource('slopes', { type: 'geojson', data: { type: 'FeatureCollection', features } });
    // Under the task line
    map.addLayer({
        id: 'slopes',
        type: 'fill',
        source: 'slopes',
        paint: { 'fill-color': '#ff8c00', 'fill-opacity': ['interpolate', ['linear'], ['get', 'lift'], 1, 0.15, 4, 0.55] },
    }, map.getLayer('task') ? 'task' : undefined);
}

export function showSlopes(map, on) {
    if (map.getLayer('slopes')) map.setLayoutProperty('slopes', 'visibility', on ? 'visible' : 'none');
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
/** A time of day in seconds as "14:53:20", wrapping at midnight. */
export function formatClock(seconds) {
    const s = ((Math.round(seconds) % 86400) + 86400) % 86400;
    const pad = (value) => String(value).padStart(2, '0');

    return `${pad(Math.floor(s / 3600))}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}`;
}

export function formatTime(seconds) {
    const s = Math.max(0, Math.round(seconds));
    const h = Math.floor(s / 3600);
    const mm = String(Math.floor((s % 3600) / 60)).padStart(h > 0 ? 2 : 1, '0');
    const ss = String(s % 60).padStart(2, '0');

    return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}
