import { Controller } from '@hotwired/stimulus';

/*
 * Submits a filter form as soon as a choice changes; its submit button is only needed without
 * JavaScript, so it is hidden. A `reset` param empties another field first (changing the year of the
 * news list resets the month).
 * Usage: data-controller="autosubmit" on the form, data-action="autosubmit#submit" on the fields,
 * data-autosubmit-reset-param="mois", data-autosubmit-target="button" on the submit button.
 */
export default class extends Controller {
    static targets = ['button'];

    connect() {
        this.buttonTargets.forEach((button) => {
            button.hidden = true;
        });
    }

    submit({ params: { reset } }) {
        if (reset && this.element.elements[reset]) {
            this.element.elements[reset].value = '';
        }
        this.element.requestSubmit();
    }
}
