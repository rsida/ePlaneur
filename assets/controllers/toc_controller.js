import { Controller } from '@hotwired/stimulus';

/*
 * Table of contents: reading progress of the article body and current section highlight.
 * Collapsed below 64em (closes after a link is followed), always open above.
 */
export default class extends Controller {
    static targets = ['bar', 'percent', 'link'];

    connect() {
        this.wide = window.matchMedia('(min-width: 64em)');
        this.syncOpen = () => { this.element.open = this.wide.matches; };
        this.syncOpen();
        this.wide.addEventListener('change', this.syncOpen);

        this.body = document.querySelector('[data-toc-content]');
        this.sections = this.linkTargets
            .map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))))
            .filter(Boolean);

        this.update = () => {
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => this.refresh());
        };
        window.addEventListener('scroll', this.update, { passive: true });
        window.addEventListener('resize', this.update);
        this.refresh();
    }

    disconnect() {
        window.removeEventListener('scroll', this.update);
        window.removeEventListener('resize', this.update);
        this.wide.removeEventListener('change', this.syncOpen);
    }

    close() {
        if (!this.wide.matches) {
            this.element.open = false;
        }
    }

    refresh() {
        if (this.body) {
            const box = this.body.getBoundingClientRect();
            const read = Math.min(Math.max((window.innerHeight - box.top) / box.height, 0), 1);
            const percent = Math.round(read * 100);
            this.barTarget.style.width = `${percent}%`;
            this.percentTarget.textContent = String(percent);
        }

        // Current section: the last one whose title has passed the upper third of the screen
        const limit = window.innerHeight / 3;
        let current = -1;
        this.sections.forEach((section, index) => {
            if (section.getBoundingClientRect().top <= limit) {
                current = index;
            }
        });
        this.linkTargets.forEach((link, index) => {
            if (index === Math.max(current, 0)) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }
}
