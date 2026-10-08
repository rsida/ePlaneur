/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { LineLayer, PathLayer, ScatterplotLayer } from '@deck.gl/layers';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';
import { addTask, boundsOf, createMap, easeAt, newBounds, setBasemap, setTerrain } from '../flight/map.js';

/*
 * Comparison viewer (templates/flight/compare.html.twig): two flights of the same task
 * (FlightController::compareData()) placed by distance along the task rather than by time. Moving
 * along the task (charts, slider, key moments, thermals) puts each glider where it was when it had
 * flown that distance, and gives the gap between them.
 */

export default class extends Controller {
    static targets = ['map', 'view', 'status', 'nowDistance', 'nowGap', 'nowAltitudeA', 'nowAltitudeB', 'seek', 'chart'];

    static values = { url: String };

    async connect() {
        this.k = 0;
        this.is3d = false;
        this.charts = [];
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.comparison = await response.json();
        } catch {
            this.statusTarget.textContent = 'Les vols n’ont pas pu être chargés.';

            return;
        }
        const style = getComputedStyle(this.element);
        const colors = ['--color-pilot-a', '--color-pilot-b'].map((name) => toRgb(style.getPropertyValue(name)));
        this.lostColor = style.getPropertyValue('--color-danger').trim();
        this.wonColor = style.getPropertyValue('--color-success').trim();
        this.flights = this.comparison.flights.map((flight, n) => ({ ...flight, color: colors[n] }));
        const [a, b] = this.flights;
        // Steps of the task flown by both: the gap is defined there
        this.common = Math.min(a.times.length, b.times.length);
        this.steps = Math.max(a.times.length, b.times.length);
        this.gaps = [];
        for (let k = 0; k < this.common; k += 1) this.gaps.push((a.times[k] - a.start) - (b.times[k] - b.start));
        this.buildMap();
        this.buildCharts();
        this.resizeObserver = new ResizeObserver(() => this.resizeCharts());
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.charts.forEach((chart) => chart.destroy());
        this.map?.remove();
    }

    /** Index in the series of a flight at step k (its last point when it did not get that far). */
    indexAt(flight, k) {
        return flight.times[Math.min(k, flight.times.length - 1)];
    }

    /* ---------- Map ---------- */

    buildMap() {
        const bounds = newBounds();
        this.flights.forEach((f) => boundsOf(f.latitude.slice(f.start, f.times.at(-1) + 1), f.longitude.slice(f.start, f.times.at(-1) + 1), bounds));
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, bounds));
        this.map.on('load', () => {
            addTask(this.map, this.comparison.task);
            this.statusTarget.hidden = true;
            this.moveTo(0);
        });
    }

    position(flight, i, ground = false) {
        return [flight.longitude[i], flight.latitude[i], ground || !this.is3d ? 0 : flight.altitude[i]];
    }

    /** Each track from its start to its finish (or its last progress), computed again on a view change. */
    paths() {
        if (this.pathsFor !== this.is3d) {
            this.pathsFor = this.is3d;
            this.flights.forEach((f) => {
                f.path = [];
                for (let i = f.start; i <= f.times.at(-1); i += 1) f.path.push(this.position(f, i));
            });
        }

        return this.flights;
    }

    render() {
        if (!this.overlay) return;
        const parameters = this.is3d ? {} : { depthCompare: 'always' };
        const always = { depthCompare: 'always' };
        const now = this.flights.map((flight) => ({ flight, i: this.indexAt(flight, this.k) }));
        const layers = [
            new PathLayer({
                id: 'tracks',
                data: this.paths(),
                getPath: (f) => f.path,
                getColor: (f) => [...f.color, 220],
                getWidth: 3,
                widthUnits: 'pixels',
                jointRounded: true,
                parameters,
                updateTriggers: { getPath: this.is3d },
            }),
            new ScatterplotLayer({
                id: 'gliders',
                data: now,
                getPosition: ({ flight, i }) => this.position(flight, i),
                getRadius: 7,
                radiusUnits: 'pixels',
                getFillColor: ({ flight }) => flight.color,
                getLineColor: [255, 255, 255],
                lineWidthMinPixels: 2,
                stroked: true,
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
        const [a] = this.flights;
        const elevation = this.is3d ? a.altitude[this.indexAt(a, this.k)] : 0;
        easeAt(this.map, this.is3d ? { pitch: 62, bearing: this.map.getBearing() || -20, duration: 800 } : { pitch: 0, bearing: 0, duration: 600 }, elevation);
        this.render();
    }

    setBasemap({ currentTarget }) {
        setBasemap(this.map, currentTarget.value, () => this.render());
    }

    /* ---------- Charts ---------- */

    buildCharts() {
        const sync = uPlot.sync('compare');
        const km = Array.from({ length: this.steps }, (_, k) => (k * this.comparison.step) / 1000);
        const [a, b] = this.flights;
        const altitudeOf = (flight) => km.map((_, k) => (k < flight.times.length ? flight.altitude[flight.times[k]] : null));
        const gapOf = (keep) => km.map((_, k) => (k < this.common ? (keep(this.gaps[k]) ? this.gaps[k] : 0) : null));
        const css = (color) => `rgb(${color.join(' ')})`;
        const nameOf = (flight) => flight.competitionId || flight.pilot;
        const altitudeLine = (flight, k) => ({ text: `${nameOf(flight)} : ${k < flight.times.length ? FORMATS.altitude(flight.altitude[flight.times[k]]) : 'pas arrivé là'}`, color: css(flight.color) });
        const settings = {
            gap: {
                tip: (k) => [FORMATS.distance(km[k]), k < this.common ? `Écart de ${nameOf(a)} : ${FORMATS.gap(this.gaps[k])}` : 'Pas de comparaison au-delà'],
                data: [gapOf((g) => g > 0), gapOf((g) => g < 0)],
                series: [
                    { label: 'Retard', stroke: this.lostColor, fill: `color-mix(in srgb, ${this.lostColor} 25%, transparent)`, width: 1.5 },
                    { label: 'Avance', stroke: this.wonColor, fill: `color-mix(in srgb, ${this.wonColor} 25%, transparent)`, width: 1.5 },
                ],
            },
            altitude: {
                tip: (k) => [FORMATS.distance(km[k]), altitudeLine(a, k), altitudeLine(b, k)],
                data: [altitudeOf(a), altitudeOf(b)],
                series: [
                    { label: a.competitionId || a.pilot, stroke: css(a.color), width: 1.5 },
                    { label: b.competitionId || b.pilot, stroke: css(b.color), width: 1.5 },
                ],
            },
        };

        this.charts = this.chartTargets.map((target) => {
            const { data, series, tip } = settings[target.dataset.series];
            const options = {
                width: target.clientWidth || 600,
                height: 150,
                cursor: { sync: { key: sync.key }, drag: { x: false, y: false }, points: { size: 6 } },
                legend: { show: false },
                scales: { x: { time: false } },
                axes: [
                    { values: (u, ticks) => ticks.map((t) => `${t} km`), size: 28, font: '11px Inter, sans-serif' },
                    { size: 50, font: '11px Inter, sans-serif' },
                ],
                series: [{}, ...series.map((s) => ({ ...s, points: { show: false } }))],
                plugins: [valueTip((u, k) => tip(k))],
                hooks: {
                    setCursor: [(u) => {
                        if (u.cursor.idx != null && this.hovering === u) this.moveTo(u.cursor.idx, { fromChart: true });
                    }],
                },
            };
            const chart = new uPlot(options, [km, ...data], target);
            sync.sub(chart);
            chart.over.addEventListener('mouseenter', () => { this.hovering = chart; });
            chart.over.addEventListener('mouseleave', () => { this.hovering = null; });

            return chart;
        });
    }

    resizeCharts() {
        this.charts.forEach((chart, n) => chart.setSize({ width: this.chartTargets[n].clientWidth, height: 150 }));
    }

    /* ---------- Moving along the task ---------- */

    seek() {
        this.moveTo(Number(this.seekTarget.value));
    }

    /** A key moment: framed on the map, both gliders at its end. */
    showStretch({ currentTarget }) {
        const from = Math.round(Number(currentTarget.dataset.from) / this.comparison.step);
        const to = Math.round(Number(currentTarget.dataset.to) / this.comparison.step);
        const bounds = newBounds();
        this.flights.forEach((flight) => {
            for (let i = this.indexAt(flight, from); i <= this.indexAt(flight, to); i += 5) bounds.extend([flight.longitude[i], flight.latitude[i]]);
        });
        if (!this.is3d) this.map.fitBounds(bounds, { padding: 80, maxZoom: 13, duration: 600 });
        this.moveTo(to);
        this.mapTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /** A thermal: both gliders where its pilot started it. */
    showDistance({ currentTarget }) {
        const k = Math.round(Number(currentTarget.dataset.at) / this.comparison.step);
        this.moveTo(k);
        const [a] = this.flights;
        const i = this.indexAt(a, k);
        this.map.jumpTo({ center: [a.longitude[i], a.latitude[i]], elevation: this.is3d ? a.altitude[i] : 0 });
        this.mapTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    moveTo(k, { fromChart = false } = {}) {
        this.k = Math.max(0, Math.min(this.steps - 1, Math.round(k)));
        const [a, b] = this.flights;
        this.seekTarget.value = String(this.k);
        this.nowDistanceTarget.textContent = FORMATS.distance((this.k * this.comparison.step) / 1000);
        const gap = this.gaps[this.k];
        this.nowGapTarget.textContent = gap === undefined ? '—' : FORMATS.gap(gap);
        this.nowGapTarget.dataset.sign = gap > 0 ? 'lost' : gap < 0 ? 'won' : '';
        this.nowAltitudeATarget.textContent = this.k < a.times.length ? FORMATS.altitude(a.altitude[a.times[this.k]]) : '—';
        this.nowAltitudeBTarget.textContent = this.k < b.times.length ? FORMATS.altitude(b.altitude[b.times[this.k]]) : '—';
        if (!fromChart) {
            this.charts.forEach((chart) => chart.setCursor({ left: chart.valToPos((this.k * this.comparison.step) / 1000, 'x'), top: 10 }, false));
        }
        this.render();
    }
}


/** "#d9822b" or "rgb(217 130 43)" → [217, 130, 43]. */
function toRgb(value) {
    const color = value.trim();
    if (color.startsWith('#')) {
        const hex = color.length === 4 ? [...color.slice(1)].map((c) => c + c).join('') : color.slice(1);

        return [0, 2, 4].map((n) => parseInt(hex.slice(n, n + 2), 16));
    }

    return (color.match(/\d+/g) ?? [0, 0, 0]).slice(0, 3).map(Number);
}
