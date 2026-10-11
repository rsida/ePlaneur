/*
 * A glider in 3D for the replays (SimpleMeshLayer of deck.gl): fuselage with its canopy, tapered
 * wings with some dihedral, fin and T-tail, as one mesh of a few hundred triangles drawn once per
 * glider by the GPU, lit by the lights of deck.gl.
 *
 * Metres, x to the right wing, y forward, z up, like the flat shapes of scene.js; drawn wider than a
 * real 15 m glider (chord, fuselage, tail) so that it reads at the size of a few dozen pixels. The
 * colours of the vertices are multiplied by the colour of the glider: white for the body, a dark
 * canopy whatever the glider.
 */

const BODY = [1, 1, 1];
const CANOPY = [0.22, 0.3, 0.4];
// Darker tips and leading edge of the tail, like the anti-collision marks of real gliders
const MARK = [0.85, 0.2, 0.15];
const DIHEDRAL = (4 * Math.PI) / 180;
const SEGMENTS = 10;

/** The mesh, built once. */
export const GLIDER_MESH = buildMesh();

/**
 * Column-major 4×4 matrix of a glider: scaled, banked around its fuselage (degrees, right
 * positive), pitched (degrees, nose up), then turned to its heading (degrees from north, clockwise).
 */
export function gliderMatrix(heading, bank, pitch, scale) {
    const h = (-heading * Math.PI) / 180;
    const p = (pitch * Math.PI) / 180;
    const b = (bank * Math.PI) / 180;
    const rz = [[Math.cos(h), -Math.sin(h), 0], [Math.sin(h), Math.cos(h), 0], [0, 0, 1]];
    const rx = [[1, 0, 0], [0, Math.cos(p), -Math.sin(p)], [0, Math.sin(p), Math.cos(p)]];
    const ry = [[Math.cos(b), 0, Math.sin(b)], [0, 1, 0], [-Math.sin(b), 0, Math.cos(b)]];
    const r = multiply(rz, multiply(rx, ry));

    return [
        r[0][0] * scale, r[1][0] * scale, r[2][0] * scale, 0,
        r[0][1] * scale, r[1][1] * scale, r[2][1] * scale, 0,
        r[0][2] * scale, r[1][2] * scale, r[2][2] * scale, 0,
        0, 0, 0, 1,
    ];
}

function multiply(a, b) {
    return a.map((row) => [0, 1, 2].map((j) => row[0] * b[0][j] + row[1] * b[1][j] + row[2] * b[2][j]));
}

function buildMesh() {
    const positions = [];
    const normals = [];
    const colors = [];
    const triangle = (a, b, c, color, normal = null) => {
        const n = normal ?? unit(cross(sub(b, a), sub(c, a)));
        positions.push(...a, ...b, ...c);
        normals.push(...n, ...n, ...n);
        colors.push(...color, ...color, ...color);
    };

    // Fuselage: sections along y (forward), radius and height of their centre
    tube(triangle, [
        [3.5, 0.02, 0.05],
        [3.1, 0.3, 0.05],
        [2.4, 0.48, 0.08],
        [1.2, 0.52, 0.1],
        [0.2, 0.45, 0.1],
        [-1.0, 0.3, 0.15],
        [-3.0, 0.18, 0.25],
        [-6.0, 0.12, 0.35],
        [-6.5, 0.02, 0.4],
    ], BODY, 1);
    // Canopy over the front of the fuselage
    tube(triangle, [
        [2.9, 0.02, 0.3],
        [2.5, 0.26, 0.38],
        [1.6, 0.32, 0.42],
        [0.8, 0.02, 0.4],
    ], CANOPY, 0.8);

    // Wings: tapered, the tips raised by the dihedral, red tips
    for (const side of [-1, 1]) {
        const lift = (x) => Math.abs(x) * Math.tan(DIHEDRAL) + 0.15;
        const point = (x, y) => [side * x, y, lift(x)];
        const root = [point(0.4, 1.1), point(6.4, 0.7), point(6.4, -0.3), point(0.4, -0.7)];
        const tip = [point(6.4, 0.7), point(7.6, 0.55), point(7.6, -0.15), point(6.4, -0.3)];
        plate(triangle, root, [0, 0, 1], 0.16, BODY);
        plate(triangle, tip, [0, 0, 1], 0.12, MARK);
    }
    // Fin, and the tailplane on top of it
    plate(triangle, [[0, -5.0, 0.3], [0, -6.5, 0.3], [0, -6.6, 1.9], [0, -5.9, 1.9]], [1, 0, 0], 0.12, BODY);
    plate(triangle, [[-2.4, -5.8, 1.95], [2.4, -5.8, 1.95], [2.4, -6.6, 1.95], [-2.4, -6.6, 1.95]], [0, 0, 1], 0.1, BODY);

    return {
        positions: { size: 3, value: new Float32Array(positions) },
        normals: { size: 3, value: new Float32Array(normals) },
        colors: { size: 3, value: new Float32Array(colors) },
    };
}

/** A body of revolution along y: sections [y, radius, centre height], `squash` flattens it. */
function tube(triangle, sections, color, squash) {
    const ring = ([y, radius, z]) => Array.from({ length: SEGMENTS }, (_, k) => {
        const angle = (k / SEGMENTS) * 2 * Math.PI;

        return { point: [radius * Math.cos(angle), y, z + radius * squash * Math.sin(angle)], normal: unit([Math.cos(angle), 0, Math.sin(angle) / squash]) };
    });
    for (let s = 0; s < sections.length - 1; s += 1) {
        const a = ring(sections[s]);
        const b = ring(sections[s + 1]);
        for (let k = 0; k < SEGMENTS; k += 1) {
            const n = (k + 1) % SEGMENTS;
            // Smooth: the normal of each quad is the average of its corners
            const normal = unit(add(add(a[k].normal, a[n].normal), add(b[k].normal, b[n].normal)));
            triangle(a[k].point, a[n].point, b[n].point, color, normal);
            triangle(a[k].point, b[n].point, b[k].point, color, normal);
        }
    }
}

/** A flat part of some thickness: its outline on both faces, and its edges. */
function plate(triangle, points, up, thickness, color) {
    // Counter-clockwise seen from `up`, so that the edges face outwards (Newell's normal)
    const newell = points.reduce((sum, p, k) => {
        const q = points[(k + 1) % points.length];

        return add(sum, [(p[1] - q[1]) * (p[2] + q[2]), (p[2] - q[2]) * (p[0] + q[0]), (p[0] - q[0]) * (p[1] + q[1])]);
    }, [0, 0, 0]);
    const outline = newell[0] * up[0] + newell[1] * up[1] + newell[2] * up[2] < 0 ? [...points].reverse() : points;
    const half = thickness / 2;
    const top = outline.map((p) => add(p, scaled(up, half)));
    const bottom = outline.map((p) => add(p, scaled(up, -half)));
    for (let k = 1; k < outline.length - 1; k += 1) {
        triangle(top[0], top[k], top[k + 1], color, up);
        triangle(bottom[0], bottom[k + 1], bottom[k], color, scaled(up, -1));
    }
    for (let k = 0; k < outline.length; k += 1) {
        const n = (k + 1) % outline.length;
        triangle(bottom[k], bottom[n], top[n], color);
        triangle(bottom[k], top[n], top[k], color);
    }
}

function add(a, b) {
    return [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
}

function sub(a, b) {
    return [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
}

function scaled(a, k) {
    return [a[0] * k, a[1] * k, a[2] * k];
}

function cross(a, b) {
    return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
}

function unit(a) {
    const length = Math.hypot(...a) || 1;

    return [a[0] / length, a[1] / length, a[2] / length];
}
