import { Controller } from '@hotwired/stimulus';

/*
 * Flight plan of the session form: typing the code or the route suggests the flight plans already
 * recorded (a <datalist>); picking one fills in the address of its sheet. For a new code, the
 * address of its page in the eplaneur.fr library is suggested.
 */
export default class extends Controller {
    static targets = ['name', 'url', 'list'];
    static values = { searchUrl: String, sheet: String };

    connect() {
        this.plans = new Map();
    }

    search() {
        clearTimeout(this.timer);
        const text = this.nameTarget.value.trim();
        if (text.length < 2) {
            return;
        }
        this.timer = setTimeout(async () => {
            const url = new URL(this.searchUrlValue, window.location.origin);
            url.searchParams.set('q', text);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const plans = await response.json();
            this.listTarget.replaceChildren(...plans.map((plan) => {
                this.plans.set(plan.label, plan);
                const option = document.createElement('option');
                option.value = plan.label;
                return option;
            }));
        }, 200);
    }

    pick() {
        const plan = this.plans.get(this.nameTarget.value);
        if (plan) {
            this.urlTarget.value = plan.url ?? '';
            return;
        }
        const code = this.nameTarget.value.match(/(?<![A-Za-z0-9])(C[23]-[a-z]\d{3}[a-z]?)(?![A-Za-z0-9])/i);
        if (code && this.urlTarget.value === '') {
            const normalised = code[1].slice(0, 2).toUpperCase() + '-' + code[1].slice(3).toLowerCase();
            this.urlTarget.value = this.sheetValue.replace('%s', normalised);
        }
    }
}
