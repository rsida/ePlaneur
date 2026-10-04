import { Controller } from '@hotwired/stimulus';

/*
 * Share actions: copy the link to the clipboard, native share sheet where the browser has one.
 */
export default class extends Controller {
    static targets = ['native', 'status'];
    static values = { url: String, title: String };

    connect() {
        if (this.hasNativeTarget && navigator.share) {
            this.nativeTarget.hidden = false;
        }
    }

    async copy() {
        try {
            await navigator.clipboard.writeText(this.urlValue);
            this.say('Lien copié');
        } catch {
            this.say('Copie impossible : sélectionnez l’adresse de la page');
        }
    }

    async native() {
        try {
            await navigator.share({ title: this.titleValue, url: this.urlValue });
        } catch {
            // Share sheet closed by the reader
        }
    }

    say(message) {
        this.statusTarget.textContent = message;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.statusTarget.textContent = ''; }, 3000);
    }
}
