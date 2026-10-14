/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { valueTip } from '../flight/chart.js';

/*
 * Charts of the activity of the club (templates/activity/index.html.twig): one point per month, one
 * curve per indicator named in data-series; hovering gives the month and the exact values.
 */

const SERIES = {
    flights: { label: 'Vols déposés', color: '--color-primary' },
    pilots: { label: 'Pilotes actifs', color: '--color-pilot-a' },
    hours: { label: 'Heures de vol', color: '--color-primary', format: (value) => `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} h` },
    sessions: { label: 'Sessions organisées', color: '--color-primary' },
    participations: { label: 'Participations (traces déposées)', color: '--color-pilot-a' },
    steps: { label: 'Étapes validées', color: '--color-success' },
    items: { label: 'Éléments de Mon parcours', color: '--color-primary' },
    comments: { label: 'Commentaires des formateurs', color: '--color-pilot-a' },
};
const HEIGHT = 180;

export default class extends Controller {
    static targets = ['chart'];

    static values = { months: Array };

    connect() {
        const months = this.monthsValue;
        const x = months.map((_, n) => n + 1);
        const style = getComputedStyle(this.element);
        this.charts = this.chartTargets.map((target) => {
            const names = target.dataset.series.split(' ');
            const series = names.map((name) => ({ ...SERIES[name], name, stroke: style.getPropertyValue(SERIES[name].color).trim() }));
            const format = (serie, value) => (serie.format ? serie.format(value) : String(value));
            if (series.length > 1) {
                const legend = document.createElement('p');
                legend.className = 'p-activity__legend';
                legend.append(...series.map((serie) => {
                    const item = document.createElement('span');
                    const key = document.createElement('span');
                    key.className = 'p-activity__legend-key';
                    key.style.backgroundColor = serie.stroke;
                    item.append(key, serie.label);

                    return item;
                }));
                target.before(legend);
            }

            return new uPlot({
                width: target.clientWidth || 600,
                height: HEIGHT,
                legend: { show: false },
                cursor: { drag: { x: false, y: false }, points: { size: 8 } },
                scales: { x: { time: false, range: [0.5, months.length + 0.5] }, y: { range: (u, min, max) => [0, Math.max(1, max)] } },
                axes: [
                    { values: (u, ticks) => ticks.map((t) => (Number.isInteger(t) && months[t - 1] ? months[t - 1].label : '')), size: 28, font: '11px Inter, sans-serif' },
                    { size: 40, font: '11px Inter, sans-serif' },
                ],
                series: [{}, ...series.map((serie) => ({ label: serie.label, stroke: serie.stroke, width: 2, points: { show: true, size: 6, fill: serie.stroke } }))],
                plugins: [valueTip((u, i) => [months[i].label, ...series.map((serie) => ({ text: `${serie.label} : ${format(serie, months[i][serie.name])}`, color: serie.stroke }))])],
            }, [x, ...series.map((serie) => months.map((month) => month[serie.name]))], target);
        });
        this.resizeObserver = new ResizeObserver(() => this.charts.forEach((chart, n) => chart.setSize({ width: this.chartTargets[n].clientWidth, height: HEIGHT })));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.charts?.forEach((chart) => chart.destroy());
        this.element.querySelectorAll('.p-activity__legend').forEach((legend) => legend.remove());
    }
}
