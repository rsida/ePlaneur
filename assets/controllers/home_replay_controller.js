/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { addTask, boundsOf, createMap, setTerrain } from '../flight/map.js';
import { chaseBearing, gliderLayers, positionAt, trailLayers } from '../flight/scene.js';

/*
 * Replay of the home page (roadmap 11e): a public flight of a member flown again in 3D, the camera
 * behind the glider, in a loop. The map and the track load only when the section comes into view,
 * the replay pauses out of view and in a hidden tab, and does not start by itself when the reader
 * asked for reduced motion (a button starts it).
 */

/** Seconds of flight per second on the screen. */
const SPEED = 60;
/** Seconds of track drawn behind the glider. */
const TRAIL = 600;
/**
 * Seconds of track the camera looks back along to stay behind the glider: longer than in the viewer
 * (a minute), as 60 times faster the course of a minute turns the map too often.
 */
const CHASE = 180;

export default class extends Controller {
    static targets = ['map', 'toggle', 'status'];

    static values = { url: String, label: String };

    connect() {
        this.playing = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.visible = false;
        this.observer = new IntersectionObserver(([entry]) => {
            this.visible = entry.isIntersecting;
            if (this.visible && !this.map) this.load();
            this.schedule();
        }, { rootMargin: '200px' });
        this.observer.observe(this.element);
        this.onVisibility = () => this.schedule();
        document.addEventListener('visibilitychange', this.onVisibility);
        this.showToggle();
    }

    disconnect() {
        this.observer?.disconnect();
        document.removeEventListener('visibilitychange', this.onVisibility);
        cancelAnimationFrame(this.frame);
        this.map?.remove();
    }

    async load() {
        this.map = true; // loading
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) throw new Error(String(response.status));
            ({ step: this.step, track: this.track, task: this.task } = await response.json());
        } catch {
            this.statusTarget.textContent = 'Le vol n’a pas pu être chargé.';

            return;
        }
        this.index = 0;
        this.color = rgb(getComputedStyle(this.element).getPropertyValue('--color-pilot-a'));
        ({ map: this.map, overlay: this.overlay } = createMap(this.mapTarget, boundsOf(this.track.latitude, this.track.longitude)));
        // The page scrolls over the map: no zoom with the wheel
        this.map.scrollZoom.disable();
        this.map.on('load', () => {
            addTask(this.map, this.task);
            setTerrain(this.map, true);
            const [lon, lat, altitude] = positionAt(this.track, 0);
            this.map.jumpTo({ center: [lon, lat], zoom: 12.5, pitch: 65, elevation: altitude });
            this.statusTarget.hidden = true;
            // The credits of the map folded: unfolded they hide a third of a small map
            this.mapTarget.querySelector('.maplibregl-ctrl-attrib')?.classList.remove('maplibregl-compact-show');
            this.ready = true;
            this.draw();
            this.schedule();
        });
    }

    toggle() {
        this.playing = !this.playing;
        this.showToggle();
        this.schedule();
    }

    showToggle() {
        this.toggleTarget.textContent = this.playing ? 'Pause' : 'Lecture';
        this.toggleTarget.setAttribute('aria-pressed', String(!this.playing));
    }

    /** Runs the replay while it plays, is in view and the tab is shown; stops it otherwise. */
    schedule() {
        cancelAnimationFrame(this.frame);
        if (!this.ready || !this.playing || !this.visible || document.hidden) return;
        let last = performance.now();
        const tick = (now) => {
            const seconds = Math.min(0.1, (now - last) / 1000);
            last = now;
            this.index += (seconds * SPEED) / this.step;
            if (this.index >= this.track.latitude.length - 1) {
                this.index = 0;
                this.chase = null;
            }
            this.draw();
            this.frame = requestAnimationFrame(tick);
        };
        this.frame = requestAnimationFrame(tick);
    }

    draw() {
        const [lon, lat, altitude] = positionAt(this.track, this.index);
        this.chase = chaseBearing(this.track, this.index, this.chase ?? this.map.getBearing(), CHASE / this.step);
        this.map.jumpTo({ center: [lon, lat], elevation: altitude, bearing: this.chase });
        const glider = { track: this.track, index: this.index, color: this.color, label: this.labelValue, selected: true };
        this.overlay.setProps({
            layers: [
                ...trailLayers('replay', [glider], { is3d: true, seconds: TRAIL / this.step }),
                ...gliderLayers('replay', [glider], { is3d: true, zoom: this.map.getZoom() }),
            ],
        });
    }
}

/** "#d9822b" as [217, 130, 43]. */
function rgb(hex) {
    const value = parseInt(hex.trim().replace('#', ''), 16);

    return Number.isNaN(value) ? [217, 130, 43] : [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}
