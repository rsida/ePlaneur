/*
 * Page tree of the back-office (templates/admin/page/tree.html.twig): folds the sub-pages of a
 * page and filters the rows by title (a match keeps its ancestors visible).
 */
import { Controller } from '@hotwired/stimulus';
import { normalize } from './data.js';

export default class extends Controller {
    static targets = ['row'];

    toggle(event) {
        const button = event.currentTarget;
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.closest('tr').classList.toggle('is-folded', !expanded);
        this.refresh();
    }

    filter(event) {
        this.query = normalize(event.target.value);
        this.refresh();
    }

    refresh() {
        const folded = new Set(this.rowTargets.filter((row) => row.classList.contains('is-folded')).map((row) => row.dataset.id));
        const matches = new Set();
        if (this.query) {
            for (const row of this.rowTargets) {
                if (normalize(row.dataset.title).includes(this.query)) {
                    matches.add(row.dataset.id);
                    row.dataset.ancestors.split(' ').filter(Boolean).forEach((id) => matches.add(id));
                }
            }
        }
        for (const row of this.rowTargets) {
            const ancestors = row.dataset.ancestors.split(' ').filter(Boolean);
            row.hidden = this.query ? !matches.has(row.dataset.id) : ancestors.some((id) => folded.has(id));
        }
    }
}
