/*
 * "Choisir un média" window of the block editor (Figma "Article programmé · Fenêtre Médiathèque" and
 * "Article · Fenêtre Téléverser"): the media library with search and type filter, the details of the
 * selected file (alt text and credit, saved as soon as they change), and the upload tab.
 *
 *     const media = await picker.open({ accept: 'image', tab: 'library' }); // null when cancelled
 *
 * The markup is in templates/admin/editor/_media_dialog.html.twig; the data comes from
 * EditorDataController (/admin/editor/media...).
 */
const TYPE_LABELS = { image: 'Images', pdf: 'PDF', other: 'Autres fichiers' };

export class MediaPicker {
    /**
     * @param {HTMLDialogElement} dialog
     * @param {{media: string, upload: string, update: string}} urls update contains "__id__"
     */
    constructor(dialog, urls) {
        this.dialog = dialog;
        this.urls = urls;
        this.token = dialog.dataset.token;
        this.canEdit = dialog.dataset.canEdit === 'true';
        this.part = (name) => dialog.querySelector(`[data-media="${name}"]`);
        this.items = [];
        this.selected = null;
        this.resolve = null;
        this.searchTimer = null;

        this.part('search').addEventListener('input', () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.load(), 250);
        });
        this.part('type').addEventListener('change', () => this.load());
        this.part('more').addEventListener('click', () => this.load({ append: true }));
        this.part('use').addEventListener('click', () => this.close(this.selected));
        for (const button of dialog.querySelectorAll('[data-media-close]')) {
            button.addEventListener('click', () => this.close(null));
        }
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.close(null);
        });
        for (const tab of dialog.querySelectorAll('[data-media-tab]')) {
            tab.addEventListener('click', () => this.showTab(tab.dataset.mediaTab));
        }
        for (const name of ['alt', 'credit']) {
            this.part(name)?.addEventListener('change', () => this.saveDetails());
        }

        // Upload: file input and drop zone
        const input = this.part('files');
        if (input) {
            this.part('choose').addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                this.upload([...input.files]);
                input.value = '';
            });
            const zone = this.part('dropzone');
            zone.addEventListener('dragover', (event) => {
                if (!event.dataTransfer.types.includes('Files')) return;
                event.preventDefault();
                zone.classList.add('is-over');
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
            zone.addEventListener('drop', (event) => {
                event.preventDefault();
                zone.classList.remove('is-over');
                this.upload([...event.dataTransfer.files]);
            });
        }
    }

    /**
     * @param {{accept?: string|null, tab?: string, current?: number|null}} options accept: image, pdf or file
     * @returns {Promise<object|null>} the chosen media, or null
     */
    open({ accept = null, tab = 'library', current = null } = {}) {
        this.accept = accept === 'file' ? null : accept;
        this.current = current;
        this.selected = null;
        const type = this.part('type');
        type.value = this.accept ?? '';
        type.disabled = this.accept !== null;
        this.part('search').value = '';
        this.part('formats').textContent = this.accept === 'image' ? 'JPG, PNG, WebP, GIF, SVG · 20 Mo maximum par fichier' : 'Images, PDF, texte ou ZIP · 20 Mo maximum par fichier';
        this.part('upload-messages').replaceChildren();
        this.showDetails(null);
        this.showTab(this.part('files') ? tab : 'library');
        this.dialog.showModal();
        this.load();

        return new Promise((resolve) => {
            this.resolve = resolve;
        });
    }

    close(media) {
        if (this.dialog.open) this.dialog.close();
        this.resolve?.(media);
        this.resolve = null;
    }

    showTab(name) {
        for (const tab of this.dialog.querySelectorAll('[data-media-tab]')) {
            const selected = tab.dataset.mediaTab === name;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            this.dialog.querySelector(`#${tab.getAttribute('aria-controls')}`).hidden = !selected;
        }
        (name === 'upload' ? this.part('choose') : this.part('search')).focus();
    }

    async load({ append = false } = {}) {
        const url = new URL(this.urls.media, window.location.href);
        url.searchParams.set('q', this.part('search').value);
        url.searchParams.set('type', this.part('type').value);
        url.searchParams.set('offset', append ? String(this.items.length) : '0');
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const page = await response.json();
        this.items = append ? [...this.items, ...page.items] : page.items;
        this.part('count').textContent = `${page.total} média${page.total > 1 ? 's' : ''} · Tri : plus récents`;
        this.part('more').hidden = !page.more;
        this.renderGrid();
        if (!append && this.current) {
            const current = this.items.find((item) => item.id === this.current);
            if (current) this.select(current);
        }
    }

    renderGrid() {
        const grid = this.part('grid');
        if (this.items.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'ep-media-grid__empty';
            empty.textContent = 'Aucun média ne correspond. Téléversez un fichier ou changez la recherche.';
            grid.replaceChildren(empty);

            return;
        }
        grid.replaceChildren(...this.items.map((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ep-media-card';
            button.setAttribute('aria-pressed', this.selected?.id === item.id ? 'true' : 'false');
            button.dataset.id = String(item.id);
            button.append(thumbnail(item), Object.assign(document.createElement('span'), { className: 'ep-media-card__name', textContent: item.name }));
            if (this.selected?.id === item.id) {
                button.append(Object.assign(document.createElement('span'), { className: 'ep-media-card__selected', textContent: '✓ Sélectionné' }));
            }
            button.addEventListener('click', () => this.select(item));
            button.addEventListener('dblclick', () => this.close(item));
            const li = document.createElement('li');
            li.append(button);

            return li;
        }));
    }

    select(item) {
        this.selected = item;
        this.renderGrid();
        this.showDetails(item);
    }

    showDetails(item) {
        this.part('details').hidden = item === null;
        this.part('no-details').hidden = item !== null;
        this.part('selection').textContent = item ? '1 média sélectionné' : 'Aucun média sélectionné';
        this.part('use').disabled = item === null;
        this.part('saved').textContent = '';
        if (!item) return;
        this.part('name').textContent = item.name;
        const facts = [`${item.format} · ${item.size}`];
        if (item.width) facts.push(`${item.width} × ${item.height} px`);
        if (item.pages) facts.push(`${item.pages} page${item.pages > 1 ? 's' : ''}`);
        facts.push(`Ajouté le ${item.uploadedAt}`, `Visible par : ${item.visibility}`);
        this.part('facts').replaceChildren(...facts.map((fact) => Object.assign(document.createElement('li'), { textContent: fact })));
        for (const name of ['alt', 'credit']) {
            const input = this.part(name);
            input.value = item[name] ?? '';
            input.disabled = !this.canEdit;
        }
        this.part('alt-row').hidden = item.type !== 'image';
    }

    async saveDetails() {
        const item = this.selected;
        if (!item || !this.canEdit) return;
        const body = new FormData();
        body.set('alt', this.part('alt').value);
        body.set('credit', this.part('credit').value);
        const response = await fetch(this.urls.update.replace('__id__', String(item.id)), { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-Token': this.token } });
        if (!response.ok) {
            this.part('saved').textContent = 'Non enregistré : rechargez la page.';

            return;
        }
        Object.assign(item, await response.json());
        this.part('saved').textContent = 'Enregistré dans la médiathèque.';
    }

    async upload(files) {
        if (files.length === 0) return;
        const messages = this.part('upload-messages');
        messages.replaceChildren(Object.assign(document.createElement('li'), { textContent: `Envoi de ${files.length} fichier${files.length > 1 ? 's' : ''}…` }));
        const body = new FormData();
        for (const file of files) body.append('files[]', file);
        let result;
        try {
            const response = await fetch(this.urls.upload, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-Token': this.token } });
            result = await response.json();
        } catch {
            result = { items: [], errors: ['L’envoi a échoué : vérifiez la connexion.'] };
        }
        messages.replaceChildren(...(result.errors ?? [result.error]).filter(Boolean).map((error) => Object.assign(document.createElement('li'), { className: 'text-danger', textContent: error })));
        const usable = (result.items ?? []).filter((item) => this.accept === null || item.type === this.accept);
        if ((result.items ?? []).length > usable.length) {
            messages.append(Object.assign(document.createElement('li'), { textContent: `Ajoutés à la médiathèque, mais ce champ attend : ${TYPE_LABELS[this.accept] ?? this.accept}.` }));
        }
        if (usable.length > 0) {
            this.part('search').value = '';
            this.showTab('library');
            await this.load();
            this.select(this.items.find((item) => item.id === usable[0].id) ?? usable[0]);
        }
    }
}

/** Image thumbnail, or a tile with the format ("PDF") for other files. */
export function thumbnail(item) {
    if (item?.url) {
        return Object.assign(document.createElement('img'), { className: 'ep-media-thumb', src: item.url, alt: '', loading: 'lazy' });
    }

    return Object.assign(document.createElement('span'), { className: 'ep-media-thumb ep-media-thumb--file', textContent: item?.format ?? '?' });
}
