/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { PathLayer, ScatterplotLayer } from '@deck.gl/layers';
import { createMap } from '../flight/map.js';
import { distance } from '../flight/geo.js';
import { climbColor } from '../flight/scene.js';

/*
 * Lift map of a landscape (templates/flight/lift_map.html.twig): every thermal and slope lift of
 * the club flights on it (FlightController::liftMapData(), ClubLift). From afar a heat map of the
 * thermals (MapLibre), weighted by their climb; from HEAT_TO zoom the thermals as points, those
 * closer than CLUSTER metres as one; the slope lift as orange lines, filtered by the direction of
 * the wind of their flight (eight sectors).
 */

const CLUSTER = 700; // metres
const HEAT_TO = 11; // zoom where the heat map gives way to the thermals
const SECTORS = ['nord', 'nord-est', 'est', 'sud-est', 'sud', 'sud-ouest', 'ouest', 'nord-ouest'];
const SLOPE_COLOR = [255, 140, 0];

export default class extends Controller {
    static targets = ['map', 'status', 'wind', 'summary'];

    static values = { url: String };

    async connect() {
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            this.lift = await response.json();
        } catch {
            this.statusTarget.textContent = 'La carte des ascendances n’a pas pu être chargée.';

            return;
        }
        const { thermals, slopes } = this.lift;
        const points = [...thermals.map(([lat, lon]) => [lon, lat]), ...slopes.flatMap((slope) => slope.path)];
        if (points.length === 0) {
            this.statusTarget.textContent = 'Aucune ascendance dans les vols de ce paysage.';

            return;
        }
        this.clusters = cluster(thermals);
        const lons = points.map(([lon]) => lon);
        const lats = points.map(([, lat]) => lat);
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, [[Math.min(...lons), Math.min(...lats)], [Math.max(...lons), Math.max(...lats)]]));
        this.map.on('load', () => {
            this.map.addSource('thermals', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: thermals.map(([lat, lon, climb]) => ({ type: 'Feature', properties: { climb: Math.max(0, climb) }, geometry: { type: 'Point', coordinates: [lon, lat] } })) },
            });
            this.map.addLayer({
                id: 'thermal-heat',
                type: 'heatmap',
                source: 'thermals',
                maxzoom: HEAT_TO + 1,
                paint: {
                    'heatmap-weight': ['interpolate', ['linear'], ['get', 'climb'], 0, 0.2, 4, 1],
                    'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 6, 8, HEAT_TO, 30],
                    'heatmap-opacity': ['interpolate', ['linear'], ['zoom'], HEAT_TO - 1, 0.8, HEAT_TO + 1, 0],
                    'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(0,0,0,0)', 0.2, 'rgb(240,128,118)', 0.5, 'rgb(226,62,52)', 0.8, 'rgb(186,22,38)', 1, 'rgb(128,0,48)'],
                },
            });
            this.render();
            this.statusTarget.hidden = true;
        });
        this.map.on('zoomend', () => this.render());
    }

    disconnect() {
        this.map?.remove();
    }

    render() {
        if (!this.overlay) return;
        const choice = this.windTarget.value;
        const slopes = choice === 'none' ? [] : this.lift.slopes.filter((slope) => choice === 'all' || (slope.windFrom !== null && sectorOf(slope.windFrom) === Number(choice)));
        const close = this.map.getZoom() >= HEAT_TO;
        this.summaryTarget.textContent = `${this.lift.flights} vols · ${this.lift.thermals.length} thermiques · ${slopes.length} passage${slopes.length > 1 ? 's' : ''} en pente${choice !== 'all' && choice !== 'none' ? ` par vent de ${SECTORS[Number(choice)]}` : ''}`;
        this.overlay.setProps({
            layers: [
                new PathLayer({
                    id: 'club-slopes',
                    data: slopes,
                    getPath: (slope) => slope.path,
                    getColor: [...SLOPE_COLOR, 220],
                    getWidth: (slope) => Math.max(3, Math.min(9, 2 + 2 * slope.climb)),
                    widthUnits: 'pixels',
                    capRounded: true,
                    jointRounded: true,
                    pickable: true,
                    parameters: { depthCompare: 'always' },
                }),
                new ScatterplotLayer({
                    id: 'club-thermals',
                    visible: close,
                    data: this.clusters,
                    getPosition: (c) => [c.longitude, c.latitude],
                    getRadius: (c) => 5 + 3 * Math.sqrt(c.count),
                    radiusUnits: 'pixels',
                    getFillColor: (c) => [...climbColor(c.climb), 220],
                    getLineColor: [255, 255, 255],
                    lineWidthMinPixels: 1.5,
                    stroked: true,
                    pickable: true,
                    parameters: { depthCompare: 'always' },
                }),
            ],
            getTooltip: ({ object }) => {
                if (!object) return null;
                const number = (value) => value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                if (object.path) {
                    const wind = object.windFrom === null ? 'vent inconnu' : `vent de ${SECTORS[sectorOf(object.windFrom)]} (${Math.round(object.windFrom)}°, ${Math.round(object.windSpeed)} km/h)`;

                    return { text: `Pente · +${Math.round(object.gain)} m à ${number(object.climb)} m/s · ${wind}` };
                }

                return { text: `${object.count} montée${object.count > 1 ? 's' : ''} · ${number(object.climb)} m/s en moyenne\n${object.bottom} → ${object.top} m` };
            },
        });
    }
}

/** Eighth of the compass a wind blows from: 0 north, clockwise. */
function sectorOf(from) {
    return Math.round((((from % 360) + 360) % 360) / 45) % 8;
}

/** Thermals ([lat, lon, climb, bottom, top]) closer than CLUSTER metres as one, strongest first. */
function cluster(thermals) {
    const groups = [];
    [...thermals].sort((a, b) => b[2] - a[2]).forEach((t) => {
        const group = groups.find((g) => distance(g.latitude, g.longitude, t[0], t[1]) < CLUSTER);
        if (group) group.members.push(t);
        else groups.push({ latitude: t[0], longitude: t[1], members: [t] });
    });

    return groups.map((g) => ({
        latitude: g.members.reduce((sum, t) => sum + t[0], 0) / g.members.length,
        longitude: g.members.reduce((sum, t) => sum + t[1], 0) / g.members.length,
        count: g.members.length,
        climb: g.members.reduce((sum, t) => sum + t[2], 0) / g.members.length,
        bottom: Math.min(...g.members.map((t) => t[3])),
        top: Math.max(...g.members.map((t) => t[4])),
    }));
}
