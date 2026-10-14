import { Controller } from '@hotwired/stimulus';

/*
 * "Installer l'application" in the footer (doc/installable-site.md): shown when the browser offers
 * to install the site (Chrome, Edge, Android), or as a hint on iPhone and iPad, where Safari installs
 * from its share menu; hidden once the site runs installed.
 */
export default class extends Controller {
    static targets = ['button', 'ios'];

    connect() {
        if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) return;
        this.onPrompt = (event) => {
            event.preventDefault();
            this.prompt = event;
            this.buttonTarget.hidden = false;
            this.element.hidden = false;
        };
        window.addEventListener('beforeinstallprompt', this.onPrompt);
        const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (ios) {
            this.iosTarget.hidden = false;
            this.element.hidden = false;
        }
    }

    disconnect() {
        window.removeEventListener('beforeinstallprompt', this.onPrompt);
    }

    async install() {
        if (!this.prompt) return;
        this.prompt.prompt();
        await this.prompt.userChoice;
        this.prompt = null;
        this.element.hidden = true;
    }
}
