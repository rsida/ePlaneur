import { Controller } from '@hotwired/stimulus';

/*
 * Switches the layout of a list (e.g. documents: compact list or cards) by setting data-layout on
 * the element; the toggle buttons carry their value and aria-pressed.
 * Usage: data-controller="layout" data-layout="list", buttons with data-layout-target="toggle"
 * data-layout-value-param="cards" data-action="layout#switch".
 */
export default class extends Controller {
    static targets = ['toggle'];

    switch({ params: { value } }) {
        this.element.dataset.layout = value;
        this.toggleTargets.forEach((toggle) => {
            toggle.setAttribute('aria-pressed', String(toggle.dataset.layoutValueParam === value));
        });
    }
}
