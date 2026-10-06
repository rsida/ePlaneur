/*
 * Block editor of posts and pages (templates/admin/editor/editor.html.twig).
 *
 * The controller owns the blocks of every zone ({body: [{id, type, data}], ...}) and the undo
 * history. After each change it writes the zones into their hidden form fields and asks the server
 * to render them (`render` action of the CRUD controller): the canvas shows the blocks with the site
 * components, never with a second renderer in JavaScript. The library (drag and drop, search), the
 * structure list, the settings panel and the publish dialog live in this document; the block tools
 * live in the canvas iframe (canvas.js).
 */
import { Controller } from '@hotwired/stimulus';
import { Canvas, BLOCK_MIME } from './canvas.js';
import { renderBlockForm } from './block_form.js';
import { MediaPicker, thumbnail } from './media_picker.js';
import { clone, normalize, setPath, slugify, summary } from './data.js';

const RENDER_DELAY = 250;
const TYPING_GROUP_DELAY = 1000;
const HISTORY_LIMIT = 100;

export default class extends Controller {
    static targets = [
        'form', 'frame', 'title', 'status', 'save', 'undo', 'redo', 'path', 'workspace',
        'library', 'libraryGroup', 'libraryItem', 'structure', 'settings', 'blockTab', 'blockPanel',
        'publishDialog', 'publishDate', 'publishLabel', 'publishVisibility', 'publishTitle', 'mediaDialog',
    ];

    static values = { kind: String, formName: String, schema: Object, urls: Object, zones: Object };

    connect() {
        this.types = new Map(this.schemaValue.types.map((type) => [type.type, type]));
        this.counter = 0;
        this.blocks = {};
        for (const zone of Object.keys(this.zonesValue)) {
            const field = this.zoneField(zone);
            let stored = [];
            try {
                stored = JSON.parse(field?.value || '[]');
            } catch {
                stored = [];
            }
            // Stored data may omit values equal to their default: the panel needs them all
            this.blocks[zone] = stored.map((block) => ({ id: this.newId(), type: block.type, data: { ...clone(this.types.get(block.type)?.defaults ?? {}), ...(block.data ?? {}) } }));
        }
        this.selectedId = null;
        this.history = [];
        this.future = [];
        this.lastTyping = 0;
        this.dirty = this.statusTarget.textContent.includes('non enregistrées');
        this.submitting = false;
        this.sourceCache = new Map();
        this.mediaCache = new Map();
        this.mediaPicker = new MediaPicker(this.mediaDialogTarget, { media: this.urlsValue.media, upload: this.urlsValue.media_upload, update: this.urlsValue.media_update });
        this.renderSequence = 0;
        this.slugTouched = this.field('slug')?.value !== '';
        this.renderStructure();

        if (this.frameTarget.contentDocument?.readyState === 'complete' && this.frameTarget.contentDocument.querySelector('[data-ep-zone]')) {
            this.frameLoaded();
        }
    }

    disconnect() {
        clearTimeout(this.renderTimer);
    }

    /* ---------- State ---------- */

    newId() {
        this.counter += 1;

        return `b${this.counter}`;
    }

    zoneField(zone) {
        return this.formTarget.querySelector(`[name="${this.formNameValue}[${zone}]"]`);
    }

    field(name) {
        return this.formTarget.querySelector(`[name="${this.formNameValue}[${name}]"]`);
    }

    find(id) {
        for (const [zone, blocks] of Object.entries(this.blocks)) {
            const index = blocks.findIndex((block) => block.id === id);
            if (index !== -1) return { zone, index, block: blocks[index] };
        }

        return null;
    }

    newBlock(type) {
        return { id: this.newId(), type, data: clone(this.types.get(type)?.defaults ?? {}) };
    }

    /** Remembers the state before a change, for undo. Typing in one value counts as one change. */
    remember({ typing = false } = {}) {
        const now = Date.now();
        if (typing && now - this.lastTyping < TYPING_GROUP_DELAY) {
            this.lastTyping = now;

            return;
        }
        this.lastTyping = typing ? now : 0;
        this.history.push(JSON.stringify(this.blocks));
        if (this.history.length > HISTORY_LIMIT) this.history.shift();
        this.future = [];
        this.updateHistoryButtons();
    }

    /** After every change: hidden fields, "unsaved" status, structure, canvas. */
    changed({ structure = true, panel = false } = {}) {
        for (const [zone, blocks] of Object.entries(this.blocks)) {
            const field = this.zoneField(zone);
            if (field) field.value = JSON.stringify(blocks.map(({ type, data }) => ({ type, data })));
        }
        this.markDirty();
        if (structure) this.renderStructure();
        if (panel) this.renderPanel();
        this.scheduleRender();
    }

    markDirty() {
        this.dirty = true;
        this.statusTarget.textContent = 'Modifications non enregistrées';
    }

    restore(snapshot) {
        this.blocks = JSON.parse(snapshot);
        if (this.selectedId && !this.find(this.selectedId)) this.selectedId = null;
        this.canvas?.closeMenu();
        this.frameTarget.contentDocument?.activeElement?.blur();
        this.changed({ panel: true });
        this.render({ force: true });
    }

    undo() {
        if (this.history.length === 0) return;
        this.future.push(JSON.stringify(this.blocks));
        this.restore(this.history.pop());
        this.updateHistoryButtons();
    }

    redo() {
        if (this.future.length === 0) return;
        this.history.push(JSON.stringify(this.blocks));
        this.restore(this.future.pop());
        this.updateHistoryButtons();
    }

    updateHistoryButtons() {
        this.undoTarget.disabled = this.history.length === 0;
        this.redoTarget.disabled = this.future.length === 0;
    }

    /* ---------- Block operations ---------- */

    insert(type, zone, index, { focus = true } = {}) {
        if (!this.types.has(type) || !this.blocks[zone]) return null;
        this.remember();
        const block = this.newBlock(type);
        this.blocks[zone].splice(Math.max(0, Math.min(index, this.blocks[zone].length)), 0, block);
        this.changed();
        this.select(block.id);
        this.render().then(() => {
            this.canvas?.select(block.id);
            this.canvas?.scrollTo(block.id);
            if (focus) this.canvas?.focus(block.id);
        });

        return block;
    }

    move(id, zone, index) {
        const found = this.find(id);
        if (!found || !this.blocks[zone]) return;
        let target = index;
        if (found.zone === zone && found.index < index) target -= 1;
        if (found.zone === zone && found.index === target) return;
        this.remember();
        this.blocks[found.zone].splice(found.index, 1);
        this.blocks[zone].splice(Math.max(0, Math.min(target, this.blocks[zone].length)), 0, found.block);
        this.changed({ panel: true });
        this.render().then(() => this.canvas?.scrollTo(id));
    }

    remove(id) {
        const found = this.find(id);
        if (!found) return;
        this.remember();
        this.blocks[found.zone].splice(found.index, 1);
        const neighbour = this.blocks[found.zone][Math.max(0, found.index - 1)];
        this.select(neighbour?.id ?? null);
        this.changed({ panel: true });

        return neighbour;
    }

    duplicate(id) {
        const found = this.find(id);
        if (!found) return;
        this.remember();
        const copy = { id: this.newId(), type: found.block.type, data: clone(found.block.data) };
        this.blocks[found.zone].splice(found.index + 1, 0, copy);
        this.changed();
        this.select(copy.id);
        this.render().then(() => this.canvas?.select(copy.id));
    }

    select(id) {
        this.selectedId = id;
        this.canvas?.select(id);
        this.renderPanel();
        this.renderStructure();
        if (id) this.showTab(this.blockTabTarget);
    }

    /* ---------- Canvas ---------- */

    frameLoaded() {
        const doc = this.frameTarget.contentDocument;
        if (!doc?.querySelector('[data-ep-zone]')) return;
        this.canvas = new Canvas(this.frameTarget, {
            typeOf: (type) => this.types.get(type),
            types: () => [...this.types.values()],
            draggedLabel: () => this.types.get(this.draggedType)?.label,
            onSelect: (id) => this.select(id),
            onFieldInput: (id, path, value) => {
                const found = this.find(id);
                if (!found) return;
                this.remember({ typing: true });
                setPath(found.block.data, path, value);
                this.changed();
                if (id === this.selectedId && !this.settingsTarget.contains(document.activeElement)) this.schedulePanel();
            },
            onMetaInput: (name, value) => {
                const input = this.field(name);
                if (input) {
                    input.value = value;
                    this.metaChanged(name);
                }
            },
            onCommand: (command, id, type) => this.command(command, id, type),
            onAppend: (zone) => this.insert('text', zone, this.blocks[zone].length),
            onInsert: (type, zone, index) => this.insert(type, zone, index),
            onMove: (id, zone, index) => this.move(id, zone, index),
            onSlashPick: (id, type) => {
                const found = this.find(id);
                if (!found) return;
                this.remember();
                const block = this.newBlock(type);
                this.blocks[found.zone].splice(found.index, 1, block);
                this.changed();
                this.select(block.id);
                this.render({ force: true }).then(() => {
                    this.canvas.select(block.id);
                    this.canvas.focus(block.id);
                });
            },
            onRemoveEmpty: (id) => {
                const neighbour = this.remove(id);
                this.render().then(() => neighbour && this.canvas.focus(neighbour.id));
            },
            onPickMedia: (id, tab = 'library') => this.pickBlockMedia(id, tab),
            canUpload: () => this.mediaDialogTarget.querySelector('[data-media-tab="upload"]') !== null,
            onKey: (event) => this.shortcut(event),
        });
        this.canvas.rendered.clear();
        this.render({ force: true });
    }

    scheduleRender() {
        clearTimeout(this.renderTimer);
        this.renderTimer = setTimeout(() => this.render(), RENDER_DELAY);
    }

    /** Asks the server for the header and blocks of the current state, then updates the canvas. */
    async render({ force = false } = {}) {
        clearTimeout(this.renderTimer);
        if (!this.canvas) return;
        const sequence = ++this.renderSequence;
        const body = new FormData(this.formTarget);
        body.set('zones', JSON.stringify(this.blocks));
        try {
            const response = await fetch(this.urlsValue.render, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const rendered = await response.json();
            if (sequence !== this.renderSequence) return;
            this.canvas.apply(rendered, this.blocks, { force });
            this.canvas.select(this.selectedId);
        } catch (error) {
            this.statusTarget.textContent = 'Aperçu indisponible : vérifiez la connexion.';
            console.error(error);
        }
    }

    command(command, id, type = null) {
        const found = this.find(id);
        if (!found) return;
        switch (command) {
            case 'up':
                if (found.index > 0) this.move(id, found.zone, found.index - 1);
                break;
            case 'down':
                if (found.index < this.blocks[found.zone].length - 1) this.move(id, found.zone, found.index + 2);
                break;
            case 'duplicate':
                this.duplicate(id);
                break;
            case 'insert-before':
                this.insert(type, found.zone, found.index);
                break;
            case 'insert-after':
                this.insert(type, found.zone, found.index + 1);
                break;
            case 'remove':
                this.remove(id);
                break;
        }
    }

    /* ---------- Library ---------- */

    insertFromLibrary(event) {
        const type = event.currentTarget.dataset.type;
        if (this.isNarrow()) this.closePanels();
        const found = this.selectedId ? this.find(this.selectedId) : null;
        if (found) {
            this.insert(type, found.zone, found.index + 1);
        } else {
            this.insert(type, 'body', this.blocks.body.length);
        }
    }

    dragFromLibrary(event) {
        this.draggedType = event.currentTarget.dataset.type;
        // The canvas must be reachable to drop the block
        if (this.isNarrow()) setTimeout(() => this.closePanels(), 0);
        event.dataTransfer.setData(BLOCK_MIME, this.draggedType);
        event.dataTransfer.setData('text/plain', this.types.get(this.draggedType)?.label ?? '');
        event.dataTransfer.effectAllowed = 'copy';
    }

    endDrag() {
        this.draggedType = null;
        this.canvas?.hideDropLine();
    }

    filterLibrary(event) {
        const words = normalize(event.target.value).split(/\s+/).filter(Boolean);
        for (const item of this.libraryItemTargets) {
            const text = normalize(item.dataset.search);
            item.hidden = !words.every((word) => text.includes(word));
        }
        for (const group of this.libraryGroupTargets) {
            group.hidden = group.querySelector('li:not([hidden])') === null;
        }
    }

    renderStructure() {
        const list = document.createElement('div');
        for (const [zone, label] of Object.entries(this.zonesValue)) {
            const section = document.createElement('section');
            section.className = 'ep-structure__zone';
            const title = document.createElement('p');
            title.className = 'ep-structure__title';
            title.textContent = label;
            const items = document.createElement('ol');
            items.className = 'ep-structure__list';
            for (const block of this.blocks[zone] ?? []) {
                const type = this.types.get(block.type);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ep-structure__item';
                if (block.id === this.selectedId) button.setAttribute('aria-current', 'true');
                const text = summary(block.data);
                button.textContent = text ? `${type?.label ?? block.type} · ${text}` : (type?.label ?? block.type);
                button.addEventListener('click', () => {
                    this.select(block.id);
                    this.canvas?.scrollTo(block.id);
                });
                const item = document.createElement('li');
                item.append(button);
                items.append(item);
            }
            if ((this.blocks[zone] ?? []).length === 0) {
                const empty = document.createElement('li');
                empty.className = 'ep-structure__empty';
                empty.textContent = 'Vide';
                items.append(empty);
            }
            section.append(title, items);
            list.append(section);
        }
        this.structureTarget.replaceChildren(list);
    }

    /* ---------- Settings panel ---------- */

    schedulePanel() {
        clearTimeout(this.panelTimer);
        this.panelTimer = setTimeout(() => this.renderPanel(), 400);
    }

    renderPanel() {
        const found = this.selectedId ? this.find(this.selectedId) : null;
        const type = found ? this.types.get(found.block.type) : null;
        if (!found || !type) {
            const tip = document.createElement('p');
            tip.className = 'ep-editor__tip';
            tip.textContent = 'Sélectionnez un bloc dans la page pour voir ses réglages.';
            this.blockPanelTarget.replaceChildren(tip);

            return;
        }
        const icon = this.libraryTarget.querySelector(`[data-type="${type.type}"] svg`)?.cloneNode(true) ?? null;
        renderBlockForm(this.blockPanelTarget, {
            type,
            data: found.block.data,
            zoneLabel: this.zonesValue[found.zone],
            icon,
            sources: { load: (widget, accept) => this.loadSource(widget, accept) },
            mediaInfo: (id) => this.mediaInfo(id),
            pickMedia: (accept, current) => this.mediaPicker.open({ accept, current }),
            onChange: (path, value) => {
                const current = this.find(found.block.id);
                if (!current) return;
                this.remember({ typing: typeof value === 'string' });
                setPath(current.block.data, path, value);
                this.changed();
            },
            onRemove: () => this.remove(found.block.id),
        });
    }

    loadSource(widget, accept) {
        const key = `${widget}:${accept ?? ''}`;
        if (!this.sourceCache.has(key)) {
            const url = { document: this.urlsValue.documents, document_category: this.urlsValue.document_categories }[widget];
            this.sourceCache.set(key, fetch(url, { headers: { Accept: 'application/json' } }).then((response) => (response.ok ? response.json() : [])).catch(() => []));
        }

        return this.sourceCache.get(key);
    }

    /* ---------- Media ---------- */

    /** A media of the library (cached), to show the current choice of a field. */
    mediaInfo(id) {
        if (!id) return Promise.resolve(null);
        if (!this.mediaCache.has(id)) {
            this.mediaCache.set(id, fetch(this.urlsValue.media_show.replace('__id__', String(id)), { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : null)).catch(() => null));
        }

        return this.mediaCache.get(id);
    }

    /**
     * Chooses the media of a block from the canvas (empty block, toolbar). For a list (files to
     * download, slides...), the media goes to the first item without one, or to a new item.
     */
    async pickBlockMedia(id, tab) {
        const found = this.find(id);
        const fields = found ? this.types.get(found.block.type)?.fields ?? [] : [];
        const field = fields.find((candidate) => candidate.widget === 'media');
        const list = field ? null : fields.find((candidate) => candidate.widget === 'items' && candidate.item.fields.some((itemField) => itemField.widget === 'media'));
        if (!field && !list) return;
        const itemField = list?.item.fields.find((candidate) => candidate.widget === 'media');
        const media = await this.mediaPicker.open({ accept: (field ?? itemField).accept, tab, current: field ? found.block.data[field.name] || null : null });
        const current = this.find(id);
        if (!media || !current) return;
        this.mediaCache.set(media.id, Promise.resolve(media));
        this.remember();
        if (field) {
            current.block.data[field.name] = media.id;
        } else {
            const items = current.block.data[list.name] ?? [];
            let item = items.find((candidate) => !candidate[itemField.name]);
            if (!item) {
                item = clone(list.item.defaults);
                items.push(item);
            }
            item[itemField.name] = media.id;
            // A file to download needs a title: start from the file name
            const title = list.item.fields.find((candidate) => candidate.name === 'title' || candidate.name === 'label');
            if (title && !item[title.name]) item[title.name] = media.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
            current.block.data[list.name] = items;
        }
        this.changed({ panel: true });
    }

    /** Cover of a post and other MediaPickerType fields of the settings. */
    async pickFormMedia(event) {
        const wrapper = event.currentTarget.closest('[data-ep-media-picker]');
        const input = wrapper.querySelector('input[type="hidden"]');
        const media = await this.mediaPicker.open({ accept: wrapper.dataset.accept, current: Number.parseInt(input.value, 10) || null });
        if (!media) return;
        this.setFormMedia(wrapper, media);
    }

    clearFormMedia(event) {
        this.setFormMedia(event.currentTarget.closest('[data-ep-media-picker]'), null);
    }

    setFormMedia(wrapper, media) {
        const input = wrapper.querySelector('input[type="hidden"]');
        input.value = media ? String(media.id) : '';
        const preview = wrapper.querySelector('[data-ep-media-preview]');
        const name = Object.assign(document.createElement('span'), { className: 'ep-media-field__name', textContent: media ? media.name : 'Aucun média' });
        preview.replaceChildren(...(media?.url ? [Object.assign(thumbnail(media), { className: 'ep-media-field__image' }), name] : [name]));
        wrapper.querySelector('[data-action="block-editor#clearFormMedia"]').hidden = !media;
        wrapper.querySelector('[data-action="block-editor#pickFormMedia"]').textContent = media ? 'Changer…' : 'Choisir…';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ---------- Content settings (form fields) ---------- */

    formChanged(event) {
        const name = event.target.name?.match(/\[([^\]]+)\]/)?.[1];
        if (!name || event.target.closest('dialog') || Object.hasOwn(this.zonesValue, name)) return;
        if (name === 'slug' && event.type === 'input') this.slugTouched = event.target.value !== '';
        this.metaChanged(name, event.target);
        if (event.target.dataset.epReload && event.type === 'change') {
            const url = new URL(this.urlsValue.canvas, window.location.href);
            url.searchParams.set('parent', event.target.value);
            this.frameTarget.src = url.toString();
        }
    }

    /** A content setting changed, from the panel or from the canvas header. */
    metaChanged(name, source = null) {
        if (name === 'title') {
            const title = this.field('title')?.value ?? '';
            this.titleTarget.textContent = title || (this.kindValue === 'post' ? 'Nouvel article' : 'Nouvelle page');
            if (!this.slugTouched && this.field('slug')) {
                this.field('slug').value = slugify(title);
            }
        }
        if (this.hasPathTarget && (name === 'title' || name === 'slug')) {
            this.pathTarget.textContent = `${this.urlsValue.path_prefix}${this.field('slug')?.value ?? ''}`;
        }
        this.markDirty();
        // The canvas header shows these values; typing in the canvas does not need a new render
        if (source !== null || !['title', 'kicker', 'excerpt'].includes(name)) {
            this.scheduleRender();
        }
    }

    /* ---------- Tabs and panels ---------- */

    switchTab(event) {
        this.showTab(event.currentTarget);
    }

    showTab(tab) {
        const list = tab.closest('[role="tablist"]');
        for (const other of list.querySelectorAll('[role="tab"]')) {
            const selected = other === tab;
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            const panel = document.getElementById(other.getAttribute('aria-controls'));
            if (panel) panel.hidden = !selected;
        }
    }

    /**
     * Wide screens: folds or unfolds a side panel. Narrow screens (tablets): opens or closes it over
     * the canvas.
     */
    togglePanel(event) {
        const panel = event.currentTarget.dataset.panel;
        if (this.isNarrow()) {
            const open = !this.workspaceTarget.classList.contains(`ep-editor__workspace--show-${panel}`);
            this.closePanels();
            this.workspaceTarget.classList.toggle(`ep-editor__workspace--show-${panel}`, open);
            for (const button of this.element.querySelectorAll(`.ep-editor__panel-toggles [data-panel="${panel}"]`)) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        } else {
            this.workspaceTarget.classList.toggle(`ep-editor__workspace--no-${panel}`);
        }
        setTimeout(() => this.canvas?.placeToolbar(), 50);
    }

    closePanels() {
        this.workspaceTarget.classList.remove('ep-editor__workspace--show-library', 'ep-editor__workspace--show-settings');
        for (const button of this.element.querySelectorAll('.ep-editor__panel-toggles [data-panel]')) {
            button.setAttribute('aria-expanded', 'false');
        }
    }

    isNarrow() {
        return window.matchMedia('(width < 75em)').matches;
    }

    /* ---------- Saving, preview, publication ---------- */

    beforeSubmit(event) {
        clearTimeout(this.renderTimer);
        this.changed({ structure: false });
        clearTimeout(this.renderTimer);
        if (event.submitter?.getAttribute('formtarget') === '_blank') return;
        this.submitting = true;
        this.statusTarget.textContent = 'Enregistrement…';
    }

    preview() {
        // The preview opens in a new tab: this page stays as it is
        this.submitting = false;
    }

    confirmLeave(event) {
        if (this.dirty && !this.submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    openPublish() {
        if (!this.hasPublishDialogTarget) return;
        const visibility = this.field('visibility');
        const groups = [...this.formTarget.querySelectorAll(`[name="${this.formNameValue}[allowedGroups][]"]:checked`)].map((input) => input.closest('.form-check')?.textContent.trim()).filter(Boolean);
        const label = visibility?.selectedOptions[0]?.textContent.trim() ?? '';
        this.publishVisibilityTarget.textContent = `Visibilité : ${label}${visibility?.value === 'groups' ? ` (${groups.join(', ') || 'aucun groupe choisi'})` : ''}.`;
        this.publishTitleTarget.textContent = this.field('title')?.value || this.titleTarget.textContent;
        this.publishWhen();
        this.publishDialogTarget.showModal();
    }

    closePublish() {
        this.publishDialogTarget.close();
    }

    publishWhen() {
        const later = this.publishDialogTarget.querySelector('input[name="ep_publish_when"]:checked')?.value === 'later';
        this.publishDateTarget.hidden = !later;
        this.publishLabelTarget.textContent = later ? 'Confirmer la programmation' : 'Publier maintenant';
    }

    confirmPublish() {
        const input = this.field('publishedAt');
        const later = this.publishDialogTarget.querySelector('input[name="ep_publish_when"]:checked')?.value === 'later';
        if (!later) {
            input.value = parisNow();
        } else if (input.value === '') {
            input.focus();
            input.setCustomValidity('Choisissez la date et l’heure de publication.');
            input.reportValidity();
            input.addEventListener('input', () => input.setCustomValidity(''), { once: true });

            return;
        }
        this.publishDialogTarget.close();
        this.formTarget.requestSubmit(this.saveTarget);
    }

    unpublish() {
        this.field('publishedAt').value = '';
        this.publishDialogTarget.close();
        this.formTarget.requestSubmit(this.saveTarget);
    }

    /* ---------- Keyboard ---------- */

    shortcut(event) {
        const key = event.key.toLowerCase();
        const modifier = event.ctrlKey || event.metaKey;
        const target = event.composedPath?.()[0] ?? event.target;
        const typing = target instanceof Element && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

        if (modifier && key === 's') {
            event.preventDefault();
            this.formTarget.requestSubmit(this.saveTarget);
        } else if (modifier && !typing && ((key === 'z' && event.shiftKey) || key === 'y')) {
            event.preventDefault();
            this.redo();
        } else if (modifier && !typing && key === 'z') {
            event.preventDefault();
            this.undo();
        } else if (event.altKey && event.shiftKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown') && this.selectedId) {
            event.preventDefault();
            this.command(event.key === 'ArrowUp' ? 'up' : 'down', this.selectedId);
        } else if (event.key === 'Escape' && this.workspaceTarget.matches('[class*="--show-"]')) {
            this.closePanels();
        } else if (event.key === 'Escape' && this.selectedId && !(this.hasPublishDialogTarget && this.publishDialogTarget.open)) {
            this.canvas?.closeMenu();
            this.frameTarget.contentDocument?.activeElement?.blur();
            this.select(null);
        }
    }
}

/** Current date and time in metropolitan France, as a datetime-local value ("2026-10-12T20:30"). */
function parisNow() {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date()).map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}
