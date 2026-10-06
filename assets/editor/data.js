/*
 * Small helpers of the block editor: values addressed by a dotted path ("steps.0.title"), deep
 * copies, search normalisation and slugs.
 */

export function clone(value) {
    return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
}

export function getPath(data, path) {
    return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), data);
}

export function setPath(data, path, value) {
    const keys = path.split('.');
    const last = keys.pop();
    let target = data;
    for (const [index, key] of keys.entries()) {
        if (target[key] == null || typeof target[key] !== 'object') {
            target[key] = /^\d+$/.test(keys[index + 1] ?? last) ? [] : {};
        }
        target = target[key];
    }
    target[last] = value;
}

/** Lower case without accents, for searches ("Écrire" matches "ecr"). */
export function normalize(text) {
    return String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}

/** URL segment from a title: "Votre premier vol !" → "votre-premier-vol". */
export function slugify(text) {
    return normalize(text).replace(/['’]/g, '-').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
}

/** First text of a block, for the structure list: "Section · Premières ailes". */
export function summary(data) {
    const texts = [];
    const walk = (value) => {
        if (texts.length > 0 || value == null) return;
        if (typeof value === 'string') {
            const text = value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            if (text !== '' && !/^https?:\/\//.test(text)) texts.push(text);
        } else if (Array.isArray(value)) {
            value.forEach(walk);
        } else if (typeof value === 'object') {
            Object.values(value).forEach(walk);
        }
    };
    walk(data);

    return texts.length > 0 ? (texts[0].length > 42 ? `${texts[0].slice(0, 40)}…` : texts[0]) : '';
}
