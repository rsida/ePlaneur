/*
 * Full screen for the replays (roadmap 7k5): the whole viewer (map, values beside it, replay bar),
 * with the Fullscreen API, or where it is missing (Safari on iPhone) as a layer over the page
 * (`is-fullscreen`, left with Escape or the button). `onChange(on)` follows every change, Escape
 * of the browser included, so that the map and the charts take their new size.
 */
export function bindFullscreen(element, button, onChange) {
    const native = typeof element.requestFullscreen === 'function' && document.fullscreenEnabled;
    const isOn = () => (native ? document.fullscreenElement === element : element.classList.contains('is-fullscreen'));
    const show = () => {
        const on = isOn();
        button.setAttribute('aria-pressed', String(on));
        button.querySelector('.p-flight__fullscreen-label').textContent = on ? 'Quitter' : 'Plein écran';
        button.title = on ? 'Quitter le plein écran (Échap)' : 'Afficher le replay en plein écran';
        onChange(on);
    };
    const toggle = async () => {
        if (native) {
            try {
                if (isOn()) await document.exitFullscreen();
                else await element.requestFullscreen();
            } catch {
                return;
            }
        } else {
            element.classList.toggle('is-fullscreen');
            document.documentElement.classList.toggle('has-fullscreen', isOn());
            show();
        }
    };
    const escape = (event) => {
        if (!native && event.key === 'Escape' && isOn()) toggle();
    };
    document.addEventListener('fullscreenchange', show);
    document.addEventListener('keydown', escape);
    button.addEventListener('click', toggle);

    return () => {
        document.removeEventListener('fullscreenchange', show);
        document.removeEventListener('keydown', escape);
        button.removeEventListener('click', toggle);
        if (!native) {
            element.classList.remove('is-fullscreen');
            document.documentElement.classList.remove('has-fullscreen');
        }
    };
}
