import './stimulus_bootstrap.js';
import './styles/app.css';

// Installable site (doc/installable-site.md): the service worker keeps the page "Hors ligne"
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
