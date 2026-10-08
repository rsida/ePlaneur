/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { FORMATS, valueTip } from '../flight/chart.js';

/*
 * Logbook charts (templates/flight/logbook.html.twig): one value per flight, in the order they were
 * flown; hovering gives the date, the flight plan and the value, clicking opens the flight.
 */

const SERIES = {
    taskSpeed: { label: 'Vitesse circuit', format: FORMATS.speed },
    climb: { label: 'Vario moyen', format: (value) => `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s` },
    glideRatio: { label: 'Finesse', format: (value) => String(Math.round(value)) },
    circling: { label: 'En spirale', format: (value) => `${Math.round(value)} %` },
};

export default class extends Controller {
    static targets = ['chart'];

    static values = { points: Array };

    connect() {
        const points = this.pointsValue;
        const x = points.map((_, n) => n + 1);
        const color = getComputedStyle(this.element).getPropertyValue('--color-primary').trim() || '#3e5d83';
        this.charts = this.chartTargets.map((target) => {
            const name = target.dataset.series;
            const { label, format } = SERIES[name];
            const values = points.map((point) => point[name]);
            const chart = new uPlot({
                width: target.clientWidth || 600,
                height: 140,
                legend: { show: false },
                cursor: { drag: { x: false, y: false }, points: { size: 8 } },
                scales: { x: { time: false, range: [0.5, points.length + 0.5] } },
                axes: [
                    { values: (u, ticks) => ticks.map((t) => (Number.isInteger(t) && points[t - 1] ? points[t - 1].date : '')), size: 28, font: '11px Inter, sans-serif' },
                    { size: 46, font: '11px Inter, sans-serif' },
                ],
                series: [{}, { label, stroke: color, width: 2, spanGaps: true, points: { show: true, size: 6, fill: color } }],
                plugins: [valueTip((u, i) => [points[i].date, points[i].plan, values[i] == null ? 'Pas de valeur' : `${label} : ${format(values[i])}`])],
            }, [x, values], target);
            chart.over.addEventListener('click', () => {
                const i = chart.cursor.idx;
                if (i != null) window.location.assign(points[i].url);
            });
            chart.over.style.cursor = 'pointer';

            return chart;
        });
        this.resizeObserver = new ResizeObserver(() => this.charts.forEach((chart, n) => chart.setSize({ width: this.chartTargets[n].clientWidth, height: 140 })));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.charts?.forEach((chart) => chart.destroy());
    }
}
