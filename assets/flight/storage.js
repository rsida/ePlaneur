/*
 * Choices kept in the viewer's browser (layers shown, charts, instruments). Storage can be refused
 * (private window, blocked site data): nothing breaks then, the choice is just not kept.
 */

export function readStored(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

export function writeStored(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Not kept, that is all
    }
}
