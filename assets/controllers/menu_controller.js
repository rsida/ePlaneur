import { Controller } from '@hotwired/stimulus';

/*
 * Site navigation (templates/components/Site/Header.html.twig).
 * - Desktop: a first-level button opens its mega-menu (one at a time); Escape or a click outside
 *   closes it.
 * - Mobile: the burger opens the full-screen panel, with the current section and group expanded;
 *   sections and groups are accordions. The rest of the page cannot be reached meanwhile (inert)
 *   and Escape gives the focus back to the burger.
 */
export default class extends Controller {
    static targets = ['toggle', 'section'];

    connect() {
        // The mobile panel does not exist on wide screens (site.css, 64em)
        this.wide = window.matchMedia('(min-width: 64em)');
        this.onWide = () => {
            if (this.wide.matches && this.element.hasAttribute('data-open')) this.close();
        };
        this.wide.addEventListener('change', this.onWide);
    }

    disconnect() {
        this.wide.removeEventListener('change', this.onWide);
        document.documentElement.classList.remove('is-menu-open');
        this.setPageInert(false);
    }

    toggle() {
        this.element.hasAttribute('data-open') ? this.close() : this.open();
    }

    open() {
        this.element.setAttribute('data-open', '');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('is-menu-open');
        this.setPageInert(true);
        const active = this.sectionTargets.find((section) => section.hasAttribute('data-active'));
        if (active && !this.sectionTargets.some((section) => section.getAttribute('aria-expanded') === 'true')) {
            this.expand(active, true);
        }
        this.element.querySelector('#site-menu')?.querySelector('a[href], button')?.focus();
    }

    close(event) {
        const wasOpen = this.element.hasAttribute('data-open');
        // Escape in a mega-menu: the focus goes back to its first-level button
        const section = this.sectionTargets.find((button) => button.getAttribute('aria-expanded') === 'true');
        const focusInSection = section && document.getElementById(section.getAttribute('aria-controls'))?.contains(document.activeElement);
        this.element.removeAttribute('data-open');
        document.documentElement.classList.remove('is-menu-open');
        this.setPageInert(false);
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
        }
        this.sectionTargets.forEach((button) => this.expand(button, false));
        if (event?.type !== 'keydown') return;
        if (wasOpen && this.hasToggleTarget) {
            this.toggleTarget.focus();
        } else if (focusInSection) {
            section.focus();
        }
    }

    /** Everything but the header, while the mobile panel covers the page. */
    setPageInert(inert) {
        for (const element of document.body.children) {
            if (!element.contains(this.element)) element.inert = inert;
        }
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
