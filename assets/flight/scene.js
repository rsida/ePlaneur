import { LineLayer, PathLayer, SolidPolygonLayer, TextLayer } from '@deck.gl/layers';
import { bearing } from './geo.js';

/*
 * The gliders of the flight viewers (flight_viewer, compare_viewer and session_viewer controllers),
 * drawn the same way, after the 3D replays of Condor Club and SportsTrackLive:
 *
 * - a glider seen from anywhere: wings, tailplane, fuselage and fin, turned to its heading and banked
 *   like it, enlarged to stay about GLIDER_PIXELS wide whatever the zoom (at its real size a 15 m
 *   glider is a dot);
 * - a flag above it: its name (or competition id) and altitude on its colour;
 * - its trail: the last minutes of its track, fading out behind it;
 * - in 3D, for the glider followed, a curtain down to the ground under its trail, which shows how
 *   high it flies above the relief.
 *
 * A glider is { track: { latitude, longitude, altitude, speed }, index, color, label, selected }:
 * `index` is its second in the track, between two seconds during a replay. Optional: `flagColor`
 * (the colour of its flag when it is not its own, e.g. a white glider) and `flagRow` (flags of
 * gliders close to each other put one above the other).
 */

/*
 * Shape of the glider in metres: x to the right wing, y forward, z up. A 15 m glider drawn wider
 * than it is (chord, fuselage, tailplane): with its real slender wings it reads as a line.
 */
const WINGS = [[-7.5, 0.6, 0], [-0.6, 1.6, 0], [0.6, 1.6, 0], [7.5, 0.6, 0], [7.5, -0.5, 0], [0.6, -0.6, 0], [-0.6, -0.6, 0], [-7.5, -0.5, 0]];
const TAILPLANE = [[-2.6, -5.2, 0.9], [2.6, -5.2, 0.9], [2.6, -6.3, 0.9], [-2.6, -6.3, 0.9]];
const FUSELAGE = [[0, 3.4, -0.2], [0, 0, 0], [0, -6.3, 0.3]];
const FIN = [[0, -5.3, 0.2], [0, -6.3, 1.8]];
const SPAN = 15;
const GLIDER_PIXELS = 56;
const TRAIL_CHUNKS = 6;

/** Signed difference from one heading to another, in degrees (-180 to 180, positive to the right). */
export function angleDifference(from, to) {
    return ((to - from + 540) % 360) - 180;
}

/**
 * Heading (degrees from north) and bank (degrees, to the right positive) of a glider at an index of
 * its track, between two seconds: the bank of a steady turn, tan(bank) = v·ω / g.
 */
export function attitude(track, index) {
    const { latitude, longitude, speed } = track;
    const last = latitude.length - 1;
    const heading = (k) => {
        const from = Math.max(0, k - 2);
        const to = Math.min(last, k + 2);

        return bearing(latitude[from], longitude[from], latitude[to], longitude[to]);
    };
    const bankAt = (k) => {
        // Degrees per second
        const turnRate = angleDifference(heading(Math.max(0, k - 1)), heading(Math.min(last, k + 1))) / 2;

        return Math.max(-60, Math.min(60, Math.atan(((speed[k] / 3.6) * turnRate * Math.PI) / 180 / 9.81) * 180 / Math.PI));
    };
    const a = Math.max(0, Math.min(last, Math.floor(index)));
    const b = Math.min(last, a + 1);
    const f = index - a;

    return {
        heading: (heading(a) + angleDifference(heading(a), heading(b)) * f + 360) % 360,
        bank: bankAt(a) + (bankAt(b) - bankAt(a)) * f,
    };
}

/**
 * Bearing of a camera flying behind a glider: along its track of the last `seconds` (the course,
 * not each circle of a thermal), moved a little from `previous` at each frame.
 */
export function chaseBearing(track, index, previous, seconds = 60) {
    const [lon, lat] = positionAt(track, index);
    const back = Math.max(0, Math.floor(index) - seconds);
    if (back >= Math.floor(index)) return previous ?? 0;
    const course = bearing(track.latitude[back], track.longitude[back], lat, lon);

    return previous === null || previous === undefined ? course : (previous + angleDifference(previous, course) * 0.05 + 360) % 360;
}

/** Position of a glider between two seconds of its track: [longitude, latitude, altitude]. */
export function positionAt(track, index) {
    const last = track.latitude.length - 1;
    const a = Math.max(0, Math.min(last, Math.floor(index)));
    const b = Math.min(last, a + 1);
    const f = index - a;
    const lerp = (values) => values[a] + (values[b] - values[a]) * f;

    return [lerp(track.longitude), lerp(track.latitude), lerp(track.altitude)];
}

/**
 * Layers of the gliders: body, flag, and the line down to the ground in 3D. `zoom` is the zoom of
 * the map, which sets the size of the gliders.
 */
export function gliderLayers(id, gliders, { is3d, zoom }) {
    const items = gliders.map((glider) => {
        const [lon, lat, altitude] = positionAt(glider.track, glider.index);
        const { heading, bank } = attitude(glider.track, glider.index);
        const metresPerPixel = (40075016.686 * Math.cos((lat * Math.PI) / 180)) / (512 * 2 ** zoom);
        // The glider followed a little bigger
        const scale = Math.max(1, ((glider.selected ? 1.4 : 1) * GLIDER_PIXELS * metresPerPixel) / SPAN);
        const z = is3d ? altitude : 0;
        const place = shape(lon, lat, z, heading, is3d ? bank : 0, scale);

        return { ...glider, position: [lon, lat, z], ground: [lon, lat, 0], altitude, place, scale };
    });
    // The glider followed is drawn last, over the others
    items.sort((a, b) => Number(a.selected) - Number(b.selected));
    const always = { depthCompare: 'always' };
    const outline = (item) => (item.selected ? [255, 255, 255] : [25, 38, 48]);
    const layers = [
        new SolidPolygonLayer({
            id: `${id}-wings`,
            data: items.flatMap((item) => [{ item, polygon: item.place(WINGS) }, { item, polygon: item.place(TAILPLANE) }]),
            getPolygon: (part) => part.polygon,
            getFillColor: (part) => part.item.color,
            parameters: always,
        }),
        new PathLayer({
            id: `${id}-body`,
            data: items.flatMap((item) => [
                { item, path: item.place(FUSELAGE), width: 1.6 },
                { item, path: item.place(FIN), width: 1 },
                { item, path: item.place([...WINGS, WINGS[0]]), width: 0.25 },
                { item, path: item.place([...TAILPLANE, TAILPLANE[0]]), width: 0.25 },
            ]),
            getPath: (part) => part.path,
            getColor: (part) => (part.width < 0.5 ? outline(part.item) : part.item.color),
            getWidth: (part) => part.width * part.item.scale,
            widthUnits: 'meters',
            widthMinPixels: 1,
            capRounded: true,
            jointRounded: true,
            parameters: always,
        }),
        new TextLayer({
            id: `${id}-flags`,
            data: items.filter((item) => item.label),
            getPosition: (item) => item.position,
            getText: (item) => `${item.label}\n${Math.round(item.altitude).toLocaleString('fr-FR')} m`,
            characterSet: 'auto',
            getSize: (item) => (item.selected ? 13 : 11),
            lineHeight: 1.15,
            getColor: [255, 255, 255],
            getPixelOffset: (item) => [0, -GLIDER_PIXELS / 2 - 6 - (item.flagRow ?? 0) * 36],
            getTextAnchor: 'middle',
            getAlignmentBaseline: 'bottom',
            fontFamily: 'Inter, sans-serif',
            fontWeight: 700,
            background: true,
            getBackgroundColor: (item) => [...(item.flagColor ?? item.color), 235],
            getBorderColor: outline,
            getBorderWidth: (item) => (item.selected ? 2 : 0),
            backgroundPadding: [5, 3],
            parameters: always,
        }),
    ];
    if (is3d) {
        layers.unshift(new LineLayer({
            id: `${id}-stems`,
            data: items,
            getSourcePosition: (item) => item.position,
            getTargetPosition: (item) => item.ground,
            getColor: (item) => [...item.color, 200],
            getWidth: (item) => (item.selected ? 2.5 : 1.5),
        }));
    }

    return layers;
}

/**
 * The trail of each glider: its track over the last `seconds`, in its colour, more transparent the
 * older it is. With `curtain`, the glider followed also gets lines down to the ground every second.
 */
export function trailLayers(id, gliders, { is3d, seconds, width = 3 }) {
    const chunks = [];
    const curtain = [];
    for (const glider of gliders) {
        const { track, index, color } = glider;
        const to = Math.floor(index);
        const from = Math.max(0, to - seconds);
        const point = (k) => [track.longitude[k], track.latitude[k], is3d ? track.altitude[k] : 0];
        const size = Math.max(1, Math.ceil((to - from) / TRAIL_CHUNKS));
        for (let c = 0; c < TRAIL_CHUNKS; c += 1) {
            const start = from + c * size;
            if (start >= to) break;
            const path = [];
            for (let k = start; k <= Math.min(to, start + size); k += 1) path.push(point(k));
            if (c === TRAIL_CHUNKS - 1 || start + size >= to) path.push(positionAt(track, index).map((v, n) => (n === 2 && !is3d ? 0 : v)));
            chunks.push({ path, color: [...color, Math.round(60 + (195 * (c + 1)) / TRAIL_CHUNKS)], width: glider.selected ? width + 1.5 : width });
        }
        if (is3d && glider.selected) {
            for (let k = from; k <= to; k += 1) curtain.push({ top: point(k), bottom: [track.longitude[k], track.latitude[k], 0], color: [...color, 45] });
        }
    }
    const parameters = is3d ? {} : { depthCompare: 'always' };

    return [
        new LineLayer({
            id: `${id}-curtain`,
            data: curtain,
            getSourcePosition: (line) => line.top,
            getTargetPosition: (line) => line.bottom,
            getColor: (line) => line.color,
            getWidth: 2,
        }),
        new PathLayer({
            id: `${id}-trails`,
            data: chunks,
            getPath: (chunk) => chunk.path,
            getColor: (chunk) => chunk.color,
            getWidth: (chunk) => chunk.width,
            widthUnits: 'pixels',
            capRounded: true,
            jointRounded: true,
            parameters,
        }),
    ];
}

/** Places a shape of the glider (metres) at a point, turned to `heading`, banked by `bank`, scaled. */
function shape(lon, lat, altitude, heading, bank, scale) {
    const h = (heading * Math.PI) / 180;
    const b = (bank * Math.PI) / 180;
    const metresPerDegreeLat = 111320;
    const metresPerDegreeLon = 111320 * Math.cos((lat * Math.PI) / 180);

    return (points) => points.map(([x, y, z]) => {
        // Bank around the axis of the fuselage: banked right, the right wing goes down
        const xb = x * Math.cos(b) - z * Math.sin(b);
        const zb = -x * Math.sin(b) + z * Math.cos(b);
        // Heading, clockwise from north
        const east = (xb * Math.cos(h) + y * Math.sin(h)) * scale;
        const north = (-xb * Math.sin(h) + y * Math.cos(h)) * scale;

        return [lon + east / metresPerDegreeLon, lat + north / metresPerDegreeLat, altitude + zb * scale];
    });
}
