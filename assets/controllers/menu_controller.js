import { Controller } from '@hotwired/stimulus';

/*
 * Mobile navigation: toggles the header panel.
 * Usage: data-controller="menu" on the header, data-menu-target="toggle" on the burger button.
 */
export default class extends Controller {
    static targets = ['toggle'];

    toggle() {
        this.element.hasAttribute('data-open') ? this.close() : this.open();
    }

    open() {
        this.element.setAttribute('data-open', '');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.element.removeAttribute('data-open');
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
        }
    }
}
