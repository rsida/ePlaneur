/*
 * The editor canvas: the iframe document showing the content with the site styles
 * (templates/admin/editor/canvas_*.html.twig). This module keeps one wrapper per block in each zone,
 * makes the marked values editable in place (data-ep-field, data-ep-meta), and draws the editing
 * tools inside the iframe: block toolbar, block menu ("/" command and inserter), drop line.
 * Every decision about the content is delegated to the handlers given by the controller.
 */
import { normalize } from './data.js';

const BLOCK_MIME = 'application/x-ep-block';
const MOVE_MIME = 'application/x-ep-move';

export class Canvas {
    /**
     * @param {HTMLIFrameElement} frame
     * @param {object} handlers callbacks of the block-editor controller
     */
    constructor(frame, handlers) {
        this.frame = frame;
        this.doc = frame.contentDocument;
        this.win = frame.contentWindow;
        this.handlers = handlers;
        this.rendered = new Map();
        this.selectedId = null;
        this.menu = null;

        this.doc.execCommand('defaultParagraphSeparator', false, 'p');
        this.buildTools();
        this.listen();
    }

    /* ---------- DOM helpers ---------- */

    element(tag, attributes = {}, ...children) {
        const element = this.doc.createElement(tag);
        for (const [name, value] of Object.entries(attributes)) {
            if (value === false || value == null) continue;
            element.setAttribute(name, value === true ? '' : value);
        }
        element.append(...children.filter((child) => child != null));

        return element;
    }

    icon(name) {
        const source = this.doc.querySelector(`#ep-icons`)?.content.querySelector(`[data-icon="${name}"] svg`);

        return source ? source.cloneNode(true) : this.doc.createTextNode('');
    }

    zone(name) {
        return this.doc.querySelector(`[data-ep-zone="${name}"]`);
    }

    wrapper(id) {
        return this.doc.querySelector(`.ep-block[data-ep-id="${CSS.escape(id)}"]`);
    }

    /* ---------- Rendering ---------- */

    /**
     * Puts the rendered fragments in place: the header, the table of contents and one wrapper per
     * block, in the order of the zones. The element being typed in is never replaced.
     *
     * @param {{header: string, toc: string, blocks: Object<string, string>}} rendered
     * @param {Object<string, Array<{id: string, type: string}>>} zones
     */
    apply(rendered, zones, { force = false } = {}) {
        const active = this.doc.activeElement;
        const editing = active?.isContentEditable ? active : null;

        const header = this.doc.querySelector('[data-ep-slot="header"]');
        if (header && rendered.header !== undefined && (force || !header.contains(editing))) {
            header.innerHTML = rendered.header;
        }
        const toc = this.doc.querySelector('[data-ep-slot="toc"]');
        if (toc && rendered.toc !== undefined) {
            toc.innerHTML = rendered.toc;
        }

        const seen = new Set();
        for (const [name, blocks] of Object.entries(zones)) {
            const zone = this.zone(name);
            if (!zone) continue;
            // Wrappers are only moved when out of place: moving a node takes the focus away
            let previous = null;
            for (const block of blocks) {
                seen.add(block.id);
                let wrapper = this.wrapper(block.id);
                if (!wrapper) {
                    wrapper = this.element('div', { class: 'ep-block', 'data-ep-id': block.id, 'data-ep-type': block.type });
                }
                const html = rendered.blocks[block.id];
                if (html !== undefined && (force || !wrapper.contains(editing)) && (force || this.rendered.get(block.id) !== html)) {
                    wrapper.innerHTML = html;
                    this.rendered.set(block.id, html);
                    // A block without content (no file chosen yet...) may render an empty container
                    if (wrapper.textContent.trim() === '' && !wrapper.querySelector('img, iframe, video, [data-ep-field]')) {
                        wrapper.replaceChildren(this.emptyBlock(block.type));
                    }
                }
                wrapper.dataset.epType = block.type;
                const expected = previous ? previous.nextElementSibling : zone.firstElementChild;
                if (expected !== wrapper) {
                    zone.insertBefore(wrapper, expected);
                }
                previous = wrapper;
            }
            const appender = this.appender(name, blocks.length === 0);
            if (zone.lastElementChild !== appender) {
                zone.append(appender);
            }
        }

        for (const wrapper of this.doc.querySelectorAll('.ep-block')) {
            if (!seen.has(wrapper.dataset.epId)) {
                this.rendered.delete(wrapper.dataset.epId);
                wrapper.remove();
            }
        }

        this.decorate();
        this.placeToolbar();
    }

    /** Placeholder of a block that renders nothing yet (an image without picture...). */
    emptyBlock(type) {
        const schema = this.handlers.typeOf(type);
        // The media of the block, or of the items of its list (files to download, carousel slides...)
        const media = schema?.fields.find((field) => field.widget === 'media')
            ?? schema?.fields.find((field) => field.widget === 'items')?.item.fields.find((field) => field.widget === 'media');
        if (!media) {
            return this.element('div', { class: 'ep-block__empty' },
                this.icon(schema?.icon ?? 'plus'),
                this.element('strong', {}, schema?.label ?? type),
                this.element('span', {}, 'À compléter dans les réglages du bloc, à droite.'),
            );
        }

        const noun = media.accept === 'image' ? 'une image' : (media.accept === 'pdf' ? 'un PDF' : 'un fichier');

        return this.element('div', { class: 'ep-block__empty ep-block__empty--media' },
            this.icon(schema.icon),
            this.element('strong', {}, `Ajoutez ${noun} à votre contenu`),
            this.element('button', { type: 'button', class: 'ep-canvas-button ep-canvas-button--primary', 'data-ep-pick': 'library' }, this.icon('images'), this.element('span', {}, 'Choisir dans la médiathèque')),
            this.handlers.canUpload() ? this.element('button', { type: 'button', class: 'ep-canvas-button', 'data-ep-pick': 'upload' }, this.icon('upload'), this.element('span', {}, 'Téléverser')) : null,
            this.element('span', { class: 'ep-block__empty-note' }, `${media.accept === 'image' ? 'JPG, PNG, WebP' : 'Images, PDF, texte ou ZIP'} · 20 Mo maximum · les autres réglages sont à droite`),
        );
    }

    appender(zone, empty) {
        let appender = this.zone(zone).querySelector(':scope > .ep-appender');
        if (!appender) {
            appender = this.element('button', { type: 'button', class: 'ep-appender', 'data-ep-append': zone, 'aria-label': 'Ajouter un bloc dans cette zone' }, this.icon('plus'));
        }
        appender.classList.toggle('ep-appender--empty', empty);

        return appender;
    }

    /** Makes the marked values editable and gives the empty ones their placeholder. */
    decorate() {
        for (const element of this.doc.querySelectorAll('[data-ep-field], [data-ep-meta]')) {
            if (!element.hasAttribute('contenteditable')) {
                const rich = element.dataset.epKind === 'rich';
                element.setAttribute('contenteditable', rich ? 'true' : 'plaintext-only');
                element.setAttribute('spellcheck', 'true');
                element.dataset.placeholder = this.placeholder(element);
            }
            element.classList.toggle('is-empty', element.textContent.trim() === '');
        }
    }

    placeholder(element) {
        if (element.dataset.epMeta) {
            return { title: 'Titre', kicker: 'Surtitre', excerpt: 'Chapô : une ou deux phrases qui donnent envie de lire' }[element.dataset.epMeta] ?? '';
        }
        const wrapper = element.closest('.ep-block');
        const type = this.handlers.typeOf(wrapper?.dataset.epType);
        if (wrapper?.dataset.epType === 'text') {
            return 'Écrivez, ou tapez / pour choisir un bloc';
        }
        let fields = type?.fields ?? [];
        let label = '';
        for (const key of element.dataset.epField.split('.')) {
            const field = fields.find((candidate) => candidate.name === key);
            if (field) {
                label = field.label;
                fields = field.item?.fields ?? [];
            }
        }

        return label;
    }

    /* ---------- Selection and toolbar ---------- */

    select(id) {
        this.selectedId = id;
        for (const wrapper of this.doc.querySelectorAll('.ep-block.is-selected')) {
            wrapper.classList.remove('is-selected');
        }
        const wrapper = id ? this.wrapper(id) : null;
        wrapper?.classList.add('is-selected');
        this.closeMore();
        this.placeToolbar();
    }

    scrollTo(id) {
        this.wrapper(id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /** Focuses the first editable value of a block (after an insertion). */
    focus(id) {
        const editable = this.wrapper(id)?.querySelector('[contenteditable]');
        if (!editable) return;
        editable.focus();
        const range = this.doc.createRange();
        range.selectNodeContents(editable);
        range.collapse(false);
        const selection = this.win.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    buildTools() {
        const button = (label, icon, attributes = {}) => this.element('button', { type: 'button', class: 'ep-toolbar__button', title: label, 'aria-label': label, ...attributes }, this.icon(icon));

        this.typeIcon = this.element('span', { class: 'ep-toolbar__type' });
        this.more = this.element('div', { class: 'ep-toolbar__menu', role: 'menu', hidden: true },
            this.menuItem('Dupliquer', 'copy', 'duplicate'),
            this.menuItem('Insérer avant', 'between-horizontal-start', 'insert-before'),
            this.menuItem('Insérer après', 'between-horizontal-end', 'insert-after'),
            this.menuItem('Supprimer', 'trash-2', 'remove', 'ep-toolbar__item--danger'),
        );
        this.formatButtons = [
            button('Gras (Ctrl + B)', 'bold', { 'data-format': 'bold' }),
            button('Italique (Ctrl + I)', 'italic', { 'data-format': 'italic' }),
            button('Lien', 'link', { 'data-format': 'link' }),
            button('Liste', 'list', { 'data-format': 'list' }),
        ];
        this.toolbar = this.element('div', { class: 'ep-toolbar', role: 'toolbar', 'aria-label': 'Outils du bloc', hidden: true },
            button('Glisser pour déplacer', 'grip-vertical', { class: 'ep-toolbar__button ep-toolbar__grip', draggable: 'true' }),
            this.typeIcon,
            this.mediaButton = button('Choisir le média', 'images', { 'data-command': 'media' }),
            button('Monter (Alt + Maj + ↑)', 'arrow-up', { 'data-command': 'up' }),
            button('Descendre (Alt + Maj + ↓)', 'arrow-down', { 'data-command': 'down' }),
            this.element('span', { class: 'ep-toolbar__separator' }),
            ...this.formatButtons,
            this.element('span', { class: 'ep-toolbar__separator' }),
            button('Plus d’actions', 'ellipsis', { 'data-command': 'more', 'aria-haspopup': 'menu' }),
            this.more,
        );
        this.dropLine = this.element('div', { class: 'ep-dropline', hidden: true }, this.element('span', {}));
        this.doc.body.append(this.toolbar, this.dropLine);
    }

    menuItem(label, icon, command, extraClass = '') {
        return this.element('button', { type: 'button', class: `ep-toolbar__item ${extraClass}`, role: 'menuitem', 'data-command': command }, this.icon(icon), this.element('span', {}, label));
    }

    placeToolbar() {
        const wrapper = this.selectedId ? this.wrapper(this.selectedId) : null;
        if (!wrapper) {
            this.toolbar.hidden = true;

            return;
        }
        const type = this.handlers.typeOf(wrapper.dataset.epType);
        this.typeIcon.replaceChildren(this.icon(type?.icon ?? 'plus'));
        this.typeIcon.title = type?.label ?? '';
        this.mediaButton.hidden = !type?.fields.some((field) => field.widget === 'media' || field.item?.fields.some((itemField) => itemField.widget === 'media'));
        const rich = wrapper.querySelector('[data-ep-kind="rich"]') !== null;
        for (const formatButton of this.formatButtons) {
            formatButton.hidden = !rich;
        }
        this.toolbar.hidden = false;
        const rect = wrapper.getBoundingClientRect();
        const top = rect.top + this.win.scrollY - this.toolbar.offsetHeight - 6;
        this.toolbar.style.top = `${Math.max(top, this.win.scrollY + 4)}px`;
        this.toolbar.style.left = `${Math.max(rect.left + this.win.scrollX, 4)}px`;
    }

    closeMore() {
        this.more.hidden = true;
    }

    format(name) {
        if (name === 'link') {
            const url = this.win.prompt('Adresse du lien (vide pour retirer le lien)', 'https://');
            this.doc.execCommand(url ? 'createLink' : 'unlink', false, url || null);
        } else {
            this.doc.execCommand(name === 'list' ? 'insertUnorderedList' : name, false, null);
        }
        const editable = this.doc.activeElement;
        if (editable?.dataset.epField) {
            this.fieldInput(editable);
        }
    }

    /* ---------- Block menu: "/" command and inserter ---------- */

    /**
     * @param {HTMLElement} anchor element under which the menu opens
     * @param {{query?: string, search?: boolean, onPick: function(string)}} options
     */
    openMenu(anchor, { query = '', search = false, onPick }) {
        this.closeMenu();
        const list = this.element('ul', { class: 'ep-menu__list', role: 'listbox', 'aria-label': 'Blocs' });
        const input = search ? this.element('input', { type: 'search', class: 'ep-menu__search', placeholder: 'Rechercher un bloc…', 'aria-label': 'Rechercher un bloc' }) : null;
        const menu = this.element('div', { class: 'ep-menu' },
            input,
            list,
            this.element('p', { class: 'ep-menu__hint' }, '↑ ↓ Choisir · Entrée Insérer · Échap Fermer'),
        );
        this.menu = { element: menu, list, input, items: [], active: 0, onPick };
        this.doc.body.append(menu);
        const rect = anchor.getBoundingClientRect();
        menu.style.top = `${rect.bottom + this.win.scrollY + 6}px`;
        menu.style.left = `${rect.left + this.win.scrollX}px`;
        this.filterMenu(query);
        if (input) {
            // Its keys (↑ ↓ Entrée Échap) reach the document listener, which hands them to menuKey()
            input.addEventListener('input', () => this.filterMenu(input.value));
            input.focus();
        }
    }

    filterMenu(query) {
        if (!this.menu) return;
        const words = normalize(query).split(/\s+/).filter(Boolean);
        const types = this.handlers.types().filter((type) => {
            const text = normalize(`${type.label} ${type.description}`);

            return words.every((word) => text.includes(word));
        });
        this.menu.items = types;
        this.menu.active = 0;
        this.menu.list.replaceChildren(...(types.length > 0
            ? types.map((type, index) => {
                const option = this.element('li', { class: 'ep-menu__item', role: 'option', 'aria-selected': index === 0 ? 'true' : 'false', 'data-type': type.type },
                    this.icon(type.icon),
                    this.element('span', { class: 'ep-menu__text' },
                        this.element('strong', {}, type.label),
                        this.element('span', {}, type.description)),
                    index === 0 ? this.icon('corner-down-left') : null,
                );
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    this.pickMenu(type.type);
                });

                return option;
            })
            : [this.element('li', { class: 'ep-menu__empty' }, 'Aucun bloc trouvé. Essayez « texte », « image » ou « encadré ».')]));
    }

    menuKey(event) {
        if (!this.menu) return false;
        const count = this.menu.items.length;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (count === 0) return true;
            this.menu.active = (this.menu.active + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
            [...this.menu.list.children].forEach((option, index) => option.setAttribute('aria-selected', index === this.menu.active ? 'true' : 'false'));
            this.menu.list.children[this.menu.active]?.scrollIntoView({ block: 'nearest' });

            return true;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            const type = this.menu.items[this.menu.active];
            if (type) this.pickMenu(type.type);

            return true;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            this.closeMenu();

            return true;
        }

        return false;
    }

    pickMenu(type) {
        const { onPick } = this.menu;
        this.closeMenu();
        onPick(type);
    }

    closeMenu() {
        this.menu?.element.remove();
        this.menu = null;
    }

    /* ---------- Events ---------- */

    listen() {
        const doc = this.doc;

        doc.addEventListener('click', (event) => {
            const target = event.target;
            if (target.closest('a')) event.preventDefault();
            if (!target.closest('.ep-menu')) this.closeMenu();

            const commandButton = target.closest('.ep-toolbar [data-command]');
            if (commandButton) {
                const command = commandButton.dataset.command;
                if (command === 'media') {
                    this.closeMore();
                    this.handlers.onPickMedia(this.selectedId);
                } else if (command === 'more') {
                    this.more.hidden = !this.more.hidden;
                } else if (command === 'insert-before' || command === 'insert-after') {
                    this.closeMore();
                    this.openMenu(this.wrapper(this.selectedId) ?? commandButton, { search: true, onPick: (type) => this.handlers.onCommand(command, this.selectedId, type) });
                } else {
                    this.closeMore();
                    this.handlers.onCommand(command, this.selectedId);
                }

                return;
            }
            const pick = target.closest('[data-ep-pick]');
            if (pick) {
                const wrapper = pick.closest('.ep-block');
                this.handlers.onSelect(wrapper.dataset.epId);
                this.handlers.onPickMedia(wrapper.dataset.epId, pick.dataset.epPick);

                return;
            }
            const appender = target.closest('[data-ep-append]');
            if (appender) {
                this.handlers.onAppend(appender.dataset.epAppend);

                return;
            }
            if (target.closest('.ep-toolbar')) return;

            const wrapper = target.closest('.ep-block');
            this.closeMore();
            this.handlers.onSelect(wrapper ? wrapper.dataset.epId : null);
        });

        doc.addEventListener('mousedown', (event) => {
            const formatButton = event.target.closest('[data-format]');
            if (formatButton) {
                event.preventDefault();
                this.format(formatButton.dataset.format);
            }
        });

        doc.addEventListener('submit', (event) => event.preventDefault());

        doc.addEventListener('focusin', (event) => {
            const wrapper = event.target.closest?.('.ep-block');
            if (wrapper && wrapper.dataset.epId !== this.selectedId) {
                this.handlers.onSelect(wrapper.dataset.epId);
            }
        });

        doc.addEventListener('input', (event) => {
            const target = event.target;
            if (target.dataset?.epField) {
                this.fieldInput(target);
            } else if (target.dataset?.epMeta) {
                target.classList.toggle('is-empty', target.textContent.trim() === '');
                this.handlers.onMetaInput(target.dataset.epMeta, target.textContent);
            }
        });

        doc.addEventListener('paste', (event) => {
            if (!event.target.closest?.('[contenteditable]')) return;
            event.preventDefault();
            doc.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        });

        doc.addEventListener('keydown', (event) => {
            if (this.menuKey(event)) return;
            const target = event.target;
            const editable = target.closest?.('[data-ep-field], [data-ep-meta]');
            if (editable && event.key === 'Enter' && editable.getAttribute('contenteditable') === 'plaintext-only') {
                event.preventDefault();

                return;
            }
            const wrapper = editable?.closest('.ep-block');
            if (editable && wrapper && event.key === 'Backspace' && wrapper.dataset.epType === 'text' && editable.textContent === '') {
                event.preventDefault();
                this.handlers.onRemoveEmpty(wrapper.dataset.epId);

                return;
            }
            this.handlers.onKey(event);
        });

        this.win.addEventListener('resize', () => this.placeToolbar());

        // Drag and drop: blocks from the library (other document) and blocks moved by their grip
        doc.addEventListener('dragstart', (event) => {
            if (!event.target.closest?.('.ep-toolbar__grip') || !this.selectedId) return;
            event.dataTransfer.setData(MOVE_MIME, this.selectedId);
            event.dataTransfer.setData('text/plain', '');
            event.dataTransfer.effectAllowed = 'move';
            const wrapper = this.wrapper(this.selectedId);
            if (wrapper) event.dataTransfer.setDragImage(wrapper, 20, 20);
        });
        doc.addEventListener('dragover', (event) => {
            const types = [...event.dataTransfer.types];
            const moving = types.includes(MOVE_MIME);
            if (!moving && !types.includes(BLOCK_MIME)) return;
            const target = this.dropTarget(event);
            if (!target) {
                this.hideDropLine();

                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = moving ? 'move' : 'copy';
            this.showDropLine(target, moving ? 'Déplacer le bloc ici' : `Insérer ${this.handlers.draggedLabel() ?? 'le bloc'} ici`);
        });
        doc.addEventListener('drop', (event) => {
            const target = this.dropTarget(event);
            this.hideDropLine();
            if (!target) return;
            event.preventDefault();
            const moved = event.dataTransfer.getData(MOVE_MIME);
            const type = event.dataTransfer.getData(BLOCK_MIME);
            if (moved) {
                this.handlers.onMove(moved, target.zone, target.index);
            } else if (type) {
                this.handlers.onInsert(type, target.zone, target.index);
            }
        });
        doc.addEventListener('dragleave', (event) => {
            if (event.relatedTarget === null) this.hideDropLine();
        });
        doc.addEventListener('dragend', () => this.hideDropLine());
    }

    fieldInput(element) {
        const wrapper = element.closest('.ep-block');
        if (!wrapper) return;
        element.classList.toggle('is-empty', element.textContent.trim() === '');
        const rich = element.dataset.epKind === 'rich';
        const value = rich ? element.innerHTML : element.textContent;
        this.rendered.delete(wrapper.dataset.epId);
        this.handlers.onFieldInput(wrapper.dataset.epId, element.dataset.epField, value);

        // "/" command: a text block holding only "/query"
        if (wrapper.dataset.epType === 'text') {
            const match = element.textContent.match(/^\/(\S*)$/);
            if (match) {
                if (this.menu) this.filterMenu(match[1]);
                else this.openMenu(element, { query: match[1], onPick: (type) => this.handlers.onSlashPick(wrapper.dataset.epId, type) });
            } else {
                this.closeMenu();
            }
        }
    }

    /** Zone and position under the pointer while dragging. */
    dropTarget(event) {
        const zone = event.target.closest?.('[data-ep-zone]');
        if (!zone) return null;
        const wrappers = [...zone.querySelectorAll(':scope > .ep-block')];
        let index = wrappers.findIndex((wrapper) => {
            const rect = wrapper.getBoundingClientRect();

            return event.clientY < rect.top + rect.height / 2;
        });
        if (index === -1) index = wrappers.length;

        return { zone: zone.dataset.epZone, index, element: zone, wrappers };
    }

    showDropLine(target, label) {
        const zoneRect = target.element.getBoundingClientRect();
        let y;
        if (target.wrappers.length === 0) {
            y = zoneRect.top + 8;
        } else if (target.index < target.wrappers.length) {
            y = target.wrappers[target.index].getBoundingClientRect().top - 6;
        } else {
            y = target.wrappers[target.wrappers.length - 1].getBoundingClientRect().bottom + 6;
        }
        this.dropLine.hidden = false;
        this.dropLine.style.top = `${y + this.win.scrollY}px`;
        this.dropLine.style.left = `${zoneRect.left + this.win.scrollX}px`;
        this.dropLine.style.width = `${zoneRect.width}px`;
        this.dropLine.firstElementChild.textContent = label;
    }

    hideDropLine() {
        this.dropLine.hidden = true;
    }
}

export { BLOCK_MIME };
