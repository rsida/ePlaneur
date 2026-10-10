/*
 * Shared by the uPlot charts of the flight pages: a label next to the cursor giving the exact values
 * of the point under it, kept inside the plot (flipped to the left near the right edge). Every chart
 * gets it through `plugins: [valueTip((u, i) => [...lines])]`.
 */

/**
 * uPlot plugin: `lines(u, i)` returns the lines of the label for the point i (strings, or
 * { text, color } for a line with a color key).
 */
export function valueTip(lines) {
    let tip;

    return {
        hooks: {
            init: (u) => {
                tip = document.createElement('div');
                tip.className = 'p-flight__chart-tip';
                tip.hidden = true;
                u.over.append(tip);
            },
            setCursor: (u) => {
                const i = u.cursor.idx;
                if (i == null || u.cursor.left < 0) {
                    tip.hidden = true;

                    return;
                }
                tip.replaceChildren(...lines(u, i).map((line) => {
                    const row = document.createElement('span');
                    row.className = 'p-flight__chart-tip-line';
                    if (typeof line === 'string') {
                        row.textContent = line;
                    } else {
                        const key = document.createElement('span');
                        key.className = 'p-flight__chart-tip-key';
                        key.style.backgroundColor = line.color;
                        row.append(key, line.text);
                    }

                    return row;
                }));
                tip.hidden = false;
                const x = u.valToPos(u.data[0][i], 'x');
                // Next to the point of a single curve, next to the cursor when there are several
                const value = u.data[1][i];
                const y = u.series.length > 2 || value == null ? u.cursor.top : u.valToPos(value, 'y');
                const flip = x + 8 + tip.offsetWidth > u.over.clientWidth;
                tip.style.left = `${flip ? x - 8 - tip.offsetWidth : x + 8}px`;
                tip.style.top = `${Math.min(Math.max(y - tip.offsetHeight - 6, 0), u.over.clientHeight - tip.offsetHeight)}px`;
            },
        },
    };
}

/** "1 234 m", "+2,3 m/s", "112 km/h": the values of the flight pages. */
export const FORMATS = {
    altitude: (value) => `${Math.round(value).toLocaleString('fr-FR')} m`,
    vario: (value) => `${value > 0 ? '+' : ''}${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m/s`,
    speed: (value) => `${Math.round(value)} km/h`,
    height: (value) => `${Math.round(value).toLocaleString('fr-FR')} m sol`,
    required: (value) => `finesse ${Math.round(value)}`,
    distance: (km) => `km ${km.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}`,
    /** A time gained or lost: "+2 min 05 s", "−45 s". */
    gap: (seconds) => {
        const s = Math.abs(Math.round(seconds));
        const sign = seconds > 0 ? '+' : seconds < 0 ? '−' : '';

        return sign + (s >= 60 ? `${Math.floor(s / 60)} min ${String(s % 60).padStart(2, '0')} s` : `${s} s`);
    },
};
