import { Controller } from '@hotwired/stimulus';

/*
 * Checklist: counts ticked boxes and keeps them in the reader's browser (localStorage), per page
 * and per block, so the list is still ticked when they come back.
 */
export default class extends Controller {
    static targets = ['box', 'count'];
    static values = { key: String };

    connect() {
        const saved = this.read();
        this.boxTargets.forEach((box, index) => {
            box.checked = saved.includes(index);
        });
        this.refresh();
    }

    update() {
        const checked = this.boxTargets.flatMap((box, index) => (box.checked ? [index] : []));
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(checked));
        } catch {
            // Private browsing or storage full: the count still works for this visit
        }
        this.refresh();
    }

    refresh() {
        this.countTarget.textContent = String(this.boxTargets.filter((box) => box.checked).length);
    }

    read() {
        try {
            const value = JSON.parse(localStorage.getItem(this.storageKey) ?? '[]');
            return Array.isArray(value) ? value : [];
        } catch {
            return [];
        }
    }

    get storageKey() {
        return `checklist:${window.location.pathname}:${this.keyValue}`;
    }
}
