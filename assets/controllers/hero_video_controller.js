import { Controller } from '@hotwired/stimulus';

/*
 * Background video of the home hero, on large screens only: the file is requested only when the
 * screen matches `media` (phones keep the still image and never download it), not when the reader
 * prefers reduced motion or saves data. The video fades in over the image once it plays; a button
 * pauses it (moving content longer than 5 s, WCAG 2.2.2).
 */
export default class extends Controller {
    static targets = ['media', 'video', 'toggle', 'legend'];
    static values = { src: String, media: String, legend: String };

    connect() {
        this.screen = window.matchMedia(this.mediaValue);
        this.calm = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.update = this.update.bind(this);
        this.screen.addEventListener('change', this.update);
        this.update();
    }

    disconnect() {
        this.screen.removeEventListener('change', this.update);
        this.videoTarget.pause();
    }

    update() {
        if (!this.screen.matches) {
            this.videoTarget.pause();
            return;
        }
        if (this.calm.matches || navigator.connection?.saveData) {
            return;
        }
        if (!this.videoTarget.getAttribute('src')) {
            this.videoTarget.addEventListener('playing', () => this.shown(), { once: true });
            this.videoTarget.src = this.srcValue;
        }
        if (!this.pausedByReader) {
            this.videoTarget.play().catch(() => {});
        }
    }

    toggle() {
        this.pausedByReader = !this.videoTarget.paused;
        if (this.pausedByReader) {
            this.videoTarget.pause();
        } else {
            this.videoTarget.play().catch(() => {});
        }
        this.toggleTarget.setAttribute('aria-pressed', String(this.pausedByReader));
    }

    shown() {
        this.mediaTarget.classList.add('is-playing');
        this.toggleTarget.hidden = false;
        this.legendTarget.textContent = this.legendValue;
    }
}
