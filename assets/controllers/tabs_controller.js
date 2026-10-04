import { Controller } from '@hotwired/stimulus';

/*
 * Accessible tabs (WAI-ARIA tabs pattern): click, arrow keys, Home and End.
 * Markup: Block/Tabs.html.twig. Without JavaScript every panel stays visible.
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        const selected = this.tabTargets.findIndex((tab) => tab.getAttribute('aria-selected') === 'true');
        this.show(Math.max(selected, 0), false);
    }

    select(event) {
        this.show(this.tabTargets.indexOf(event.currentTarget));
    }

    navigate(event) {
        const index = this.tabTargets.indexOf(event.currentTarget);
        const last = this.tabTargets.length - 1;
        const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: last }[event.key];
        if (next === undefined) {
            return;
        }
        event.preventDefault();
        this.show((next + last + 1) % (last + 1));
    }

    show(index, focus = true) {
        this.tabTargets.forEach((tab, i) => {
            const active = i === index;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            this.panelTargets[i].hidden = !active;
            if (active && focus) {
                tab.focus();
            }
        });
    }
}
