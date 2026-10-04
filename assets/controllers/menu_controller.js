import { Controller } from '@hotwired/stimulus';

/*
 * Site navigation (templates/components/Site/Header.html.twig).
 * - Desktop: a first-level button opens its mega-menu (one at a time); Escape or a click outside
 *   closes it.
 * - Mobile: the burger opens the full-screen panel, with the current section and group expanded;
 *   sections and groups are accordions.
 */
export default class extends Controller {
    static targets = ['toggle', 'section'];

    disconnect() {
        document.documentElement.classList.remove('is-menu-open');
    }

    toggle() {
        this.element.hasAttribute('data-open') ? this.close() : this.open();
    }

    open() {
        this.element.setAttribute('data-open', '');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('is-menu-open');
        const active = this.sectionTargets.find((section) => section.hasAttribute('data-active'));
        if (active && !this.sectionTargets.some((section) => section.getAttribute('aria-expanded') === 'true')) {
            this.expand(active, true);
        }
    }

    close() {
        this.element.removeAttribute('data-open');
        document.documentElement.classList.remove('is-menu-open');
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
        }
        this.sectionTargets.forEach((section) => this.expand(section, false));
    }

    closeOutside(event) {
        if (!this.element.contains(event.target)) {
            this.sectionTargets.forEach((section) => this.expand(section, false));
        }
    }

    section({ currentTarget }) {
        const expand = currentTarget.getAttribute('aria-expanded') !== 'true';
        this.sectionTargets.forEach((section) => this.expand(section, section === currentTarget && expand));
    }

    group({ currentTarget }) {
        const expanded = currentTarget.getAttribute('aria-expanded') === 'true';
        currentTarget.setAttribute('aria-expanded', String(!expanded));
    }

    expand(button, expanded) {
        button.setAttribute('aria-expanded', String(expanded));
        document.getElementById(button.getAttribute('aria-controls')).hidden = !expanded;
    }
}
