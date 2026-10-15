/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

/*
 * "Notifications sur cet appareil" in the profile (doc/installable-site.md#notifications): asks the
 * browser for the permission, subscribes with the VAPID key of the site and gives the subscription to
 * the server (src/Controller/PushController.php); turns it off, or sends a try to this device.
 */
export default class extends Controller {
    static targets = ['state', 'on', 'off', 'try'];
    static values = { key: String, token: String, subscribeUrl: String, unsubscribeUrl: String, tryUrl: String };

    async connect() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            this.show(ios
                ? 'Sur iPhone et iPad, installez d’abord le site sur l’écran d’accueil (Partager, puis « Sur l’écran d’accueil »), puis ouvrez-le depuis son icône.'
                : 'Ce navigateur ne sait pas recevoir de notifications.');

            return;
        }
        this.registration = await navigator.serviceWorker.ready;
        this.subscription = await this.registration.pushManager.getSubscription();
        this.render();
    }

    render(message = null) {
        if (Notification.permission === 'denied') {
            this.show('Les notifications sont bloquées pour ce site : autorisez-les dans les réglages du navigateur, puis rechargez la page.');

            return;
        }
        this.show(message ?? (this.subscription ? 'Activées sur cet appareil.' : 'Désactivées sur cet appareil.'), this.subscription ? ['off', 'try'] : ['on']);
    }

    async subscribe() {
        try {
            if (await Notification.requestPermission() !== 'granted') {
                this.render();

                return;
            }
            this.subscription = await this.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.keyValue });
            await this.post(this.subscribeUrlValue, this.subscription.toJSON());
            this.render('Activées sur cet appareil. Envoyez un essai pour vérifier.');
        } catch {
            await this.subscription?.unsubscribe();
            this.subscription = null;
            this.render('L’activation n’a pas abouti. Réessayez plus tard.');
        }
    }

    async unsubscribe() {
        if (!this.subscription) return;
        const { endpoint } = this.subscription;
        await this.subscription.unsubscribe();
        this.subscription = null;
        await this.post(this.unsubscribeUrlValue, { endpoint }).catch(() => {});
        this.render();
    }

    async try() {
        if (!this.subscription) return;
        try {
            await this.post(this.tryUrlValue, { endpoint: this.subscription.endpoint });
            this.render('Essai envoyé : la notification arrive dans quelques secondes.');
        } catch {
            this.render('L’essai n’a pas abouti. Désactivez puis réactivez les notifications.');
        }
    }

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify(body),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    }

    show(message, buttons = []) {
        this.stateTarget.textContent = message;
        for (const name of ['on', 'off', 'try']) {
            this[`${name}Target`].hidden = !buttons.includes(name);
        }
    }
}
