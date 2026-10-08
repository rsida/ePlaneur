/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { LineLayer, PathLayer, ScatterplotLayer, TextLayer } from '@deck.gl/layers';
import { addTask, boundsOf, createMap, easeAt, formatTime, newBounds, setBasemap, setTerrain } from '../flight/map.js';

/*
 * Session replay (templates/flight/session.html.twig): every flight of FlightController::sessionData()
 * on one map, one color per pilot, replayed together.
 *
 * Two ways to put the flights on one clock:
 * - "race": each recording from its beginning (in a network race every pilot starts at once);
 * - "task": every pilot crosses the start line at the same moment, to compare the tasks flown on
 *   different days or with different start times like a ghost race.
 */

/* One color per pilot, in ranking order */
const COLORS = [
    [214, 69, 65], [62, 93, 131], [36, 140, 72], [168, 91, 42], [123, 79, 160],
    [0, 150, 160], [200, 160, 0], [220, 90, 160], [90, 90, 90], [120, 160, 40],
];
const TRAIL = 120; // seconds of track drawn behind each glider

export default class extends Controller {
    static targets = ['map', 'view', 'align', 'status', 'clock', 'pilots', 'dot', 'play', 'rate', 'seek'];

    static values = { url: String };

    async connect() {
        this.time = 0;
        this.playing = false;
        this.is3d = false;
        this.selected = null;
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

    indexOf(flight) {
        return Math.min(flight.count - 1, Math.max(0, Math.round(this.time + flight.offset)));
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
        const always = { depthCompare: 'always' };
        const faded = (f) => (this.selected === null || this.selected === f.id ? 170 : 50);
        const now = this.flights.map((f) => ({ flight: f, i: this.indexOf(f) }));
        const trails = now.map(({ flight, i }) => {
            const path = [];
            for (let k = Math.max(0, i - TRAIL); k <= i; k += 1) path.push(this.position(flight, k));

            return { flight, path };
        });
        const layers = [
            new PathLayer({
                id: 'tracks',
                data: this.paths(),
                getPath: (f) => f.path,
                getColor: (f) => [...f.color, faded(f)],
                getWidth: 2.5,
                widthUnits: 'pixels',
                jointRounded: true,
                parameters,
                updateTriggers: { getPath: this.is3d, getColor: this.selected },
            }),
            new PathLayer({
                id: 'trails',
                data: trails,
                getPath: (trail) => trail.path,
                getColor: (trail) => trail.flight.color,
                getWidth: (trail) => (this.selected === trail.flight.id ? 5 : 3.5),
                widthUnits: 'pixels',
                capRounded: true,
                jointRounded: true,
                parameters,
            }),
            new ScatterplotLayer({
                id: 'gliders',
                data: now,
                getPosition: ({ flight, i }) => this.position(flight, i),
                getRadius: ({ flight }) => (this.selected === flight.id ? 8 : 6),
                radiusUnits: 'pixels',
                getFillColor: ({ flight }) => flight.color,
                getLineColor: [255, 255, 255],
                lineWidthMinPixels: 2,
                stroked: true,
                parameters: always,
            }),
            new TextLayer({
                id: 'labels',
                data: now,
                getPosition: ({ flight, i }) => this.position(flight, i),
                getText: ({ flight }) => flight.competitionId || flight.pilot,
                characterSet: 'auto',
                getSize: 12,
                getColor: [25, 38, 48],
                getPixelOffset: [10, -10],
                getTextAnchor: 'start',
                fontFamily: 'Inter, sans-serif',
                fontWeight: 600,
                background: true,
                getBackgroundColor: [255, 255, 255, 220],
                backgroundPadding: [4, 2],
                parameters: always,
            }),
        ];
        if (this.is3d) {
            layers.push(new LineLayer({
                id: 'stems',
                data: now,
                getSourcePosition: ({ flight, i }) => this.position(flight, i),
                getTargetPosition: ({ flight, i }) => this.position(flight, i, true),
                getColor: ({ flight }) => [...flight.color, 200],
                getWidth: 2,
            }));
        }
        this.overlay.setProps({ layers });
    }

    setView({ currentTarget }) {
        this.is3d = currentTarget.dataset.view === '3d';
        this.viewTargets.forEach((button) => button.setAttribute('aria-pressed', String(button === currentTarget)));
        setTerrain(this.map, this.is3d);
        const flight = this.flights.find((f) => f.id === this.selected) ?? this.flights[0];
        const elevation = this.is3d ? flight.altitude[this.indexOf(flight)] : 0;
        easeAt(this.map, this.is3d ? { pitch: 62, bearing: this.map.getBearing() || -20, duration: 800 } : { pitch: 0, bearing: 0, duration: 600 }, elevation);
        this.render();
    }

    setBasemap({ currentTarget }) {
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
            button.title = 'Centrer la carte sur ce pilote';
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
        if (this.selected !== null) {
            const i = this.indexOf(flight);
            this.map.jumpTo({ center: [flight.longitude[i], flight.latitude[i]], elevation: this.is3d ? flight.altitude[i] : 0 });
        }
        this.render();
    }

    updatePilots() {
        this.pilotItems.forEach(({ flight, values }) => {
            const i = this.indexOf(flight);
            const a = Math.max(0, i - 2);
            const b = Math.min(flight.count - 1, i + 2);
            const vario = b > a ? (flight.altitude[b] - flight.altitude[a]) / (b - a) : 0;
            const sign = vario > 0.05 ? '+' : '';
            values.textContent = `${Math.round(flight.altitude[i]).toLocaleString('fr-FR')} m · ${sign}${vario.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s`;
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
        if (this.selected !== null && this.playing) {
            const flight = this.flights.find((f) => f.id === this.selected);
            const i = this.indexOf(flight);
            this.map.jumpTo({ center: [flight.longitude[i], flight.latitude[i]], elevation: this.is3d ? flight.altitude[i] : 0 });
        }
        this.render();
    }
}
