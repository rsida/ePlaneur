/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { LineLayer, PathLayer } from '@deck.gl/layers';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';
import { bearing, distance } from '../flight/geo.js';
import { addTask, boundsOf, createMap, easeAt, formatTime, newBounds, setBasemap, setTerrain } from '../flight/map.js';
import { angleDifference, chaseBearing, gliderLayers, positionAt, readings, taskLayers, thermalLayers } from '../flight/scene.js';

/*
 * Flight viewer (templates/flight/show.html.twig): the flight of FlightController::analysis() on a
 * MapLibre map, from above or in 3D over the relief, with its charts and a replay.
 *
 * - The map comes from ../flight/map.js; in 3D the map drapes its tiles on the relief and the track
 *   is drawn by deck.gl at its real altitude, with lines down to the ground every few seconds.
 * - The task is drawn by the map itself, so it lies on the relief.
 * - Charts (uPlot) share the cursor with the map; clicking a chart, the seek bar or a phase moves
 *   the glider there.
 * - The glider is drawn turned and banked with its flag and readings (../flight/scene.js); in 3D
 *   the cylinders of the task stand up and the thermals climbed so far rise as columns. "Suivre"
 *   keeps the glider in view, and in 3D flies the camera behind it.
 * - "Vue pilote": in 3D, the camera sits in the glider, looks where it goes and banks with it.
 * - A moment of the flight has an address (#t=seconds), copied with "Lien vers cet instant".
 * - Two thermals picked in the list are compared: figures and the height gained over time.
 */

/* Track colors by vario class, from strong sink to strong climb */
const VARIO_CLASSES = [
    { max: -2, color: [214, 69, 65] },
    { max: -0.5, color: [240, 150, 70] },
    { max: 0.5, color: [62, 93, 131] },
    { max: 2, color: [92, 176, 112] },
    { max: Infinity, color: [36, 140, 72] },
];
// Track coloured by altitude: from the lowest to the highest point of the flight, as on Condor Club
const ALTITUDE_COLORS = [[94, 60, 153], [59, 82, 170], [44, 123, 182], [0, 166, 202], [0, 196, 160], [144, 220, 120], [249, 208, 87], [242, 135, 50], [215, 48, 39]];
const CURTAIN_STEP = 5; // seconds between the lines down to the ground in 3D
// Pilot view: looking ahead and a little down (fewer far tiles than the horizon, which MapLibre
// draws with stretched walls while they load)
const PILOT_PITCH = 75;
// Metres the pilot view keeps above the relief of the map
const PILOT_GROUND_CLEARANCE = 30;

export default class extends Controller {
    static targets = ['map', 'view', 'basemap', 'follow', 'pilot', 'colors', 'legend', 'status', 'nowTime', 'nowAltitude', 'nowSpeed', 'nowVario', 'nowPhase', 'phases', 'play', 'rate', 'seek', 'chart', 'commentAt', 'commentTime', 'momentStatus', 'thermals', 'thermalTable', 'thermalChart', 'thermalHint', 'marks'];

    static values = { url: String, label: String };

    async connect() {
        this.index = 0;
        this.playing = false;
        this.is3d = false;
        this.following = false;
        this.pilotView = false;
        this.compared = [];
        this.charts = [];
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.flight = await response.json();
        } catch {
            this.statusTarget.textContent = 'Le vol n’a pas pu être chargé.';

            return;
        }
        this.series = this.flight.series;
        this.count = this.series.time.length;
        this.seekTarget.max = String(this.count - 1);
        this.phaseAt = this.phaseIndex();
        this.thermalPhases = this.flight.phases.filter((phase) => phase.type === 'thermal');
        this.top = this.series.altitude.reduce((top, altitude) => Math.max(top, altitude), 0);
        this.buildMap();
        this.buildCharts();
        this.buildPhases();
        this.buildMarks();
        this.resizeObserver = new ResizeObserver(() => this.resizeCharts());
        this.resizeObserver.observe(this.element);
        // A link to a moment of the flight: #t=seconds
        const moment = /^#t=(\d+)$/.exec(window.location.hash);
        this.moveTo(moment ? Math.min(this.count - 1, Number(moment[1])) : (this.flight.statistics.takeoff ?? 0));
        if (moment) this.map.once('load', () => this.centerOnGlider());
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.resizeObserver?.disconnect();
        this.charts.forEach((chart) => chart.destroy());
        this.thermalPlot?.destroy();
        this.map?.remove();
    }

    /* ---------- Map ---------- */

    buildMap() {
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, boundsOf(this.series.latitude, this.series.longitude)));
        this.map.on('load', () => {
            addTask(this.map, this.flight.task);
            this.trackSegments = this.segments();
            this.showLegend();
            this.render();
            this.statusTarget.hidden = true;
        });
        // The glider keeps its size on the screen
        this.map.on('zoomend', () => {
            if (!this.playing) this.render();
        });
    }

    /** Colour mode of the track chosen above the map: vario (default) or altitude. */
    get colorMode() {
        return this.hasColorsTarget ? this.colorsTarget.value : 'vario';
    }

    setColors() {
        this.trackSegments = this.segments();
        this.showLegend();
        this.render();
    }

    /** Lowest and highest altitude of the flight, for the altitude colours and their legend. */
    altitudeRange() {
        const { altitude } = this.series;
        const { takeoff = 0, landing = this.count - 1 } = this.flight.statistics;
        const flying = altitude.slice(takeoff, landing + 1);

        return [Math.min(...flying), Math.max(...flying)];
    }

    showLegend() {
        if (!this.hasLegendTarget) return;
        const altitude = this.colorMode === 'altitude';
        const rgb = (c) => `rgb(${c.join(',')})`;
        const colors = altitude ? ALTITUDE_COLORS : VARIO_CLASSES.map((c) => c.color);
        const [low, high] = this.altitudeRange();
        this.legendTarget.querySelector('[data-legend="bar"]').style.background = `linear-gradient(to right, ${colors.map(rgb).join(', ')})`;
        this.legendTarget.querySelector('[data-legend="low"]').textContent = altitude ? `${Math.round(low)} m` : '< −2 m/s';
        this.legendTarget.querySelector('[data-legend="high"]').textContent = altitude ? `${Math.round(high)} m` : '> +2 m/s';
    }

    /** Runs of the track of the same colour class (vario or altitude), drawn as one path each. */
    segments() {
        const { vario, altitude } = this.series;
        const [low, high] = this.altitudeRange();
        const byAltitude = this.colorMode === 'altitude';
        const classes = byAltitude ? ALTITUDE_COLORS : VARIO_CLASSES.map((c) => c.color);
        const classOf = byAltitude
            ? (i) => Math.max(0, Math.min(classes.length - 1, Math.floor(((altitude[i] - low) / Math.max(1, high - low)) * classes.length)))
            : (i) => VARIO_CLASSES.findIndex((c) => vario[i] <= c.max);
        const segments = [];
        let current = null;
        for (let i = 0; i < this.count; i += 1) {
            const cls = classOf(i);
            if (!current || current.cls !== cls) {
                // Each segment starts at the end of the previous one: no gap in the line
                current = { cls, color: classes[cls], indexes: current ? [i - 1, i] : [i] };
                segments.push(current);
            } else {
                current.indexes.push(i);
            }
        }

        return segments;
    }

    position(i, ground = false) {
        const { latitude, longitude, altitude } = this.series;

        return [longitude[i], latitude[i], ground || !this.is3d ? 0 : altitude[i]];
    }

    render() {
        if (!this.trackSegments) return;
        // From above, everything lies at altitude 0 like the map tiles: no depth test, or they fight.
        // In 3D the relief hides the track behind it, the glider always shows, and the white casing
        // is left out (at the same depth as the track, it would hide parts of it).
        const parameters = this.is3d ? {} : { depthCompare: 'always' };
        const layers = [
            new PathLayer({
                id: 'track-casing',
                visible: !this.is3d,
                data: this.trackSegments,
                getPath: (segment) => segment.indexes.map((k) => this.position(k)),
                getColor: [255, 255, 255, 220],
                getWidth: 5,
                widthUnits: 'pixels',
                capRounded: true,
                jointRounded: true,
                parameters,
                updateTriggers: { getPath: this.is3d },
            }),
            new PathLayer({
                id: 'track',
                data: this.trackSegments,
                getPath: (segment) => segment.indexes.map((k) => this.position(k)),
                getColor: (segment) => segment.color,
                // From the glider, a ribbon of 4 m: a width in pixels would fill the view up close
                getWidth: this.pilotView ? 4 : 3,
                widthUnits: this.pilotView ? 'meters' : 'pixels',
                widthMinPixels: this.pilotView ? 1.5 : 0,
                capRounded: true,
                jointRounded: true,
                parameters,
                updateTriggers: { getPath: this.is3d },
            }),
        ];
        // In the pilot view the camera is in the glider
        if (!this.pilotView) {
            const [lon, lat] = positionAt(this.series, this.index);
            const ground = this.is3d ? this.map.queryTerrainElevation([lon, lat]) : null;
            const details = readings(this.series, this.index, ground);
            layers.push(...gliderLayers('flight', [{ track: this.series, index: this.index, color: [255, 255, 255], flagColor: [25, 38, 48], label: this.labelValue, details, selected: false }], { is3d: this.is3d, zoom: this.map.getZoom() }));
        }
        if (this.is3d) {
            layers.unshift(
                ...taskLayers('flight', this.flight.task, { top: this.top }),
                ...thermalLayers('flight', this.series, this.thermalPhases, this.index),
            );
            // Lines down to the ground: the relief hides their lower part
            if (!this.curtain) {
                this.curtain = [];
                for (let k = 0; k < this.count; k += CURTAIN_STEP) this.curtain.push(k);
            }
            layers.unshift(new LineLayer({
                id: 'curtain',
                // Seen from the glider they become bars across the view
                visible: !this.pilotView,
                data: this.curtain,
                getSourcePosition: (k) => this.position(k),
                getTargetPosition: (k) => this.position(k, true),
                getColor: [25, 38, 48, 70],
                getWidth: 1,
            }));
        }
        this.overlay.setProps({ layers });
    }

    setView({ currentTarget }) {
        if (this.pilotView) this.togglePilot();
        this.show3d(currentTarget.dataset.view === '3d');
    }

    show3d(on, { animate = true } = {}) {
        this.is3d = on;
        this.viewTargets.forEach((button) => button.setAttribute('aria-pressed', String((button.dataset.view === '3d') === on)));
        setTerrain(this.map, on);
        // The relief reads best on the photos, as long as the pilot kept the default map
        if (on && this.hasBasemapTarget && this.basemapTarget.value === 'topo' && !this.basemapChosen) {
            this.basemapTarget.value = 'satellite';
            setBasemap(this.map, 'satellite', () => this.render());
        }
        this.chase = null;
        if (animate && on) {
            easeAt(this.map, { pitch: 62, bearing: this.map.getBearing() || -20, roll: 0, duration: 800 }, this.series.altitude[Math.round(this.index)]);
        } else if (animate) {
            easeAt(this.map, { pitch: 0, bearing: 0, roll: 0, duration: 600 }, 0);
        }
        this.render();
    }

    setBasemap({ currentTarget }) {
        this.basemapChosen = true;
        setBasemap(this.map, currentTarget.value, () => this.render());
    }

    toggleFollow() {
        this.following = !this.following;
        this.followTarget.setAttribute('aria-pressed', String(this.following));
        if (!this.following) return;
        if (this.is3d) {
            // Close enough to see the glider turn
            const [lon, lat, altitude] = this.position(Math.round(this.index));
            this.chase = null;
            easeAt(this.map, { center: [lon, lat], zoom: Math.max(this.map.getZoom(), 12.5), pitch: 55, duration: 800 }, altitude);
        } else {
            this.centerOnGlider();
        }
    }

    centerOnGlider() {
        // Let the camera finish flying to the glider
        if (this.map.isEasing()) return;
        const [lon, lat, altitude] = this.position(Math.round(this.index));
        if (this.is3d && this.playing) {
            // Behind the glider, turning with its course
            this.chase = chaseBearing(this.series, this.index, this.chase ?? this.map.getBearing());
            this.map.jumpTo({ center: [lon, lat], elevation: altitude, bearing: this.chase });
        } else {
            this.map.jumpTo({ center: [lon, lat], elevation: altitude });
        }
    }

    /* ---------- Pilot view ---------- */

    togglePilot() {
        this.pilotView = !this.pilotView;
        this.pilotTarget.setAttribute('aria-pressed', String(this.pilotView));
        this.camera = null;
        if (this.pilotView) {
            if (!this.is3d) this.show3d(true, { animate: false });
            // Real time or close to it: faster, the view turns too quickly to follow
            if (Number(this.rateTarget.value) > 3) this.rateTarget.value = '2';
            this.pilotCamera();
        } else {
            this.bankCanvas(0);
            const [lon, lat, altitude] = this.position(Math.round(this.index));
            easeAt(this.map, { center: [lon, lat], pitch: 62, zoom: 12, duration: 800 }, altitude);
        }
        this.render();
    }

    /**
     * Camera in the glider: at its position between two seconds of the series, looking along its
     * track, the picture banked like the glider in a turn (tan(bank) = v·ω / g). Heading and bank are
     * interpolated between two seconds, then smoothed from one frame to the next.
     *
     * The bank turns the canvas of the map, not the camera: deck.gl follows the bearing and pitch of
     * the map but not its roll, so a rolled camera would draw the track off the relief.
     */
    pilotCamera() {
        const { latitude, longitude, altitude, speed } = this.series;
        const last = this.count - 1;
        const a = Math.floor(this.index);
        const b = Math.min(last, a + 1);
        const f = this.index - a;
        const lerp = (values) => values[a] + (values[b] - values[a]) * f;
        const heading = (k) => {
            const from = Math.max(0, k - 2);
            const to = Math.min(last, k + 2);

            return bearing(latitude[from], longitude[from], latitude[to], longitude[to]);
        };
        // Turn to the right positive, in degrees per second
        const turnRate = (k) => angleDifference(heading(Math.max(0, k - 1)), heading(Math.min(last, k + 1))) / 2;
        const bankAt = (k) => {
            const omega = turnRate(k) * Math.PI / 180;

            return Math.max(-60, Math.min(60, Math.atan((speed[k] / 3.6) * omega / 9.81) * 180 / Math.PI));
        };
        const target = {
            heading: heading(a) + angleDifference(heading(a), heading(b)) * f,
            bank: bankAt(a) + (bankAt(b) - bankAt(a)) * f,
        };
        // Smoothing: a quarter of the way to the target at each frame
        this.camera = this.camera
            ? { heading: this.camera.heading + angleDifference(this.camera.heading, target.heading) * 0.25, bank: this.camera.bank + (target.bank - this.camera.bank) * 0.25 }
            : target;
        // The relief of the map is the real one, not Condor's: low over a ridge the glider can be
        // under it by a few tens of metres, and the camera would see the inside of the mountain
        const position = [lerp(longitude), lerp(latitude)];
        const ground = this.map.queryTerrainElevation(position) ?? -Infinity;
        const camera = this.map.calculateCameraOptionsFromCameraLngLatAltRotation(position, Math.max(lerp(altitude) + 1.5, ground + PILOT_GROUND_CLEARANCE), (this.camera.heading + 360) % 360, PILOT_PITCH, 0);
        this.map.jumpTo(camera);
        this.bankCanvas(this.camera.bank);
    }

    /** Turns the picture of the map: banked right, the horizon turns to the left. */
    bankCanvas(bank) {
        const canvas = this.map.getCanvasContainer();
        // Enlarged so the corners stay covered
        canvas.style.transform = bank ? `rotate(${-bank}deg) scale(${1 + Math.abs(Math.sin(bank * Math.PI / 180)) * 0.9})` : '';
    }

    /* ---------- Link to a moment ---------- */

    async copyMoment() {
        const i = Math.round(this.index);
        const url = `${window.location.origin}${window.location.pathname}#t=${i}`;
        window.history.replaceState(null, '', `#t=${i}`);
        try {
            await navigator.clipboard.writeText(url);
            this.momentStatusTarget.textContent = `Lien vers ${formatTime(this.series.time[i])} copié`;
        } catch {
            this.momentStatusTarget.textContent = url;
        }
    }

    /* ---------- Charts ---------- */

    buildCharts() {
        const sync = uPlot.sync('flight');
        const time = this.series.time;
        const thermals = this.flight.phases.filter((phase) => phase.type === 'thermal');
        const settings = {
            altitude: { stroke: '#3e5d83', fill: 'rgba(62, 93, 131, 0.12)', label: 'Altitude', unit: 'm' },
            vario: { stroke: '#2f6b4f', label: 'Vario', unit: 'm/s' },
            speed: { stroke: '#a85b2a', label: 'Vitesse sol', unit: 'km/h' },
        };

        this.charts = this.chartTargets.map((target) => {
            const name = target.dataset.series;
            // Under the vario, the netto of the glides when the polar of the glider is known
            const netto = name === 'vario' && this.series.netto ? this.series.netto : null;
            const options = {
                width: target.clientWidth || 600,
                height: 130,
                cursor: { sync: { key: sync.key }, drag: { x: false, y: false }, points: { size: 6 } },
                legend: { show: false },
                scales: { x: { time: false } },
                axes: [
                    { values: (u, ticks) => ticks.map((t) => formatTime(t)), size: 28, font: '11px Inter, sans-serif' },
                    { size: 46, font: '11px Inter, sans-serif' },
                ],
                series: [{}, { ...settings[name], width: 1.5, points: { show: false } }, ...(netto ? [{ stroke: '#a85b2a', width: 1.5, dash: [4, 3], points: { show: false } }] : [])],
                plugins: [valueTip((u, i) => [
                    `${FORMATS[name](u.data[1][i])} · ${formatTime(u.data[0][i])}`,
                    ...(netto && u.data[2][i] != null ? [{ text: `Netto ${FORMATS.vario(u.data[2][i])}`, color: '#a85b2a' }] : []),
                ])],
                hooks: {
                    // Thermals shaded behind the altitude
                    drawClear: name === 'altitude' ? [(u) => {
                        const { ctx } = u;
                        ctx.save();
                        ctx.fillStyle = 'rgba(92, 176, 112, 0.16)';
                        for (const phase of thermals) {
                            const left = u.valToPos(time[phase.start], 'x', true);
                            const right = u.valToPos(time[phase.end], 'x', true);
                            ctx.fillRect(left, u.bbox.top, right - left, u.bbox.height);
                        }
                        ctx.restore();
                    }] : [],
                    setCursor: [(u) => {
                        if (u.cursor.idx != null && !this.playing && this.hovering === u) this.moveTo(u.cursor.idx, { fromChart: true });
                    }],
                },
            };
            if (name === 'vario') options.scales.y = { range: (u, min, max) => [Math.min(min, -4), Math.max(max, 4)] };
            const chart = new uPlot(options, netto ? [time, this.series[name], netto] : [time, this.series[name]], target);
            sync.sub(chart);
            chart.over.addEventListener('mouseenter', () => { this.hovering = chart; });
            chart.over.addEventListener('mouseleave', () => { this.hovering = null; });
            chart.over.addEventListener('click', () => {
                if (chart.cursor.idx != null) this.moveTo(chart.cursor.idx);
            });

            return chart;
        });
    }

    resizeCharts() {
        this.charts.forEach((chart, k) => chart.setSize({ width: this.chartTargets[k].clientWidth, height: 130 }));
    }

    /* ---------- Phases ---------- */

    /** Index of the phase of each point of the series. */
    phaseIndex() {
        const at = new Array(this.count).fill(-1);
        this.flight.phases.forEach((phase, p) => {
            for (let i = phase.start; i <= phase.end; i += 1) at[i] = p;
        });

        return at;
    }

    buildPhases() {
        const items = this.flight.phases.map((phase, p) => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `p-flight__phase-item p-flight__phase-item--${phase.type}`;
            button.dataset.phase = String(p);
            button.textContent = describePhase(phase);
            button.addEventListener('click', () => this.showPhase(p));
            li.append(button);
            if (phase.type === 'thermal') {
                const pick = document.createElement('button');
                pick.type = 'button';
                pick.className = 'p-flight__phase-pick';
                pick.dataset.pick = String(p);
                pick.setAttribute('aria-pressed', 'false');
                pick.title = 'Comparer ce thermique avec un autre';
                pick.textContent = 'Comparer';
                pick.addEventListener('click', () => this.pickThermal(p));
                li.append(pick);
            }

            return li;
        });
        this.phasesTarget.replaceChildren(...items);
    }

    showPhase(p) {
        const phase = this.flight.phases[p];
        const bounds = newBounds();
        let altitude = 0;
        for (let i = phase.start; i <= phase.end; i += 1) {
            bounds.extend([this.series.longitude[i], this.series.latitude[i]]);
            altitude += this.series.altitude[i] / (phase.end - phase.start + 1);
        }
        if (this.is3d) {
            // Framed from the side, around the phase at its own altitude
            const camera = this.map.cameraForBounds(bounds, { padding: 80, maxZoom: 14 });
            easeAt(this.map, { center: bounds.getCenter(), zoom: Math.min(camera?.zoom ?? 12, 14) - 0.6, pitch: 62, duration: 900 }, altitude);
        } else {
            this.map.fitBounds(bounds, { padding: 80, maxZoom: 14, duration: 600 });
        }
        this.moveTo(phase.start);
    }

    /* ---------- Two thermals side by side ---------- */

    pickThermal(p) {
        this.compared = this.compared.includes(p) ? this.compared.filter((q) => q !== p) : [...this.compared, p].slice(-2);
        this.phasesTarget.querySelectorAll('[data-pick]').forEach((pick) => pick.setAttribute('aria-pressed', String(this.compared.includes(Number(pick.dataset.pick)))));
        this.thermalsTarget.hidden = this.compared.length === 0;
        this.thermalHintTarget.hidden = this.compared.length === 2;
        this.thermalPlot?.destroy();
        this.thermalPlot = null;
        this.thermalTableTarget.replaceChildren();
        if (this.compared.length < 2) return;

        const [a, b] = this.compared.map((q) => this.describeThermal(q));
        const style = getComputedStyle(this.element);
        const colors = [style.getPropertyValue('--color-pilot-a').trim(), style.getPropertyValue('--color-pilot-b').trim()];
        const rows = [
            ['Début', (t) => formatTime(t.time)],
            ['Durée', (t) => formatTime(t.duration)],
            ['Gain', (t) => `${t.gain >= 0 ? '+' : ''}${Math.round(t.gain).toLocaleString('fr-FR')} m`],
            ['Vario moyen', (t) => FORMATS.vario(t.climb)],
            ['Meilleures 30 s', (t) => FORMATS.vario(t.best)],
            ['Altitude', (t) => `${FORMATS.altitude(t.from)} → ${FORMATS.altitude(t.to)}`],
            ['Rayon de spirale', (t) => (t.radius ? `${Math.round(t.radius)} m` : '—')],
            ['Inclinaison', (t) => (t.bank ? `${Math.round(t.bank)}°` : '—')],
            ['Sens', (t) => (t.direction === 'left' ? 'à gauche' : 'à droite')],
            ['Dérive (vent)', (t) => `${Math.round(t.drift)} km/h`],
        ];
        const table = document.createElement('table');
        const head = table.createTHead().insertRow();
        ['', `Thermique ${a.number}`, `Thermique ${b.number}`].forEach((text, n) => {
            const th = document.createElement('th');
            th.scope = 'col';
            if (n > 0) {
                const key = document.createElement('span');
                key.className = 'p-flight__thermal-key';
                key.style.backgroundColor = colors[n - 1];
                th.append(key);
            }
            th.append(text);
            head.append(th);
        });
        const body = table.createTBody();
        rows.forEach(([label, value]) => {
            const row = body.insertRow();
            const th = document.createElement('th');
            th.scope = 'row';
            th.textContent = label;
            row.append(th);
            row.insertCell().textContent = value(a);
            row.insertCell().textContent = value(b);
        });
        this.thermalTableTarget.append(table);

        // Height gained since the entry of each thermal, second by second
        const length = Math.max(a.gains.length, b.gains.length);
        const x = Array.from({ length }, (_, t) => t);
        const pad = (gains) => x.map((t) => (t < gains.length ? gains[t] : null));
        const line = (thermal, color, t) => ({ text: `Thermique ${thermal.number} : ${t < thermal.gains.length ? `${thermal.gains[t] >= 0 ? '+' : ''}${Math.round(thermal.gains[t])} m` : 'sorti'}`, color });
        this.thermalPlot = new uPlot({
            width: this.thermalChartTarget.clientWidth || 600,
            height: 180,
            legend: { show: false },
            cursor: { drag: { x: false, y: false }, points: { size: 6 } },
            scales: { x: { time: false } },
            axes: [
                { values: (u, ticks) => ticks.map((t) => formatTime(t)), size: 28, font: '11px Inter, sans-serif' },
                { size: 46, font: '11px Inter, sans-serif' },
            ],
            series: [{}, { stroke: colors[0], width: 2, points: { show: false } }, { stroke: colors[1], width: 2, points: { show: false } }],
            plugins: [valueTip((u, t) => [`${formatTime(t)} après l’entrée`, line(a, colors[0], t), line(b, colors[1], t)])],
        }, [x, pad(a.gains), pad(b.gains)], this.thermalChartTarget);
    }

    /** Figures of a thermal: climbs, radius and bank from the turn rate, drift of the circles. */
    describeThermal(p) {
        const phase = this.flight.phases[p];
        const { time, latitude, longitude, altitude, speed } = this.series;
        const number = this.flight.phases.slice(0, p + 1).filter((other) => other.type === 'thermal').length;
        let turned = 0;
        let previous = null;
        for (let i = phase.start + 1; i <= phase.end; i += 1) {
            const now = bearing(latitude[i - 1], longitude[i - 1], latitude[i], longitude[i]);
            if (previous !== null) turned += Math.abs(((now - previous + 540) % 360) - 180);
            previous = now;
        }
        const duration = phase.end - phase.start;
        const omega = duration > 0 ? (turned / duration) * Math.PI / 180 : 0;
        let meanSpeed = 0;
        for (let i = phase.start; i <= phase.end; i += 1) meanSpeed += speed[i] / 3.6 / (duration + 1);
        let best = -Infinity;
        for (let i = phase.start; i + 30 <= phase.end; i += 1) best = Math.max(best, (altitude[i + 30] - altitude[i]) / 30);
        // Drift: from the centre of the first circle to the centre of the last one
        const circle = omega > 0 ? Math.min(duration, Math.round((2 * Math.PI) / omega)) : duration;
        const centre = (from) => {
            let lat = 0;
            let lon = 0;
            for (let i = from; i < from + circle; i += 1) {
                lat += latitude[i] / circle;
                lon += longitude[i] / circle;
            }

            return [lat, lon];
        };
        const [lat1, lon1] = centre(phase.start);
        const [lat2, lon2] = centre(phase.end - circle);
        const drift = duration > circle ? (distance(lat1, lon1, lat2, lon2) / (duration - circle)) * 3.6 : 0;

        return {
            number,
            time: time[phase.start],
            duration,
            gain: phase.gain,
            climb: phase.vario,
            best: best === -Infinity ? phase.vario : best,
            from: altitude[phase.start],
            to: altitude[phase.end],
            radius: omega > 0 ? meanSpeed / omega : null,
            bank: omega > 0 ? Math.atan((meanSpeed * omega) / 9.81) * 180 / Math.PI : null,
            direction: phase.direction,
            drift,
            gains: Array.from({ length: duration + 1 }, (_, t) => altitude[phase.start + t] - altitude[phase.start]),
        };
    }

    /* ---------- Turnpoints on the timeline ---------- */

    /** A tick above the slider at each turnpoint reached (start, turnpoints, finish). */
    buildMarks() {
        if (!this.hasMarksTarget || !this.flight.task) return;
        const points = this.flight.task.turnpoints.slice(1);
        const marks = points.flatMap((point, n) => {
            if (point.reached === null || point.reached === undefined) return [];
            const mark = document.createElement('button');
            mark.type = 'button';
            mark.className = 'p-flight__mark';
            mark.style.left = `${(point.reached / Math.max(1, this.count - 1)) * 100}%`;
            const name = n === 0 ? 'Départ' : n === points.length - 1 ? 'Arrivée' : `Point ${n}`;
            mark.title = `${name} · ${point.name} · ${formatTime(this.series.time[point.reached])}`;
            mark.setAttribute('aria-label', mark.title);
            mark.addEventListener('click', () => this.goToMoment({ detail: { at: point.reached } }));

            return [mark];
        });
        this.marksTarget.replaceChildren(...marks);
    }

    /* ---------- Comments ---------- */

    /** A moment given by another controller (a glide clicked on the polar chart). */
    goToMoment({ detail: { at } }) {
        this.goTo({ params: { at } });
    }

    /** A comment's moment: the glider there, the map in view. */
    goTo({ params: { at } }) {
        this.moveTo(Math.min(this.count - 1, Number(at)));
        if (this.following) this.centerOnGlider();
        else {
            const [lon, lat, altitude] = this.position(Math.round(this.index));
            this.map.jumpTo({ center: [lon, lat], elevation: altitude });
        }
        this.mapTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /* ---------- Replay ---------- */

    seek() {
        this.moveTo(Number(this.seekTarget.value));
    }

    togglePlay() {
        this.playing = !this.playing;
        this.playTarget.classList.toggle('is-playing', this.playing);
        this.playTarget.setAttribute('aria-label', this.playing ? 'Mettre en pause' : 'Rejouer le vol');
        if (!this.playing) return;
        if (this.index >= this.count - 1) this.index = 0;
        let last = performance.now();
        const step = (now) => {
            if (!this.playing) return;
            this.index = Math.min(this.count - 1, this.index + ((now - last) / 1000) * Number(this.rateTarget.value));
            last = now;
            this.moveTo(this.index, { keepFraction: true });
            if (this.index >= this.count - 1) {
                this.togglePlay();

                return;
            }
            this.frame = requestAnimationFrame(step);
        };
        this.frame = requestAnimationFrame(step);
    }

    moveTo(index, { keepFraction = false, fromChart = false } = {}) {
        this.index = keepFraction ? index : Math.round(index);
        const i = Math.round(this.index);
        const { time, altitude, speed, vario } = this.series;
        this.seekTarget.value = String(i);
        this.nowTimeTarget.textContent = formatTime(time[i]);
        // The comment form takes the moment shown
        if (this.hasCommentAtTarget) this.commentAtTarget.value = String(i);
        if (this.hasCommentTimeTarget) this.commentTimeTarget.textContent = formatTime(time[i]);
        this.nowAltitudeTarget.textContent = FORMATS.altitude(altitude[i]);
        this.nowSpeedTarget.textContent = FORMATS.speed(speed[i]);
        this.nowVarioTarget.textContent = FORMATS.vario(vario[i]);
        const p = this.phaseAt[i];
        this.nowPhaseTarget.textContent = p >= 0 ? describePhase(this.flight.phases[p]) : '';
        this.phasesTarget.querySelectorAll('[aria-current]').forEach((item) => item.removeAttribute('aria-current'));
        this.phasesTarget.querySelector(`[data-phase="${p}"]`)?.setAttribute('aria-current', 'true');
        if (!fromChart) {
            this.charts.forEach((chart) => chart.setCursor({ left: chart.valToPos(time[i], 'x'), top: chart.valToPos(chart.data[1][i], 'y') }, false));
        }
        if (this.pilotView && this.map) {
            // Smoothed during the replay only: a jump shows the new place at once
            if (!this.playing) this.camera = null;
            this.pilotCamera();
        }
        else if (this.following && this.map) this.centerOnGlider();
        this.render();
    }
}



/** One line describing a thermal, a glide or straight lift (slope, wave). */
function describePhase(phase) {
    const minutes = Math.max(1, Math.round(phase.duration / 60));
    const number = (value, digits = 1) => value.toLocaleString('fr-FR', { maximumFractionDigits: digits });
    if (phase.type === 'thermal') {
        return `Thermique · ${minutes} min · ${phase.gain >= 0 ? '+' : ''}${phase.gain} m · ${number(phase.vario)} m/s · ${phase.direction === 'left' ? 'à gauche' : 'à droite'}`;
    }

    if (phase.type === 'lift') {
        return `Pente ou onde · ${minutes} min · +${phase.gain} m · ${number(phase.vario)} m/s en ligne droite`;
    }

    return `Transition · ${number(phase.distance / 1000)} km · ${Math.round(phase.speed)} km/h${phase.glideRatio ? ` · finesse ${Math.round(phase.glideRatio)}` : ''}`;
}
