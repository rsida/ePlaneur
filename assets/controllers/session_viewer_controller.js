/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { PathLayer } from '@deck.gl/layers';
import { addTask, boundsOf, createMap, easeAt, formatTime, newBounds, setBasemap, setTerrain } from '../flight/map.js';
import { chaseBearing, gliderLayers, positionAt, trailLayers } from '../flight/scene.js';

/*
 * Session replay (templates/flight/session.html.twig): every flight of FlightController::sessionData()
 * on one map, one color per pilot, replayed together.
 *
 * Two ways to put the flights on one clock:
 * - "race": each recording from its beginning (in a network race every pilot starts at once);
 * - "task": every pilot crosses the start line at the same moment, to compare the tasks flown on
 *   different days or with different start times like a ghost race.
 *
 * Each pilot is a glider in their colour with a flag (name, altitude) and a fading trail
 * (../flight/scene.js). Clicking a pilot follows them: in 3D the camera then flies behind their
 * glider, and a curtain under their trail shows their height above the relief.
 */

/* One color per pilot, in ranking order */
const COLORS = [
    [214, 69, 65], [62, 93, 131], [36, 140, 72], [168, 91, 42], [123, 79, 160],
    [0, 150, 160], [200, 160, 0], [220, 90, 160], [90, 90, 90], [120, 160, 40],
];
const TRAIL = 300; // seconds of track drawn behind each glider

export default class extends Controller {
    static targets = ['map', 'view', 'basemap', 'align', 'status', 'clock', 'pilots', 'dot', 'play', 'rate', 'seek'];

    static values = { url: String };

    async connect() {
        this.time = 0;
        this.playing = false;
        this.is3d = false;
        this.selected = null;
        this.chase = null;
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.session = await response.json();
        } catch {
            this.statusTarget.textContent = 'La session n’a pas pu être chargée.';

            return;
        }
        this.flights = this.session.flights.map((flight, k) => ({ ...flight, color: COLORS[k % COLORS.length], count: flight.altitude.length }));
        this.colorDots();
        this.buildPilots();
        this.align();
        this.buildMap();
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.map?.remove();
    }

    colorDots() {
        const colors = new Map(this.flights.map((flight) => [String(flight.id), flight.color]));
        this.dotTargets.forEach((dot) => {
            const color = colors.get(dot.dataset.flight);
            if (color) dot.style.setProperty('--pilot-color', `rgb(${color.join(' ')})`);
        });
    }

    /* ---------- Clock ---------- */

    /** Offset of each flight: its point at session time T is T + offset. */
    align() {
        const byTask = this.alignTarget.value === 'task';
        const lead = Math.max(0, ...this.flights.map((f) => (f.taskStart ?? f.start) - f.start));
        const firstStart = Math.min(...this.flights.map((f) => f.start));
        this.flights.forEach((f) => {
            f.offset = byTask ? (f.taskStart ?? f.start) - f.start - lead : firstStart - f.start;
        });
        // Clock shown: race time, or time since the start line (negative before)
        this.clockOrigin = byTask ? -lead : firstStart;
        this.duration = Math.max(...this.flights.map((f) => f.count - 1 - f.offset));
        this.seekTarget.max = String(Math.ceil(this.duration));
    }

    setAlign() {
        const before = this.time + this.clockOrigin;
        this.align();
        this.moveTo(Math.min(this.duration, Math.max(0, before - this.clockOrigin)));
    }

    /** Second of a flight at the time of the replay; between two seconds unless `rounded`. */
    indexOf(flight, rounded = true) {
        const index = Math.min(flight.count - 1, Math.max(0, this.time + flight.offset));

        return rounded ? Math.round(index) : index;
    }

    /* ---------- Map ---------- */

    buildMap() {
        const bounds = newBounds();
        this.flights.forEach((f) => boundsOf(f.latitude, f.longitude, bounds));
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, bounds));
        this.map.on('load', () => {
            addTask(this.map, this.session.task);
            this.statusTarget.hidden = true;
            this.moveTo(0);
        });
        // The gliders keep their size on the screen
        this.map.on('zoomend', () => {
            if (!this.playing) this.render();
        });
    }

    position(flight, i, ground = false) {
        return [flight.longitude[i], flight.latitude[i], ground || !this.is3d ? 0 : flight.altitude[i]];
    }

    /** Whole tracks, computed again only when the view changes. */
    paths() {
        if (this.pathsFor !== this.is3d) {
            this.pathsFor = this.is3d;
            this.flights.forEach((f) => {
                f.path = [];
                for (let i = 0; i < f.count; i += 2) f.path.push(this.position(f, i));
            });
        }

        return this.flights;
    }

    render() {
        if (!this.overlay) return;
        const parameters = this.is3d ? {} : { depthCompare: 'always' };
        const faded = (f) => (this.selected === null || this.selected === f.id ? 110 : 40);
        const gliders = this.flights.map((flight) => ({
            track: flight,
            index: this.indexOf(flight, false),
            color: flight.color,
            label: flight.competitionId || flight.pilot,
            selected: this.selected === flight.id,
        }));
        this.overlay.setProps({
            layers: [
                new PathLayer({
                    id: 'tracks',
                    data: this.paths(),
                    getPath: (f) => f.path,
                    getColor: (f) => [...f.color, faded(f)],
                    getWidth: 1.5,
                    widthUnits: 'pixels',
                    jointRounded: true,
                    parameters,
                    updateTriggers: { getPath: this.is3d, getColor: this.selected },
                }),
                ...trailLayers('session', gliders, { is3d: this.is3d, seconds: TRAIL }),
                ...gliderLayers('session', gliders, { is3d: this.is3d, zoom: this.map.getZoom() }),
            ],
        });
    }

    setView({ currentTarget }) {
        this.is3d = currentTarget.dataset.view === '3d';
        this.viewTargets.forEach((button) => button.setAttribute('aria-pressed', String(button === currentTarget)));
        setTerrain(this.map, this.is3d);
        // The relief reads best on the photos, as long as the pilot kept the default map
        if (this.is3d && this.hasBasemapTarget && this.basemapTarget.value === 'topo' && !this.basemapChosen) {
            this.basemapTarget.value = 'satellite';
            setBasemap(this.map, 'satellite', () => this.render());
        }
        this.chase = null;
        const flight = this.flights.find((f) => f.id === this.selected) ?? this.flights[0];
        const elevation = this.is3d ? flight.altitude[this.indexOf(flight)] : 0;
        easeAt(this.map, this.is3d ? { pitch: 62, bearing: this.map.getBearing() || -20, duration: 800 } : { pitch: 0, bearing: 0, duration: 600 }, elevation);
        this.render();
    }

    setBasemap({ currentTarget }) {
        this.basemapChosen = true;
        setBasemap(this.map, currentTarget.value, () => this.render());
    }

    /* ---------- Pilots ---------- */

    buildPilots() {
        this.pilotItems = this.flights.map((flight) => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'p-session__pilot';
            button.setAttribute('aria-pressed', 'false');
            button.title = 'Suivre ce pilote';
            const dot = document.createElement('span');
            dot.className = 'p-session__dot';
            dot.style.setProperty('--pilot-color', `rgb(${flight.color.join(' ')})`);
            const name = document.createElement('span');
            name.className = 'p-session__pilot-name';
            name.textContent = flight.competitionId ? `${flight.pilot} (${flight.competitionId})` : flight.pilot;
            const values = document.createElement('span');
            values.className = 'p-session__pilot-values';
            button.append(dot, name, values);
            button.addEventListener('click', () => this.select(flight));
            li.append(button);

            return { flight, button, values, li };
        });
        this.pilotsTarget.replaceChildren(...this.pilotItems.map((item) => item.li));
    }

    select(flight) {
        this.selected = this.selected === flight.id ? null : flight.id;
        this.pilotItems.forEach((item) => item.button.setAttribute('aria-pressed', String(item.flight.id === this.selected)));
        this.chase = null;
        if (this.selected !== null) this.follow(flight, { animate: true });
        this.render();
    }

    updatePilots() {
        this.pilotItems.forEach(({ flight, values }) => {
            const i = this.indexOf(flight);
            const a = Math.max(0, i - 2);
            const b = Math.min(flight.count - 1, i + 2);
            const vario = b > a ? (flight.altitude[b] - flight.altitude[a]) / (b - a) : 0;
            const sign = vario > 0.05 ? '+' : '';
            values.textContent = `${Math.round(flight.altitude[i]).toLocaleString('fr-FR')} m · ${sign}${vario.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s · ${Math.round(flight.speed[i])} km/h`;
        });
    }

    /* ---------- Replay ---------- */

    seek() {
        this.moveTo(Number(this.seekTarget.value));
    }

    togglePlay() {
        this.playing = !this.playing;
        this.playTarget.classList.toggle('is-playing', this.playing);
        this.playTarget.setAttribute('aria-label', this.playing ? 'Mettre en pause' : 'Rejouer la session');
        if (!this.playing) return;
        if (this.time >= this.duration) this.time = 0;
        let last = performance.now();
        const step = (now) => {
            if (!this.playing) return;
            this.moveTo(Math.min(this.duration, this.time + ((now - last) / 1000) * Number(this.rateTarget.value)));
            last = now;
            if (this.time >= this.duration) {
                this.togglePlay();

                return;
            }
            this.frame = requestAnimationFrame(step);
        };
        this.frame = requestAnimationFrame(step);
    }

    moveTo(time) {
        this.time = time;
        this.seekTarget.value = String(Math.round(time));
        const clock = time + this.clockOrigin;
        this.clockTarget.textContent = `${clock < 0 ? '−' : ''}${formatTime(Math.abs(clock))}`;
        this.updatePilots();
        if (this.selected !== null && this.playing) this.follow(this.flights.find((f) => f.id === this.selected));
        this.render();
    }

    /**
     * Keeps the pilot followed in view; in 3D the camera flies behind their glider (chaseBearing()).
     */
    follow(flight, { animate = false } = {}) {
        const index = this.indexOf(flight, false);
        const [lon, lat, altitude] = positionAt(flight, index);
        if (!this.is3d) {
            this.map.jumpTo({ center: [lon, lat], elevation: 0 });

            return;
        }
        this.chase = chaseBearing(flight, index, this.chase ?? this.map.getBearing());
        if (animate) {
            easeAt(this.map, { center: [lon, lat], bearing: this.chase, pitch: 55, zoom: Math.max(this.map.getZoom(), 12.5), duration: 800 }, altitude);
        } else if (!this.map.isEasing()) {
            this.map.jumpTo({ center: [lon, lat], elevation: altitude, bearing: this.chase });
        }
    }
}
