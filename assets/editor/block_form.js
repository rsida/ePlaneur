/*
 * Settings panel of the selected block ("Bloc" tab), built from the block schema
 * (App\Content\Editor\BlockSchema). Fields edited in place in the canvas (`inline`) are not repeated
 * here. Each change calls onChange(path, value); adding, moving or removing list items calls
 * onChange with the whole list, then the panel is rebuilt.
 */
import { clone, getPath, summary } from './data.js';
import { thumbnail } from './media_picker.js';

let uid = 0;

function h(tag, attributes = {}, ...children) {
    const element = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) {
        if (value === false || value == null) continue;
        if (name === 'class') element.className = value;
        else if (name.startsWith('on')) element.addEventListener(name.slice(2), value);
        else if (name === 'value') element.value = value;
        else if (name in element && typeof value !== 'string') element[name] = value;
        else element.setAttribute(name, value === true ? '' : value);
    }
    element.append(...children.flat().filter((child) => child != null && child !== false));

    return element;
}

function iconButton(label, symbol, onclick, extraClass = '') {
    return h('button', { type: 'button', class: `btn btn-secondary btn-sm ep-mini ${extraClass}`, title: label, 'aria-label': label, onclick }, symbol);
}

/**
 * @param {HTMLElement} container
 * @param {{type: object, data: object, zoneLabel: string, icon: Node|null, sources: {load: function}, onChange: function, onRemove: function}} options
 */
export function renderBlockForm(container, options) {
    const { type, data } = options;
    const rerender = () => renderBlockForm(container, options);
    const context = { ...options, rerender };

    const fields = type.fields.filter((field) => !field.inline && !(field.name === 'headers' && type.fields.some((other) => other.widget === 'rows')));
    container.replaceChildren(...[
        h('div', { class: 'ep-block-form__head' },
            options.icon,
            h('h2', { class: 'ep-editor__panel-title' }, type.label),
        ),
        h('p', { class: 'form-text' }, type.description),
        ...fields.map((field) => fieldRow(field, getPath(data, field.name), field.name, context, type)),
        fields.length === 0 ? h('p', { class: 'ep-editor__tip' }, 'Ce bloc se modifie directement dans la page.') : null,
        h('p', { class: 'ep-block-form__meta' }, `Emplacement : ${options.zoneLabel}`),
        h('p', { class: 'ep-block-form__meta' }, 'Alt + Maj + ↑ / ↓ : déplacer · Échap : quitter la sélection'),
        h('button', { type: 'button', class: 'btn btn-link text-danger ep-block-form__remove', onclick: options.onRemove }, 'Supprimer ce bloc'),
    ].filter(Boolean));
}

function fieldRow(field, value, path, context, type) {
    const id = `ep-field-${++uid}`;
    const label = h('label', { class: `form-label${field.required ? ' required' : ''}`, for: id }, field.label);
    const help = field.help ? h('p', { class: 'form-text' }, field.help) : null;

    switch (field.widget) {
        case 'bool':
            // Same markup as EasyAdmin's switches (ea:Switch component)
            return h('div', { class: 'form-group' },
                h('div', { class: 'form-check ea-switch-check' },
                    h('span', { class: 'ea-switch' },
                        h('input', { class: 'ea-switch-input', type: 'checkbox', role: 'switch', id, checked: Boolean(value), onchange: (event) => context.onChange(path, event.target.checked) }),
                        h('span', { class: 'ea-switch-track', 'aria-hidden': 'true' }, h('span', { class: 'ea-switch-thumb' })),
                    ),
                    h('label', { class: 'form-check-label', for: id }, field.label),
                ),
                help,
            );
        case 'choice':
            return h('fieldset', { class: 'form-group' },
                h('legend', { class: 'form-label' }, field.label),
                field.choices.map((choice, index) => h('div', { class: 'form-check' },
                    h('input', { class: 'form-check-input', type: 'radio', name: id, id: `${id}-${index}`, value: choice.value, checked: value === choice.value, onchange: () => context.onChange(path, choice.value) }),
                    h('label', { class: 'form-check-label', for: `${id}-${index}` }, choice.label),
                )),
                help,
            );
        case 'textarea':
            return h('div', { class: 'form-group' }, label,
                h('textarea', { class: 'form-control', id, rows: 3, value: value ?? '', oninput: (event) => context.onChange(path, event.target.value) }),
                help);
        case 'rich':
            return h('div', { class: 'form-group' }, label, richEditor(id, value ?? '', (html) => context.onChange(path, html)), help);
        case 'number':
            return h('div', { class: 'form-group' }, label,
                h('input', { class: 'form-control', type: 'number', id, value: value ?? '', oninput: (event) => context.onChange(path, event.target.value === '' ? null : Number.parseInt(event.target.value, 10)) }),
                help);
        case 'media':
            return h('div', { class: 'form-group' }, label, mediaField(field, value, path, id, context), help);
        case 'document':
        case 'document_category':
            return h('div', { class: 'form-group' }, label, sourceSelect(field, value, path, id, context), help);
        case 'lines':
            return h('fieldset', { class: 'form-group' }, h('legend', { class: 'form-label' }, field.label), linesEditor(field, value ?? [], path, context), help);
        case 'items':
            return h('fieldset', { class: 'form-group' }, h('legend', { class: 'form-label' }, field.label), itemsEditor(field, value ?? [], path, context), help);
        case 'rows':
            return h('fieldset', { class: 'form-group' }, h('legend', { class: 'form-label' }, field.label), tableEditor(value ?? [], path, context, type), help);
        default:
            return h('div', { class: 'form-group' }, label,
                h('input', { class: 'form-control', type: field.widget === 'url' ? 'url' : 'text', id, value: value ?? '', oninput: (event) => context.onChange(path, event.target.value) }),
                help);
    }
}

/** Contenteditable with bold, italic, link and list buttons; the server sanitizes the HTML. */
function richEditor(id, html, onChange) {
    const area = h('div', { class: 'form-control ep-rich', id, contentEditable: 'true', role: 'textbox', 'aria-multiline': 'true' });
    area.innerHTML = html;
    area.addEventListener('input', () => onChange(area.innerHTML));
    area.addEventListener('paste', (event) => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
    });
    const command = (name, argument = null) => (event) => {
        event.preventDefault();
        area.focus();
        document.execCommand(name, false, argument);
        onChange(area.innerHTML);
    };
    const link = (event) => {
        event.preventDefault();
        const url = window.prompt('Adresse du lien (vide pour retirer le lien)', 'https://');
        area.focus();
        document.execCommand(url ? 'createLink' : 'unlink', false, url || null);
        onChange(area.innerHTML);
    };

    return h('div', { class: 'ep-rich-wrapper' },
        h('div', { class: 'ep-rich__tools', role: 'toolbar', 'aria-label': 'Mise en forme' },
            h('button', { type: 'button', class: 'ep-mini', title: 'Gras', onmousedown: command('bold') }, h('strong', {}, 'G')),
            h('button', { type: 'button', class: 'ep-mini', title: 'Italique', onmousedown: command('italic') }, h('em', {}, 'I')),
            h('button', { type: 'button', class: 'ep-mini', title: 'Lien', onmousedown: link }, '🔗'),
            h('button', { type: 'button', class: 'ep-mini', title: 'Liste', onmousedown: command('insertUnorderedList') }, '•'),
        ),
        area,
    );
}

/** Current media of the field, with buttons opening the media window. */
function mediaField(field, value, path, id, context) {
    const preview = h('div', { class: 'ep-media-field__preview' }, h('span', { class: 'ep-media-field__name' }, value ? 'Chargement…' : 'Aucun média'));
    context.mediaInfo(value).then((item) => {
        if (!item) {
            preview.replaceChildren(h('span', { class: 'ep-media-field__name' }, value ? 'Ce média n’existe plus.' : 'Aucun média'));

            return;
        }
        preview.replaceChildren(...[
            item.url ? Object.assign(thumbnail(item), { className: 'ep-media-field__image' }) : thumbnail(item),
            h('span', { class: 'ep-media-field__name' }, item.name),
            item.type === 'image' ? h('span', { class: 'form-text' }, item.alt ? `Texte alternatif : ${item.alt}` : 'Sans texte alternatif (image décorative)') : null,
            item.credit ? h('span', { class: 'form-text' }, `Crédit : ${item.credit}`) : null,
        ].filter(Boolean));
    });
    const choose = async () => {
        const item = await context.pickMedia(field.accept, value || null);
        if (!item) return;
        context.onChange(path, item.id);
        // In a list (files to download...), an empty title starts from the file name
        const titlePath = path.replace(/[^.]+$/, 'title');
        if (titlePath !== path && path.includes('.') && getPath(context.data, titlePath) === '') {
            context.onChange(titlePath, item.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '));
        }
        context.rerender();
    };

    return h('div', { class: 'ep-media-field' },
        preview,
        h('div', { class: 'ep-media-field__actions' },
            h('button', { type: 'button', class: 'btn btn-secondary btn-sm', id, onclick: choose }, value ? 'Changer…' : 'Choisir…'),
            value ? h('button', { type: 'button', class: 'btn btn-link btn-sm text-danger', onclick: () => { context.onChange(path, field.required ? 0 : null); context.rerender(); } }, 'Retirer') : null,
        ),
    );
}

/** Select of documents or document categories, loaded from the back-office. */
function sourceSelect(field, value, path, id, context) {
    const nullable = field.widget === 'document_category';
    const placeholder = field.widget === 'document_category' ? 'Toutes les catégories' : 'Choisir…';
    const select = h('select', { class: 'form-select', id, disabled: true },
        h('option', { value: '' }, 'Chargement…'));
    context.sources.load(field.widget, field.accept).then((items) => {
        select.replaceChildren(
            h('option', { value: '' }, placeholder),
            ...items.map((item) => h('option', { value: String(item.id), selected: item.id === value }, item.label)),
        );
        select.disabled = false;
        select.addEventListener('change', () => context.onChange(path, select.value === '' ? (nullable ? null : 0) : Number.parseInt(select.value, 10)));
    });

    return select;
}

function moveItem(list, from, to) {
    const copy = [...list];
    const [item] = copy.splice(from, 1);
    copy.splice(to, 0, item);

    return copy;
}

function linesEditor(field, lines, path, context) {
    const change = (list) => {
        context.onChange(path, list);
        context.rerender();
    };

    return h('div', { class: 'ep-lines' },
        lines.map((line, index) => h('div', { class: 'ep-lines__row' },
            h('input', { class: 'form-control', type: 'text', value: line ?? '', 'aria-label': `${field.label} ${index + 1}`, oninput: (event) => context.onChange(`${path}.${index}`, event.target.value) }),
            iconButton('Monter', '↑', () => index > 0 && change(moveItem(lines, index, index - 1))),
            iconButton('Descendre', '↓', () => index < lines.length - 1 && change(moveItem(lines, index, index + 1))),
            iconButton('Retirer', '×', () => change(lines.filter((_, other) => other !== index)), 'ep-mini--danger'),
        )),
        h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => change([...lines, '']) }, '+ Ajouter une ligne'),
    );
}

function itemsEditor(field, items, path, context) {
    const change = (list) => {
        context.onChange(path, list);
        context.rerender();
    };

    return h('div', { class: 'ep-items' },
        items.map((item, index) => h('details', { class: 'ep-item', open: items.length <= 3 },
            h('summary', {}, `${index + 1}. ${summary(item) || field.label}`),
            h('div', { class: 'ep-item__body' },
                field.item.fields.map((itemField) => fieldRow(itemField, item?.[itemField.name], `${path}.${index}.${itemField.name}`, context, null)),
                h('div', { class: 'ep-item__actions' },
                    iconButton('Monter', '↑', () => index > 0 && change(moveItem(items, index, index - 1))),
                    iconButton('Descendre', '↓', () => index < items.length - 1 && change(moveItem(items, index, index + 1))),
                    h('button', { type: 'button', class: 'btn btn-link btn-sm text-danger', onclick: () => change(items.filter((_, other) => other !== index)) }, 'Retirer'),
                ),
            ),
        )),
        h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => change([...items, clone(field.item.defaults)]) }, '+ Ajouter'),
    );
}

/** Rows of a table, with its header row (the "headers" field of the same block). */
function tableEditor(rows, path, context, type) {
    const hasHeaders = type?.fields.some((field) => field.name === 'headers');
    const headers = hasHeaders ? [...(context.data.headers ?? [])] : [];
    const columns = Math.max(1, headers.length, ...rows.map((row) => row.length));
    const pad = (row) => Array.from({ length: columns }, (_, index) => row?.[index] ?? '');
    const commit = (newHeaders, newRows) => {
        if (hasHeaders) context.onChange('headers', newHeaders);
        context.onChange(path, newRows);
        context.rerender();
    };
    const cell = (value, label, onInput, extraClass = '') => h('input', { class: `form-control form-control-sm ${extraClass}`, type: 'text', value: value ?? '', 'aria-label': label, oninput: (event) => onInput(event.target.value) });

    return h('div', { class: 'ep-table' },
        h('div', { class: 'ep-table__grid', style: `--ep-columns: ${columns}` },
            hasHeaders ? pad(headers).map((value, column) => cell(value, `En-tête de la colonne ${column + 1}`, (text) => context.onChange(`headers.${column}`, text), 'ep-table__head')) : null,
            rows.map((row, rowIndex) => pad(row).map((value, column) => cell(value, `Ligne ${rowIndex + 1}, colonne ${column + 1}`, (text) => context.onChange(`${path}.${rowIndex}.${column}`, text)))),
        ),
        h('div', { class: 'ep-table__actions' },
            h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => commit(pad(headers), [...rows.map(pad), pad([])]) }, '+ Ligne'),
            h('button', { type: 'button', class: 'btn btn-secondary btn-sm', disabled: rows.length <= 1, onclick: () => commit(pad(headers), rows.slice(0, -1).map(pad)) }, '− Ligne'),
            h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => commit([...pad(headers), ''], rows.map((row) => [...pad(row), ''])) }, '+ Colonne'),
            h('button', { type: 'button', class: 'btn btn-secondary btn-sm', disabled: columns <= 1, onclick: () => commit(pad(headers).slice(0, -1), rows.map((row) => pad(row).slice(0, -1))) }, '− Colonne'),
        ),
        h('p', { class: 'form-text' }, 'Les cellules se modifient aussi dans la page. Tab : cellule suivante · Maj + Tab : précédente.'),
    );
}
