/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';

/*
 * Charts of the whole-session comparison (templates/session/compare.html.twig): for every pilot,
 * the gap to the virtual leader and the altitude along the task (SessionController::compareData()),
 * one colour per pilot in the ranking order, the values of every pilot under the cursor.
 */

/* Same colours as the session replay and the page */
const COLORS = [
    [214, 69, 65], [62, 93, 131], [36, 140, 72], [168, 91, 42], [123, 79, 160],
    [0, 150, 160], [200, 160, 0], [220, 90, 160], [90, 90, 90], [120, 160, 40],
];

export default class extends Controller {
    static targets = ['chart', 'status'];

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
        this.build();
        this.resizeObserver = new ResizeObserver(() => this.charts.forEach((chart, k) => chart.setSize({ width: this.chartTargets[k].clientWidth, height: 220 })));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.charts?.forEach((chart) => chart.destroy());
    }

    build() {
        const { km, flights } = this.comparison;
        const color = (f) => `rgb(${COLORS[f % COLORS.length].join(' ')})`;
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
                series: [{}, ...flights.map((flight, f) => ({ label: name(flight), stroke: color(f), width: 1.5, points: { show: false } }))],
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
}
