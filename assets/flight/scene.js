import { ColumnLayer, LineLayer, PathLayer, SolidPolygonLayer, TextLayer } from '@deck.gl/layers';
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
 *   high it flies above the relief;
 * - in 3D, the cylinders of the turnpoints standing up to the sky and the line of the task above
 *   the gliders, the thermals as arrows from where the pilot entered them to where they left, and
 *   the slope lift as orange arrows coming up the slope.
 *
 * A glider is { track: { latitude, longitude, altitude, speed }, index, color, label, selected }:
 * `index` is its second in the track, between two seconds during a replay. Optional: `flagColor`
 * (the colour of its flag when it is not its own, e.g. a white glider), `flagRow` (flags of
 * gliders close to each other put one above the other) and `details` (lines under the name in the
 * flag instead of the altitude, see readings()).
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
// Seconds of the average climb in the flag ("ø"), the integrator of a glider's vario
const AVERAGE_CLIMB = 30;
// Metres the cylinders of the turnpoints and the line of the task rise above the highest glider
const TASK_ABOVE = 300;
// Translucent volumes (cylinders, thermals) leave the depth buffer alone: otherwise the tracks drawn
// after them disappear behind their walls
export const SEE_THROUGH = { depthWriteEnabled: false };
// Thermal arrows by climb rate, from weak to strong (m/s)
const CLIMB_COLORS = [
    { max: 1, color: [240, 128, 118] },
    { max: 2, color: [226, 62, 52] },
    { max: 3, color: [186, 22, 38] },
    { max: Infinity, color: [128, 0, 48] },
];
// Slope lift: orange arrows every LIFT_ARROW_STEP metres, rising LIFT_ARROW_RISE metres
const LIFT_COLOR = [255, 140, 0];
// Thermals of a session closer than this (metres between their first circles) are the same one
const SAME_THERMAL = 600;
// Zoom from which the thermals of a session get their label
const SESSION_LABELS_ZOOM = 10.5;
const LIFT_ARROW_STEP = 400;
const LIFT_ARROW_RISE = 150;
// Half angle of the heads of the arrows
const HEAD_ANGLE = (32 * Math.PI) / 180;

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
    const lines = (item) => [item.label, ...(item.details ?? [`${Math.round(item.altitude).toLocaleString('fr-FR')} m`])];
    const size = (item) => (item.selected || item.details ? 13 : 11);
    // Flags put one above the other: each row as high as the tallest flag
    const row = Math.max(0, ...items.map((item) => lines(item).length * size(item) * 1.15 + 10));
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
            getText: (item) => lines(item).join('\n'),
            characterSet: 'auto',
            getSize: size,
            lineHeight: 1.15,
            getColor: [255, 255, 255],
            getPixelOffset: (item) => [0, -GLIDER_PIXELS / 2 - 6 - (item.flagRow ?? 0) * row],
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

/**
 * Lines of the flag of the glider followed, as on Condor Club: ground speed, vario, average climb
 * of the last AVERAGE_CLIMB seconds, altitude and, when the relief is known (`ground`, metres),
 * the height above it. The vario is the one of the track when it has one, else from its altitudes.
 */
export function readings(track, index, ground = null) {
    const { altitude, speed, vario } = track;
    const last = altitude.length - 1;
    const i = Math.max(0, Math.min(last, Math.round(index)));
    const a = Math.max(0, i - 2);
    const b = Math.min(last, i + 2);
    const now = vario ? vario[i] : (b > a ? (altitude[b] - altitude[a]) / (b - a) : 0);
    const back = Math.max(0, i - AVERAGE_CLIMB);
    const average = i > back ? (altitude[i] - altitude[back]) / (i - back) : 0;
    const climb = (value) => `${value > 0.05 ? '+' : value < -0.05 ? '−' : ''}${Math.abs(value).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s`;
    const lines = [
        `${Math.round(speed[i])} km/h`,
        `${now >= 0 ? '↑' : '↓'} ${climb(now)}`,
        `ø ${climb(average)}`,
        `${Math.round(altitude[i]).toLocaleString('fr-FR')} m`,
    ];
    // 0 while the relief under the glider is still loading
    if (ground !== null && Number.isFinite(ground) && ground > 0) lines.push(`${Math.max(0, Math.round(altitude[i] - ground)).toLocaleString('fr-FR')} m sol`);

    return lines;
}

/**
 * The task in 3D: each cylinder of a turnpoint as a column from the ground to `top` metres (the
 * highest altitude of the flights), translucent, and the line of the task at that height.
 */
export function taskLayers(id, task, { top }) {
    if (!task) return [];
    const points = task.turnpoints.slice(1);
    const ceiling = top + TASK_ABOVE;
    // One layer per radius: the radius of a column layer is the same for all its columns
    const radii = [...new Set(points.map((point) => point.radius))];

    return [
        ...radii.map((radius) => new ColumnLayer({
            id: `${id}-cylinders-${radius}`,
            data: points.filter((point) => point.radius === radius),
            getPosition: (point) => [point.longitude, point.latitude, 0],
            getElevation: ceiling,
            radius,
            diskResolution: 64,
            extruded: true,
            getFillColor: [80, 220, 235, 55],
            material: false,
            parameters: SEE_THROUGH,
        })),
        new PathLayer({
            id: `${id}-task-line`,
            data: [points],
            getPath: (line) => line.map((point) => [point.longitude, point.latitude, ceiling]),
            getColor: [120, 230, 150, 200],
            getWidth: 3,
            widthUnits: 'pixels',
            jointRounded: true,
        }),
    ];
}

/**
 * Thermals of a flight in 3D, as arrows rising from the centre of the first circle at the altitude
 * of the entry to the centre of the last circle at the exit: tilted by the drift of the thermal
 * (the wind), thicker and darker with the climb rate. Those entered before `index` (the one being
 * climbed grows with the glider). `thermals` are the phases of the analysis ({ start, end, vario,
 * turns }); `color` replaces the colours of the climb rates (several pilots on one map: the colour
 * of the pilot); `zoom` (of the map) sets the size of the heads.
 */
export function thermalLayers(id, track, thermals, index, { zoom, color = null } = {}) {
    const arrows = thermalArrows(track, thermals, index).map((arrow) => ({ ...arrow, color: color ?? climbColor(arrow.vario), width: arrowWidth(arrow.vario) }));

    return [arrowLayer(`${id}-thermals`, arrows, zoom)];
}

/**
 * The thermals of every pilot of a session in 3D, the same thermal used by several pilots as one
 * arrow: thermals whose first circles are less than SAME_THERMAL metres apart are one, drawn from
 * the lowest entry to the highest exit, coloured by the average climb (weighted by the time spent),
 * with how many pilots climbed in it and their climb (when the map is close enough). `flights` are
 * { track, index, thermals }.
 */
export function sessionThermalLayers(id, flights, { zoom }) {
    const groups = [];
    flights.forEach((flight, pilot) => {
        for (const arrow of thermalArrows(flight.track, flight.thermals ?? [], flight.index)) {
            const group = groups.find((g) => metresBetween(g.from, arrow.from) < SAME_THERMAL);
            if (!group) {
                groups.push({ from: arrow.from, to: arrow.to, pilots: new Set([pilot]), climb: arrow.vario * arrow.seconds, seconds: arrow.seconds });
                continue;
            }
            group.pilots.add(pilot);
            group.climb += arrow.vario * arrow.seconds;
            group.seconds += arrow.seconds;
            if (arrow.from[2] < group.from[2]) group.from = arrow.from;
            if (arrow.to[2] > group.to[2]) group.to = arrow.to;
        }
    });
    const arrows = groups.map((group) => {
        const vario = group.seconds > 0 ? group.climb / group.seconds : 0;
        const pilots = group.pilots.size;

        return { from: group.from, to: group.to, vario, color: climbColor(vario), width: arrowWidth(vario), label: `${pilots > 1 ? `${pilots} pilotes · ` : ''}${vario > 0 ? '+' : ''}${vario.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s` };
    });

    return [
        arrowLayer(`${id}-session-thermals`, arrows, zoom),
        new TextLayer({
            id: `${id}-session-thermal-labels`,
            // From afar the labels of a whole task pile up
            visible: zoom >= SESSION_LABELS_ZOOM,
            data: arrows,
            getPosition: (arrow) => arrow.to,
            getText: (arrow) => arrow.label,
            characterSet: 'auto',
            getSize: 11,
            getColor: [255, 255, 255],
            // Above the head of the arrow
            getPixelOffset: (arrow) => [0, -((arrow.width * 3 + 10) * Math.max(0.5, Math.min(1, (zoom - 8) / 4)) + 4)],
            getTextAnchor: 'middle',
            getAlignmentBaseline: 'bottom',
            fontFamily: 'Inter, sans-serif',
            fontWeight: 600,
            background: true,
            getBackgroundColor: (arrow) => [...arrow.color, 220],
            backgroundPadding: [4, 2],
        }),
    ];
}

/**
 * Arrows of the thermals of a track entered before `index`: from the centre of the first circle at
 * the entry to the centre of the last circle so far, with their climb (m/s) and seconds.
 */
function thermalArrows(track, thermals, index) {
    const arrows = [];
    for (const phase of thermals) {
        if (phase.start > index) break;
        const end = Math.min(phase.end, Math.floor(index));
        const circle = circleSeconds(phase);
        const from = [...centreOf(track, phase.start, Math.min(end, phase.start + circle - 1)), track.altitude[phase.start]];
        const to = [...centreOf(track, Math.max(phase.start, end - circle + 1), end), track.altitude[end]];
        if (to[2] - from[2] > 20) arrows.push({ from, to, vario: phase.vario, seconds: end - phase.start });
    }

    return arrows;
}

/**
 * Slope lift (straight flight that climbs, the "lift" phases) in 3D: the part of the track in an
 * orange band, and orange arrows every LIFT_ARROW_STEP metres coming up to it from below on the
 * side the wind comes from (`windFrom`, degrees; straight up when it is not known), like the air
 * pushed up the slope. Those entered before `index`.
 */
export function liftLayers(id, track, lifts, index, { zoom, windFrom = null } = {}) {
    const bands = [];
    const arrows = [];
    const upwind = windFrom === null || windFrom === undefined ? null : (windFrom * Math.PI) / 180;
    for (const phase of lifts) {
        if (phase.start > index) break;
        const end = Math.min(phase.end, Math.floor(index));
        const band = [];
        let flown = LIFT_ARROW_STEP / 2;
        for (let k = phase.start; k <= end; k += 1) {
            const point = [track.longitude[k], track.latitude[k], track.altitude[k]];
            band.push(point);
            if (k > phase.start) flown += metresBetween(band[band.length - 2], point);
            if (flown < LIFT_ARROW_STEP) continue;
            flown = 0;
            const [east, north] = upwind === null ? [0, 0] : [Math.sin(upwind) * LIFT_ARROW_RISE, Math.cos(upwind) * LIFT_ARROW_RISE];
            arrows.push({ from: offset(point, east, north, -LIFT_ARROW_RISE), to: point, color: LIFT_COLOR, width: 5 });
        }
        if (band.length > 1) bands.push(band);
    }

    return [
        new PathLayer({
            id: `${id}-lift-bands`,
            data: bands,
            getPath: (band) => band,
            getColor: [...LIFT_COLOR, 120],
            getWidth: 10,
            widthUnits: 'pixels',
            capRounded: true,
            jointRounded: true,
            parameters: SEE_THROUGH,
        }),
        arrowLayer(`${id}-lifts`, arrows, zoom),
    ];
}

/** Colour of a climb rate (m/s), from weak to strong. */
export function climbColor(vario) {
    return (CLIMB_COLORS.find((c) => vario <= c.max) ?? CLIMB_COLORS[0]).color;
}

/** Width in pixels of the arrow of a thermal: thicker with its climb rate (m/s). */
export function arrowWidth(vario) {
    return Math.max(4, Math.min(18, 4 + 3 * vario));
}

/**
 * Arrows in 3D ({ from, to, color, width (pixels) }): a line and, beyond its end, a head of four
 * barbs in two planes across the line, which reads as a head from any side, about three times as
 * long as the line is wide on the screen. Thinner when the map is zoomed out (down to half), so that
 * a whole flight seen from afar is not a row of blots.
 */
export function arrowLayer(id, arrows, zoom = 12) {
    const thinner = Math.max(0.5, Math.min(1, (zoom - 8) / 4));
    const paths = [];
    for (const arrow of arrows) {
        const { from, to } = arrow;
        const metresPerDegreeLon = 111320 * Math.cos((to[1] * Math.PI) / 180);
        const d = [(to[0] - from[0]) * metresPerDegreeLon, (to[1] - from[1]) * 111320, to[2] - from[2]];
        const length = Math.hypot(...d);
        paths.push({ arrow, path: [from, to] });
        if (length < 1) continue;
        const unit = d.map((v) => v / length);
        const metresPerPixel = (40075016.686 * Math.cos((to[1] * Math.PI) / 180)) / (512 * 2 ** zoom);
        const head = (arrow.width * 3 + 10) * thinner * metresPerPixel;
        // Two directions across the arrow: horizontal, then across both
        let across = [unit[1], -unit[0], 0];
        if (Math.hypot(...across) < 0.01) across = [1, 0, 0];
        const norm = Math.hypot(...across);
        across = across.map((v) => v / norm);
        const other = [unit[1] * across[2] - unit[2] * across[1], unit[2] * across[0] - unit[0] * across[2], unit[0] * across[1] - unit[1] * across[0]];
        // The tip beyond the end, so the head does not shorten the arrow
        const tip = offset(to, unit[0] * head, unit[1] * head, unit[2] * head);
        paths.push({ arrow, path: [to, tip] });
        const back = Math.cos(HEAD_ANGLE) * head;
        const side = Math.sin(HEAD_ANGLE) * head;
        for (const n of [across, other]) {
            for (const sign of [1, -1]) {
                paths.push({ arrow, path: [offset(tip, -unit[0] * back + sign * n[0] * side, -unit[1] * back + sign * n[1] * side, -unit[2] * back + sign * n[2] * side), tip] });
            }
        }
    }

    return new PathLayer({
        id,
        data: paths,
        getPath: (part) => part.path,
        getColor: (part) => [...part.arrow.color, 230],
        getWidth: (part) => part.arrow.width * thinner,
        widthUnits: 'pixels',
        capRounded: true,
        jointRounded: true,
        parameters: SEE_THROUGH,
    });
}

/** Seconds of one circle of a thermal (from its turns), for the centres of its circles. */
function circleSeconds(phase) {
    const duration = phase.end - phase.start;

    return Math.max(8, Math.min(60, phase.turns > 0 ? Math.round(duration / phase.turns) : 20));
}

/** Average position of a track between two seconds (included): [longitude, latitude]. */
function centreOf(track, from, to) {
    let lon = 0;
    let lat = 0;
    for (let k = from; k <= to; k += 1) {
        lon += track.longitude[k] / (to - from + 1);
        lat += track.latitude[k] / (to - from + 1);
    }

    return [lon, lat];
}

/** A point moved by metres east, north and up. */
function offset([lon, lat, altitude], east, north, up) {
    return [lon + east / (111320 * Math.cos((lat * Math.PI) / 180)), lat + north / 111320, altitude + up];
}

/** Horizontal distance in metres between two close points. */
function metresBetween(a, b) {
    return Math.hypot((b[0] - a[0]) * 111320 * Math.cos((a[1] * Math.PI) / 180), (b[1] - a[1]) * 111320);
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
