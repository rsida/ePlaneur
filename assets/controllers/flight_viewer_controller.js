/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { LineLayer, PathLayer, ScatterplotLayer } from '@deck.gl/layers';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';
import { addTask, boundsOf, createMap, easeAt, formatTime, newBounds, setBasemap, setTerrain } from '../flight/map.js';

/*
 * Flight viewer (templates/flight/show.html.twig): the flight of FlightController::analysis() on a
 * MapLibre map, from above or in 3D over the relief, with its charts and a replay.
 *
 * - The map comes from ../flight/map.js; in 3D the map drapes its tiles on the relief and the track
 *   is drawn by deck.gl at its real altitude, with lines down to the ground every few seconds.
 * - The task is drawn by the map itself, so it lies on the relief.
 * - Charts (uPlot) share the cursor with the map; clicking a chart, the seek bar or a phase moves
 *   the glider there.
 */

/* Track colors by vario class, from strong sink to strong climb */
const VARIO_CLASSES = [
    { max: -2, color: [214, 69, 65] },
    { max: -0.5, color: [240, 150, 70] },
    { max: 0.5, color: [62, 93, 131] },
    { max: 2, color: [92, 176, 112] },
    { max: Infinity, color: [36, 140, 72] },
];
const CURTAIN_STEP = 5; // seconds between the lines down to the ground in 3D

export default class extends Controller {
    static targets = ['map', 'view', 'follow', 'status', 'nowTime', 'nowAltitude', 'nowSpeed', 'nowVario', 'nowPhase', 'phases', 'play', 'rate', 'seek', 'chart'];

    static values = { url: String };

    async connect() {
        this.index = 0;
        this.playing = false;
        this.is3d = false;
        this.following = false;
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
        this.buildMap();
        this.buildCharts();
        this.buildPhases();
        this.resizeObserver = new ResizeObserver(() => this.resizeCharts());
        this.resizeObserver.observe(this.element);
        this.moveTo(this.flight.statistics.takeoff ?? 0);
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.resizeObserver?.disconnect();
        this.charts.forEach((chart) => chart.destroy());
        this.map?.remove();
    }

    /* ---------- Map ---------- */

    buildMap() {
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, boundsOf(this.series.latitude, this.series.longitude)));
        this.map.on('load', () => {
            addTask(this.map, this.flight.task);
            this.trackSegments = this.segmentsByVario();
            this.render();
            this.statusTarget.hidden = true;
        });
    }

    /** Runs of the track of the same vario class, drawn as one path each. */
    segmentsByVario() {
        const { vario } = this.series;
        const classOf = (v) => VARIO_CLASSES.findIndex((c) => v <= c.max);
        const segments = [];
        let current = null;
        for (let i = 0; i < this.count; i += 1) {
            const cls = classOf(vario[i]);
            if (!current || current.cls !== cls) {
                // Each segment starts at the end of the previous one: no gap in the line
                current = { cls, color: VARIO_CLASSES[cls].color, indexes: current ? [i - 1, i] : [i] };
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
        const i = Math.round(this.index);
        // From above, everything lies at altitude 0 like the map tiles: no depth test, or they fight.
        // In 3D the relief hides the track behind it, the glider always shows, and the white casing
        // is left out (at the same depth as the track, it would hide parts of it).
        const parameters = this.is3d ? {} : { depthCompare: 'always' };
        const always = { depthCompare: 'always' };
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
                getWidth: 3,
                widthUnits: 'pixels',
                capRounded: true,
                jointRounded: true,
                parameters,
                updateTriggers: { getPath: this.is3d },
            }),
            new ScatterplotLayer({
                id: 'glider',
                data: [i],
                getPosition: (k) => this.position(k),
                getRadius: 7,
                radiusUnits: 'pixels',
                getFillColor: [255, 255, 255],
                getLineColor: [25, 38, 48],
                lineWidthMinPixels: 3,
                stroked: true,
                parameters: always,
                updateTriggers: { getPosition: [i, this.is3d] },
            }),
        ];
        if (this.is3d) {
            // Lines down to the ground: the relief hides their lower part
            if (!this.curtain) {
                this.curtain = [];
                for (let k = 0; k < this.count; k += CURTAIN_STEP) this.curtain.push(k);
            }
            layers.unshift(new LineLayer({
                id: 'curtain',
                data: this.curtain,
                getSourcePosition: (k) => this.position(k),
                getTargetPosition: (k) => this.position(k, true),
                getColor: [25, 38, 48, 70],
                getWidth: 1,
            }));
            layers.push(new LineLayer({
                id: 'glider-stem',
                data: [i],
                getSourcePosition: (k) => this.position(k),
                getTargetPosition: (k) => this.position(k, true),
                getColor: [25, 38, 48, 200],
                getWidth: 2,
                updateTriggers: { getSourcePosition: i, getTargetPosition: i },
            }));
        }
        this.overlay.setProps({ layers });
    }

    setView({ currentTarget }) {
        this.is3d = currentTarget.dataset.view === '3d';
        this.viewTargets.forEach((button) => button.setAttribute('aria-pressed', String(button === currentTarget)));
        if (this.is3d) {
            setTerrain(this.map, true);
            easeAt(this.map, { pitch: 62, bearing: this.map.getBearing() || -20, duration: 800 }, this.series.altitude[Math.round(this.index)]);
        } else {
            setTerrain(this.map, false);
            easeAt(this.map, { pitch: 0, bearing: 0, duration: 600 }, 0);
        }
        this.render();
    }

    setBasemap({ currentTarget }) {
        setBasemap(this.map, currentTarget.value, () => this.render());
    }

    toggleFollow() {
        this.following = !this.following;
        this.followTarget.setAttribute('aria-pressed', String(this.following));
        if (this.following) this.centerOnGlider();
    }

    centerOnGlider() {
        const [lon, lat, altitude] = this.position(Math.round(this.index));
        this.map.jumpTo({ center: [lon, lat], elevation: altitude });
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
                series: [{}, { ...settings[name], width: 1.5, points: { show: false } }],
                plugins: [valueTip((u, i) => [`${FORMATS[name](u.data[1][i])} · ${formatTime(u.data[0][i])}`])],
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
            const chart = new uPlot(options, [time, this.series[name]], target);
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
        if (this.following && this.map) this.centerOnGlider();
        this.render();
    }
}



/** One line describing a thermal or a glide. */
function describePhase(phase) {
    const minutes = Math.max(1, Math.round(phase.duration / 60));
    const number = (value, digits = 1) => value.toLocaleString('fr-FR', { maximumFractionDigits: digits });
    if (phase.type === 'thermal') {
        return `Thermique · ${minutes} min · ${phase.gain >= 0 ? '+' : ''}${phase.gain} m · ${number(phase.vario)} m/s · ${phase.direction === 'left' ? 'à gauche' : 'à droite'}`;
    }

    return `Transition · ${number(phase.distance / 1000)} km · ${Math.round(phase.speed)} km/h${phase.glideRatio ? ` · finesse ${Math.round(phase.glideRatio)}` : ''}`;
}
