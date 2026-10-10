/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';
import { valueTip } from '../flight/chart.js';

/*
 * Polar of a glider (templates/glider/show.html.twig, flight page): sink rate (shown downwards)
 * against airspeed, one curve per mass, and on the flight page the glides of the flight as points
 * (clicking one moves the flight viewer there) and the MacCready speed of the flight.
 *
 * curves: [{ label, points: [[km/h, m/s], ...] }] (colours: --color-pilot-b then --color-pilot-a);
 * glides: [[km/h, m/s, second of the flight, netto m/s or null, MacCready m/s], ...];
 * speedToFly: km/h or null.
 */

export default class extends Controller {
    static targets = ['chart'];

    static values = { curves: Array, glides: Array, speedToFly: Number };

    connect() {
        const style = getComputedStyle(this.element);
        const colors = ['--color-pilot-b', '--color-pilot-a'].map((name) => style.getPropertyValue(name).trim() || '#3e5d83');
        const glideColor = style.getPropertyValue('--color-pilot-a').trim() || '#d9822b';
        const number = (value, digits) => value.toLocaleString('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
        // One x axis for every curve and glide: the speeds of all of them
        const speeds = [...new Set([
            ...this.curvesValue.flatMap((curve) => curve.points.map(([speed]) => speed)),
            ...this.glidesValue.map(([speed]) => speed),
        ])].sort((a, b) => a - b);
        const column = (points) => {
            const bySpeed = new Map(points.map(([speed, sink]) => [speed, -sink]));

            return speeds.map((speed) => (bySpeed.has(speed) ? bySpeed.get(speed) : null));
        };
        const glidesAt = new Map(this.glidesValue.map((glide) => [glide[0], glide]));
        const data = [speeds, ...this.curvesValue.map((curve) => column(curve.points))];
        const series = [{}, ...this.curvesValue.map((curve, n) => ({ label: curve.label, stroke: colors[n % colors.length], width: 2, spanGaps: true, points: { show: false } }))];
        if (this.glidesValue.length > 0) {
            data.push(column(this.glidesValue));
            series.push({ label: 'Transitions', stroke: glideColor, width: 0, paths: () => null, points: { show: true, size: 9, fill: glideColor, stroke: '#ffffff', width: 1 } });
        }
        const curveAt = (n, i) => {
            // The curve at this speed: interpolated when this speed is a point of another series
            const value = data[n + 1][i];
            if (value != null) return value;
            const before = data[n + 1].findLastIndex((v, k) => k < i && v != null);
            const after = data[n + 1].findIndex((v, k) => k > i && v != null);
            if (before < 0 || after < 0) return null;

            return data[n + 1][before] + (data[n + 1][after] - data[n + 1][before]) * (speeds[i] - speeds[before]) / (speeds[after] - speeds[before]);
        };
        const stf = this.speedToFlyValue || null;
        this.chart = new uPlot({
            width: this.chartTarget.clientWidth || 600,
            height: 260,
            legend: { show: false },
            cursor: { drag: { x: false, y: false }, points: { size: 7 } },
            scales: { x: { time: false } },
            axes: [
                { values: (u, ticks) => ticks.map((t) => `${t} km/h`), size: 30, font: '11px Inter, sans-serif' },
                { values: (u, ticks) => ticks.map((t) => number(t, 1)), size: 44, font: '11px Inter, sans-serif', label: 'm/s', labelSize: 16 },
            ],
            series,
            plugins: [valueTip((u, i) => {
                const lines = [`${Math.round(speeds[i])} km/h`];
                this.curvesValue.forEach((curve, n) => {
                    const value = curveAt(n, i);
                    if (value != null) lines.push({ text: `${curve.label} : chute ${number(-value, 2)} m/s, finesse ${Math.round(speeds[i] / 3.6 / -value)}`, color: colors[n % colors.length] });
                });
                const glide = glidesAt.get(speeds[i]);
                if (glide) {
                    lines.push({ text: `Transition : chute ${number(glide[1], 2)} m/s, finesse ${glide[1] > 0 ? Math.round(glide[0] / 3.6 / glide[1]) : '∞'}`, color: glideColor });
                    if (glide[3] != null) lines.push({ text: `Netto ${glide[3] > 0 ? '+' : ''}${number(glide[3], 1)} m/s, MacCready ${number(glide[4], 1)}`, color: glideColor });
                }

                return lines;
            })],
            hooks: {
                // The MacCready speed of the flight
                draw: stf ? [(u) => {
                    const x = u.valToPos(stf, 'x', true);
                    const { ctx } = u;
                    ctx.save();
                    ctx.strokeStyle = colors[0];
                    ctx.setLineDash([4, 4]);
                    ctx.beginPath();
                    ctx.moveTo(x, u.bbox.top);
                    ctx.lineTo(x, u.bbox.top + u.bbox.height);
                    ctx.stroke();
                    ctx.restore();
                }] : [],
            },
        }, data, this.chartTarget);
        this.chart.over.addEventListener('click', () => {
            const glide = glidesAt.get(speeds[this.chart.cursor.idx]);
            // On the flight page: the glide in the viewer
            if (glide) this.dispatch('glide', { detail: { at: glide[2] } });
        });
        this.resizeObserver = new ResizeObserver(() => this.chart.setSize({ width: this.chartTarget.clientWidth, height: 260 }));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver?.disconnect();
        this.chart?.destroy();
    }
}
