/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { ScatterplotLayer } from '@deck.gl/layers';
import { addTask, createMap, formatTime } from '../flight/map.js';

/*
 * Thermal map of a race (templates/session/show.html.twig): every thermal of every track of the
 * session (SessionController::thermals()), thermals closer than CLUSTER metres taken as one. Over
 * the whole race the size says how many climbs a thermal gave and the colour how strong they were;
 * with the time slider (time since each pilot crossed the start line), a thermal shows while a
 * pilot climbs in it and for RECENT seconds after.
 */

const CLUSTER = 700; // metres
const RECENT = 600; // seconds a thermal stays on the map after a pilot left it
// Average climb: under 1 m/s, 1 to 2, 2 to 3, over 3
const STRENGTHS = [
    { max: 1, color: [240, 150, 70] },
    { max: 2, color: [170, 205, 90] },
    { max: 3, color: [70, 170, 95] },
    { max: Infinity, color: [25, 120, 60] },
];

export default class extends Controller {
    static targets = ['map', 'status', 'seek', 'clock', 'all'];

    static values = { url: String };

    async connect() {
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.race = await response.json();
        } catch {
            this.statusTarget.textContent = 'La carte des thermiques n’a pas pu être chargée.';

            return;
        }
        const { thermals } = this.race;
        if (thermals.length === 0) {
            this.statusTarget.textContent = 'Aucun thermique dans les traces de cette course.';

            return;
        }
        this.end = Math.max(...thermals.map((t) => t.to));
        this.seekTarget.max = String(this.end);
        this.time = null;
        const lons = thermals.map((t) => t.longitude);
        const lats = thermals.map((t) => t.latitude);
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, [[Math.min(...lons), Math.min(...lats)], [Math.max(...lons), Math.max(...lats)]]));
        this.map.on('load', () => {
            addTask(this.map, this.race.task);
            this.render();
            this.statusTarget.hidden = true;
        });
    }

    disconnect() {
        this.map?.remove();
    }

    /** The slider: the thermals in use at that time of the race. */
    seek() {
        this.time = Number(this.seekTarget.value);
        this.allTarget.setAttribute('aria-pressed', 'false');
        this.render();
    }

    /** Back to every thermal of the race. */
    showAll() {
        this.time = null;
        this.allTarget.setAttribute('aria-pressed', 'true');
        this.render();
    }

    render() {
        if (!this.overlay) return;
        const used = this.time === null ? this.race.thermals : this.race.thermals.filter((t) => t.from <= this.time && this.time <= t.to + RECENT);
        this.clockTarget.textContent = this.time === null ? 'toute la course' : `${formatTime(this.time)} après le départ`;
        const clusters = cluster(used);
        this.overlay.setProps({
            layers: [new ScatterplotLayer({
                id: 'race-thermals',
                data: clusters,
                getPosition: (c) => [c.longitude, c.latitude],
                getRadius: (c) => 5 + 3 * Math.sqrt(c.count),
                radiusUnits: 'pixels',
                getFillColor: (c) => STRENGTHS.find((s) => c.climb < s.max).color,
                getLineColor: [255, 255, 255],
                lineWidthMinPixels: 1.5,
                stroked: true,
                pickable: true,
                parameters: { depthCompare: 'always' },
            })],
            getTooltip: ({ object }) => object && {
                text: `${object.count} montée${object.count > 1 ? 's' : ''} · ${object.climb.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} m/s en moyenne\n`
                    + `${Math.round(object.bottom)} → ${Math.round(object.top)} m · ${object.pilots.join(', ')}`,
            },
        });
    }
}

/** Thermals closer than CLUSTER metres to the first of a group join it (greedy, strongest first). */
function cluster(thermals) {
    const groups = [];
    [...thermals].sort((a, b) => b.climb - a.climb).forEach((t) => {
        const group = groups.find((g) => distance(g.latitude, g.longitude, t.latitude, t.longitude) < CLUSTER);
        if (group) {
            group.members.push(t);
        } else {
            groups.push({ latitude: t.latitude, longitude: t.longitude, members: [t] });
        }
    });

    return groups.map((g) => ({
        latitude: g.members.reduce((sum, t) => sum + t.latitude, 0) / g.members.length,
        longitude: g.members.reduce((sum, t) => sum + t.longitude, 0) / g.members.length,
        count: g.members.length,
        climb: g.members.reduce((sum, t) => sum + t.climb, 0) / g.members.length,
        bottom: Math.min(...g.members.map((t) => t.bottom)),
        top: Math.max(...g.members.map((t) => t.top)),
        pilots: [...new Set(g.members.map((t) => t.pilot))],
    }));
}

function distance(lat1, lon1, lat2, lon2) {
    const rad = Math.PI / 180;
    const x = (lon2 - lon1) * rad * Math.cos(((lat1 + lat2) / 2) * rad);
    const y = (lat2 - lat1) * rad;

    return Math.sqrt(x * x + y * y) * 6371000;
}
