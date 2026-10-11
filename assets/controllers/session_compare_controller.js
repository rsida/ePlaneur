/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';

/*
 * Charts of the whole-session comparison (templates/session/compare.html.twig): for every pilot,
 * the gap to the virtual leader and the altitude along the task (SessionController::compareData()),
 * one colour per pilot in the ranking order, the values of every pilot under the cursor, the name
 * of each pilot at the end of their line. "Isoler" puts one pilot forward, the others fade.
 */

/* Same colours as the session replay and the page */
const COLORS = [
    [214, 69, 65], [62, 93, 131], [36, 140, 72], [168, 91, 42], [123, 79, 160],
    [0, 150, 160], [200, 160, 0], [220, 90, 160], [90, 90, 90], [120, 160, 40],
];

export default class extends Controller {
    static targets = ['chart', 'status', 'isolate'];

    static values = { url: String };

    async connect() {
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.comparison = await response.json();
        } catch {
            this.statusTarget.textContent = 'La comparaison n’a pas pu être chargée.';

            return;
        }
        this.statusTarget.hidden = true;
        this.isolated = null;
        this.build();
        this.resizeObserver = new ResizeObserver(() => this.charts.forEach((chart, k) => chart.setSize({ width: this.chartTargets[k].clientWidth, height: 220 })));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.charts?.forEach((chart) => chart.destroy());
    }

    /** One pilot forward, or all again. */
    isolate({ params: { index } }) {
        this.isolated = this.isolated === index ? null : index;
        this.isolateTargets.forEach((button, f) => button.setAttribute('aria-pressed', String(f === this.isolated)));
        this.charts?.forEach((chart) => {
            chart.series.forEach((series, s) => {
                if (s > 0) series.width = this.isolated === s - 1 ? 3 : 1.5;
            });
            chart.redraw(true);
        });
    }

    build() {
        const { km, flights } = this.comparison;
        const color = (f) => `rgb(${COLORS[f % COLORS.length].join(' ')})`;
        // Faded when another pilot is isolated
        const stroke = (f) => () => (this.isolated === null || this.isolated === f ? color(f) : `rgb(${COLORS[f % COLORS.length].join(' ')} / 18%)`);
        const name = (flight) => flight.competitionId || flight.pilot;
        const pad = (values) => km.map((_, k) => values[k] ?? null);
        const settings = {
            gap: {
                data: flights.map((flight) => pad(flight.gap.map((s) => s / 60))),
                line: (flight, k) => (flight.gap[k] == null ? null : FORMATS.gap(flight.gap[k])),
            },
            altitude: {
                data: flights.map((flight) => pad(flight.altitude)),
                line: (flight, k) => (flight.altitude[k] == null ? null : FORMATS.altitude(flight.altitude[k])),
            },
        };
        const sync = uPlot.sync('session-compare');
        this.charts = this.chartTargets.map((target) => {
            const { data, line } = settings[target.dataset.series];
            const options = {
                width: target.clientWidth || 600,
                height: 220,
                cursor: { sync: { key: sync.key }, drag: { x: false, y: false }, points: { size: 5 } },
                legend: { show: false },
                scales: { x: { time: false } },
                axes: [
                    { values: (u, ticks) => ticks.map((t) => `${t} km`), size: 28, font: '11px Inter, sans-serif' },
                    { size: 50, font: '11px Inter, sans-serif' },
                ],
                // Room on the right for the names at the end of the lines
                padding: [12, 64, 0, 0],
                series: [{}, ...flights.map((flight, f) => ({ label: name(flight), stroke: stroke(f), width: 1.5, points: { show: false } }))],
                hooks: {
                    draw: [(u) => this.drawNames(u, flights.map((flight, f) => ({ name: name(flight), color: stroke(f)(), strong: this.isolated === f })))],
                },
                plugins: [valueTip((u, k) => [
                    FORMATS.distance(km[k]),
                    ...flights.map((flight, f) => ({ flight, f, text: line(flight, k) }))
                        .filter((row) => row.text !== null)
                        .map((row) => ({ text: `${name(row.flight)} · ${row.text}`, color: color(row.f) })),
                ])],
            };

            return new uPlot(options, [km, ...data], target);
        });
    }

    /** Each pilot's name at the end of their line, moved apart so that none overlaps. */
    drawNames(u, pilots) {
        const { ctx } = u;
        const ratio = window.devicePixelRatio || 1;
        const gap = 13 * ratio;
        const labels = pilots.map((pilot, f) => {
            const values = u.data[f + 1];
            let k = values.length - 1;
            while (k >= 0 && values[k] == null) k -= 1;
            if (k < 0) return null;

            return { ...pilot, x: u.valToPos(u.data[0][k], 'x', true), y: u.valToPos(values[k], 'y', true) };
        }).filter((label) => label !== null).sort((a, b) => a.y - b.y);
        for (let n = 1; n < labels.length; n += 1) labels[n].y = Math.max(labels[n].y, labels[n - 1].y + gap);
        ctx.save();
        ctx.textBaseline = 'middle';
        labels.forEach((label) => {
            ctx.font = `${label.strong ? 700 : 600} ${11 * ratio}px Inter, sans-serif`;
            ctx.fillStyle = label.color;
            ctx.fillText(label.name, label.x + 6 * ratio, label.y);
        });
        ctx.restore();
    }
}
