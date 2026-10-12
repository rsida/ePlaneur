import { Controller } from '@hotwired/stimulus';

/*
 * Flight plan workshop (/plans-de-vol/atelier): the settings of a Condor 3 flight plan turned, at each
 * change, into instructions to give Claude, who writes the .fpl file. The airfields, regions and
 * airspaces of a landscape come from the data URL; the settings are kept in the browser.
 */

const STORE = 'eplaneur-flight-plan-workshop';

const LEVELS = {
    discovery: {
        name: 'Découverte',
        hint: 'Circuit court et proche des terrains, thermiques forts et larges, aides visibles.',
        set: { length: 50, turnpoints: 2, shape: 'triangle', tpSource: 'airfields', startType: 'window', startWidth: 3000, startHeight: 1500, finishType: 'window', finishWidth: 3000, finishHeight: 1000, tpType: 'cylinder', tpRadius: 1000, startSpeed: '', thermalHelpers: 5, tpHelpers: 10, window: 30, w_base: 2000, w_strength: 3, w_activity: 2, w_width: 2, w_windSpeed: 2, w_turbulence: 0 },
    },
    intermediate: {
        name: 'Intermédiaire',
        hint: 'Circuit moyen, météo correcte mais sans aide thermique, quelques choix de route.',
        set: { length: 100, turnpoints: 3, shape: 'auto', tpSource: 'any', startType: 'window', startWidth: 2000, startHeight: 1000, finishType: 'window', finishWidth: 2000, finishHeight: 600, tpType: 'cylinder', tpRadius: 500, startSpeed: '', thermalHelpers: 0, tpHelpers: 5, window: 15, w_base: 2000, w_strength: 2, w_activity: 2, w_width: 1, w_windSpeed: 3, w_turbulence: 1 },
    },
    advanced: {
        name: 'Confirmé',
        hint: 'Long circuit, météo variable, zones plus faibles à gérer, pas d’aide thermique.',
        set: { length: 200, turnpoints: 4, shape: 'polygon', tpSource: 'any', startType: 'window', startWidth: 1500, startHeight: 800, finishType: 'window', finishWidth: 1500, finishHeight: 500, tpType: 'cylinder', tpRadius: 500, startSpeed: 170, thermalHelpers: 0, tpHelpers: 3, window: 15, w_base: 2200, w_strength: 2, w_activity: 1, w_width: 1, w_windSpeed: 5, w_turbulence: 1 },
    },
    competition: {
        name: 'Compétition',
        hint: 'Épreuve de championnat : départ contrôlé, fenêtres étroites, météo exigeante.',
        set: { length: 300, turnpoints: 5, shape: 'polygon', tpSource: 'any', startType: 'window', startWidth: 1000, startHeight: 600, finishType: 'window', finishWidth: 1000, finishHeight: 400, tpType: 'sector', tpRadius: 500, startSpeed: 170, thermalHelpers: 0, tpHelpers: 3, window: 0, w_base: 2500, w_strength: 2, w_activity: 2, w_width: 1, w_windSpeed: 6, w_turbulence: 2 },
    },
};

const STRENGTH = ['très faibles', 'faibles', 'moyens', 'forts', 'très forts'];
const ACTIVITY = ['faible', 'moyen', 'élevé', 'très élevé'];
const WIDTH = ['étroite', 'moyenne', 'large'];
const TURBULENCE = ['nulle', 'faible', 'moyenne', 'forte'];
const HIGH_CLOUDS = ['nul', 'léger', 'moyen', 'épais'];
const CLOUDS = { cu: 'cumulus', blue: 'thermiques bleus, sans cumulus', over: 'cumulus avec surdéveloppements possibles' };

const DEFAULTS = { landscape: 'AA3', region: 'Alpes du Sud', departure: 'Barcelonnette', gliderClass: '15 mètres', glider: 'Diana2' };

export default class extends Controller {
    static targets = ['form', 'prompt', 'status', 'baseZone', 'zones', 'pzs', 'zoneTemplate', 'pzTemplate', 'airspaces'];
    static values = { landscapes: Array, gliders: Object, dataUrl: String };

    async connect() {
        this.landscapeData = { regions: [], airfields: [], airspaces: [] };
        this.picked = new Set();
        this.sequence = 0;
        this.fillClasses();
        const saved = this.load();
        if (saved) {
            await this.restore(saved);
        } else {
            await this.defaults();
        }
        this.renumber();
        this.update();
    }

    // ---- Landscape data

    async loadLandscape(name) {
        const response = await fetch(this.dataUrlValue.replace('__L__', encodeURIComponent(name)), { headers: { Accept: 'application/json' } });
        this.landscapeData = response.ok ? await response.json() : { regions: [], airfields: [], airspaces: [] };
    }

    fillRegions(keep) {
        const select = this.field('region');
        select.replaceChildren(new Option('Toute la scène', ''), ...this.landscapeData.regions.map((region) => new Option(region, region)));
        select.value = this.landscapeData.regions.includes(keep) ? keep : '';
    }

    fillAirfields(keepDeparture, keepArrival) {
        const region = this.field('region').value;
        const list = this.landscapeData.airfields.filter((airfield) => !region || airfield[3] === region);
        const options = () => list.map(([name, altitude, length]) => new Option(`${name} · ${altitude} m${length ? ` · piste ${length} m` : ''}`, name));
        this.field('departure').replaceChildren(...options());
        this.field('arrival').replaceChildren(new Option('Même aérodrome que le départ', ''), ...options());
        if (list.some(([name]) => name === keepDeparture)) this.field('departure').value = keepDeparture;
        if (list.some(([name]) => name === keepArrival)) this.field('arrival').value = keepArrival;
    }

    renderAirspaces() {
        const region = this.field('region').value;
        const query = this.field('airspaceSearch').value.trim().toLowerCase();
        const all = this.landscapeData.airspaces.filter((airspace) => !region || airspace[4] === region);
        const shown = all.filter(([name]) => !query || name.toLowerCase().includes(query)).slice(0, 300);
        this.airspacesTarget.replaceChildren(...shown.map(([name, category, floor, ceiling]) => {
            const key = `${name}|${floor}|${ceiling}`;
            const label = document.createElement('label');
            const box = Object.assign(document.createElement('input'), { type: 'checkbox', checked: this.picked.has(key) });
            box.dataset.space = key;
            const text = document.createElement('span');
            text.textContent = `${name} (${category})`;
            const limits = document.createElement('small');
            limits.textContent = `${floor} → ${ceiling}`;
            label.append(box, text, limits);
            return label;
        }));
        this.out('airspaceCount').textContent = `${all.length} espaces aériens dans ${region || 'la scène'}${shown.length < all.length ? `, ${shown.length} affichés` : ''} · ${this.picked.size} à désactiver.`;
    }

    pickAirspace(event) {
        const key = event.target.dataset.space;
        if (!key) return;
        event.target.checked ? this.picked.add(key) : this.picked.delete(key);
        this.renderAirspaces();
        this.update();
    }

    async changeLandscape() {
        this.picked.clear();
        await this.loadLandscape(this.field('landscape').value);
        this.fillRegions();
        this.fillAirfields();
        this.renderAirspaces();
        this.update();
    }

    changeRegion() {
        this.fillAirfields(this.field('departure').value, this.field('arrival').value);
        this.renderAirspaces();
        this.update();
    }

    // ---- Gliders

    fillClasses(keep) {
        const select = this.field('gliderClass');
        select.replaceChildren(...Object.keys(this.glidersValue).map((name) => new Option(name, name)));
        if (keep && this.glidersValue[keep]) select.value = keep;
    }

    fillModels(keep) {
        const list = this.glidersValue[this.field('gliderClass').value] ?? [];
        this.field('glider').replaceChildren(new Option('Tous les planeurs de la classe', ''), ...list.map(([name]) => new Option(name, name)));
        if (list.some(([name]) => name === keep)) this.field('glider').value = keep;
    }

    changeClass() {
        this.fillModels();
        this.update();
    }

    // ---- Zones (weather and penalty)

    addZone() {
        this.addCard('zone');
        this.update();
    }

    addPz() {
        this.addCard('pz');
        this.update();
    }

    addCard(kind, values = {}) {
        const template = 'zone' === kind ? this.zoneTemplateTarget : this.pzTemplateTarget;
        const prefix = `${kind}${++this.sequence}_`;
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__P__', prefix);
        const card = holder.firstElementChild;
        card.querySelectorAll('[data-k]').forEach((element) => { element.id ||= prefix + element.dataset.k; });
        card.querySelectorAll('[data-l]').forEach((label) => { label.htmlFor = prefix + label.dataset.l; });
        Object.entries(values).forEach(([key, value]) => {
            const element = card.querySelector(`[data-k="${key}"]`);
            if (element) element.value = value;
        });
        card.querySelector('[data-remove]').addEventListener('click', () => {
            card.remove();
            this.renumber();
            this.update();
        });
        ('zone' === kind ? this.zonesTarget : this.pzsTarget).append(card);
        this.renumber();
    }

    renumber() {
        this.zonesTarget.querySelectorAll('[data-zone]').forEach((card, i) => { card.querySelector('[data-title]').textContent = `Zone ${i + 1}`; });
        this.pzsTarget.querySelectorAll('[data-pz]').forEach((card, i) => { card.querySelector('[data-title]').textContent = `Zone de pénalité ${i + 1}`; });
        this.out('zonesEmpty').hidden = null !== this.zonesTarget.querySelector('[data-zone]');
        this.out('pzsEmpty').hidden = null !== this.pzsTarget.querySelector('[data-pz]');
    }

    // ---- Settings

    field(name) {
        return this.formTarget.querySelector(`#${name}`);
    }

    out(name) {
        return this.element.querySelector(`[data-out="${name}"]`);
    }

    values() {
        const values = {};
        this.formTarget.querySelectorAll('[name]').forEach((element) => {
            if ('radio' === element.type) {
                if (element.checked) values[element.name] = element.value;
            } else if ('checkbox' === element.type) {
                values[element.name] = element.checked;
            } else {
                values[element.name] = element.value;
            }
        });
        const keyed = (container) => Object.fromEntries([...container.querySelectorAll('[data-k]')].map((element) => [element.dataset.k, element.value]));
        values.base = keyed(this.baseZoneTarget);
        values.zones = [...this.zonesTarget.querySelectorAll('[data-zone]')].map(keyed);
        values.pzs = [...this.pzsTarget.querySelectorAll('[data-pz]')].map(keyed);
        values.picked = [...this.picked];
        return values;
    }

    set(name, value) {
        this.formTarget.querySelectorAll(`[name="${name}"]`).forEach((element) => {
            if ('radio' === element.type) element.checked = element.value === String(value);
            else if ('checkbox' === element.type) element.checked = Boolean(value);
            else element.value = value ?? '';
        });
    }

    applyLevel() {
        Object.entries(LEVELS[this.values().level].set).forEach(([key, value]) => {
            if (key.startsWith('w_')) {
                this.baseZoneTarget.querySelector(`#${key}`).value = value;
            } else {
                this.set(key, value);
            }
        });
        this.update();
    }

    async defaults() {
        const landscape = this.landscapesValue.includes(DEFAULTS.landscape) ? DEFAULTS.landscape : (this.landscapesValue[0] ?? '');
        this.field('landscape').value = landscape;
        if (landscape) await this.loadLandscape(landscape);
        this.fillRegions(DEFAULTS.region);
        this.fillAirfields(DEFAULTS.departure);
        this.fillClasses(DEFAULTS.gliderClass);
        this.fillModels(DEFAULTS.glider);
        if (this.landscapeData.airspaces.some(([name]) => name.startsWith('R196A1 Est GAP'))) {
            const [name, , floor, ceiling] = this.landscapeData.airspaces.find(([n]) => n.startsWith('R196A1 Est GAP'));
            this.picked.add(`${name}|${floor}|${ceiling}`);
        }
        this.renderAirspaces();
    }

    async restore(saved) {
        const landscape = this.landscapesValue.includes(saved.landscape) ? saved.landscape : (this.landscapesValue[0] ?? '');
        this.field('landscape').value = landscape;
        if (landscape) await this.loadLandscape(landscape);
        this.fillRegions(saved.region);
        this.fillAirfields(saved.departure, saved.arrival);
        this.fillClasses(saved.gliderClass);
        this.fillModels(saved.glider);
        const own = ['landscape', 'region', 'departure', 'arrival', 'gliderClass', 'glider', 'base', 'zones', 'pzs', 'picked'];
        Object.entries(saved).forEach(([key, value]) => { if (!own.includes(key)) this.set(key, value); });
        Object.entries(saved.base ?? {}).forEach(([key, value]) => {
            const element = this.baseZoneTarget.querySelector(`[data-k="${key}"]`);
            if (element) element.value = value;
        });
        (saved.zones ?? []).forEach((zone) => this.addCard('zone', zone));
        (saved.pzs ?? []).forEach((zone) => this.addCard('pz', zone));
        (saved.picked ?? []).forEach((key) => this.picked.add(key));
        this.renderAirspaces();
    }

    load() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORE) ?? 'null');
            return saved && saved.landscape ? saved : null;
        } catch {
            return null;
        }
    }

    async reset() {
        try { localStorage.removeItem(STORE); } catch { /* storage unavailable */ }
        this.formTarget.reset();
        this.zonesTarget.replaceChildren();
        this.pzsTarget.replaceChildren();
        this.picked.clear();
        await this.defaults();
        this.renumber();
        this.update();
        this.statusTarget.textContent = 'Valeurs de départ rétablies.';
    }

    ignore(event) {
        event.preventDefault();
    }

    // ---- Display

    visibility(values) {
        this.element.querySelectorAll('[data-show]').forEach((element) => {
            const [name, accepted] = element.dataset.show.split(':');
            element.hidden = !accepted.split(',').includes(values[name]);
        });
        this.out('windowNote').hidden = ![values.startType, values.finishType, values.tpType].includes('window');
        this.out('launchHeightLabel').textContent = 'airborne' === values.launch ? 'Hauteur du départ en vol' : 'Hauteur de largage';
        this.out('levelHint').textContent = LEVELS[values.level].hint;
        this.out('length').textContent = `${values.length} km`;
        this.out('turnpoints').textContent = values.turnpoints;
        const glider = (this.glidersValue[values.gliderClass] ?? []).find(([name]) => name === values.glider);
        this.out('gliderHint').textContent = glider
            ? `${glider[1]} m d’envergure, ${glider[2] ? `jusqu’à ${glider[2]} kg d’eau` : 'pas de ballast d’eau'}.`
            : 'Le pilote choisit son planeur dans la classe.';
    }

    update() {
        const values = this.values();
        this.visibility(values);
        this.promptTarget.textContent = this.prompt(values);
        try { localStorage.setItem(STORE, JSON.stringify(values)); } catch { /* storage unavailable */ }
    }

    async copy() {
        try {
            await navigator.clipboard.writeText(this.promptTarget.textContent);
            this.statusTarget.textContent = 'Consigne copiée : collez-la dans la conversation avec Claude.';
        } catch {
            const range = document.createRange();
            range.selectNodeContents(this.promptTarget);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            this.statusTarget.textContent = 'Copie refusée par le navigateur : le texte est sélectionné, faites Ctrl+C.';
        }
    }

    // ---- Instructions

    gate(values, gate) {
        const type = values[`${gate}Type`];
        const limit = 'start' === gate
            ? (values.startMax ? `, altitude max. ${values.startMax} m` : '')
            : (values.finishMin ? `, altitude min. ${values.finishMin} m` : '');
        if ('window' === type) {
            const centre = values[`${gate}Center`]
                ? `centrée à ${values[`${gate}Center`]} m d’altitude`
                : ('start' === gate ? 'centre choisi d’après le relief' : 'centre environ 300 m au-dessus du terrain d’arrivée');
            return `fenêtre de ${values[`${gate}Width`]} m de large et ${values[`${gate}Height`]} m de haut, ${centre}, à traverser dans le sens du circuit`;
        }
        if ('line' === type) {
            return `ligne de ${values[`${gate}Width`]} m perpendiculaire au circuit${limit}`;
        }
        return `cylindre de ${values[`${gate}Radius`]} m de rayon${limit}`;
    }

    weather(zone, inherit) {
        const lines = [];
        const has = (key) => !inherit || ('' !== zone[key] && undefined !== zone[key]);
        if (has('base')) lines.push(`plafond des cumulus ${zone.base} m au-dessus du sol`);
        if (has('clouds')) lines.push(CLOUDS[zone.clouds]);
        if (has('strength')) lines.push(`thermiques ${STRENGTH[+zone.strength]}`);
        if (has('activity')) lines.push(`nombre de thermiques ${ACTIVITY[+zone.activity]}`);
        if (has('width')) lines.push(`largeur des thermiques ${WIDTH[+zone.width]}`);
        if (zone.inversion) lines.push(`plafond des thermiques (inversion) ${zone.inversion} m d’altitude`);
        else if (!inherit) lines.push('inversion au choix de Claude, au-dessus du plafond des cumulus');
        if (!inherit || zone.windDir || zone.windSpeed) lines.push(`vent du ${zone.windDir || '(comme la base)'}° à ${zone.windSpeed || '(comme la base)'} m/s`);
        if (has('turbulence')) lines.push(`turbulence ${TURBULENCE[+zone.turbulence]}`);
        if (has('streets')) lines.push(+zone.streets ? 'rues de nuages dans l’axe du vent' : 'pas de rues de nuages');
        if (!inherit) lines.push(`voile d’altitude ${HIGH_CLOUDS[+zone.highClouds]}`, +zone.waves ? 'ondes possibles' : 'pas d’ondes');
        return lines.filter(Boolean);
    }

    prompt(v) {
        const airfield = (name) => this.landscapeData.airfields.find(([n]) => n === name);
        const describe = (a) => (a ? `${a[0]} (${a[1]} m${a[2] ? `, piste ${a[2]} m` : ''})` : '?');
        const yes = (value) => (value ? 'oui' : 'non');
        const shape = { auto: 'au choix de Claude', 'out-return': 'aller-retour', triangle: 'triangle', polygon: 'polygone', line: 'ligne (arrivée sur un autre terrain)' }[v.shape];
        const level = LEVELS[v.level];
        const L = [];

        L.push('Génère un plan de vol Condor 3 (.fpl) avec les réglages ci-dessous : fichier Windows-1252 à fins de ligne CRLF, altitudes du sol tirées du relief de la scène.');
        L.push(`Nom du fichier : ${v.fileName ? `${v.fileName}.fpl` : 'à choisir'}. Résume ensuite les points retenus (nom, longueur des branches), la météo et ce qu’il faut vérifier dans le planificateur de Condor.`);
        L.push('');
        L.push('## Scène et circuit');
        L.push(`- Scène : ${v.landscape}${v.region ? `, région ${v.region}` : ''}`);
        L.push(`- Départ : ${describe(airfield(v.departure))}`);
        L.push(`- Arrivée : ${v.arrival ? describe(airfield(v.arrival)) : 'même aérodrome que le départ'}`);
        L.push(`- Longueur visée : environ ${v.length} km (±15 %), forme ${shape}`);
        L.push(`- Points de virage : ${v.turnpoints}, ${{ any: 'des repères visibles (sommets, villages, lacs, aérodromes)', airfields: 'uniquement des aérodromes', imposed: 'imposés' }[v.tpSource]}`);
        if ('imposed' === v.tpSource && v.imposed.trim()) L.push(`  Dans l’ordre : ${v.imposed.trim().split(/\n+/).join(' → ')}`);
        L.push('- Chaque branche reste à portée de plané d’un terrain posable avec la météo prévue.');
        L.push(`- Épreuve : ${'aat' === v.taskType ? `AAT, durée minimale ${v.aatTime} min, zones de ${v.aatRadius} km de rayon` : 'circuit imposé (course)'}`);
        L.push('');
        L.push('## Niveau');
        L.push(`- ${level.name} : ${level.hint.charAt(0).toLowerCase()}${level.hint.slice(1)}`);
        if (v.step) L.push(`- Étape ${this.field('step').selectedOptions[0].textContent.replace(/^\d+ · /, `${v.step} du parcours ePlaneur : `)}`);
        if (v.skill.trim()) L.push(`- Compétence travaillée : ${v.skill.trim()}`);
        L.push('');
        L.push('## Portes et points');
        L.push(`- Départ : ${this.gate(v, 'start')}, à environ ${v.startDistance} km de l’aérodrome de départ${v.startSpeed ? `, vitesse sol max. ${v.startSpeed} km/h au franchissement` : ', sans limite de vitesse'}`);
        L.push(`- Points de virage : ${'window' === v.tpType ? `fenêtre de ${v.tpWidth} × ${v.tpHeight} m` : 'sector' === v.tpType ? `secteur FAI 90° de ${v.tpRadius} m` : `cylindre de ${v.tpRadius} m de rayon`}`);
        L.push(`- Arrivée : ${this.gate(v, 'finish')}`);
        if ([v.startType, v.finishType, v.tpType].includes('window')) {
            L.push('- Le codage d’une fenêtre dans le .fpl (TPSectorType, TPWidth, TPHeight, TPAltitude, TPAzimuth) n’a pas encore été vérifié sur un fichier enregistré par Condor : en cas de doute, demande un .fpl avec une fenêtre enregistré par le planificateur.');
        }
        L.push('');
        L.push('## Lancement et horaire');
        if ('aerotow' === v.launch) L.push(`- Remorqué, largage à ${v.launchHeight} m sol, câble de ${v.ropeLength} m, rupture ${v.breakProb} %`);
        if ('winch' === v.launch) L.push(`- Treuil, câble de ${v.ropeLength} m, rupture ${v.breakProb} % ; vérifie que le terrain peut avoir un treuil et signale-le`);
        if ('airborne' === v.launch) L.push(`- En vol, ${v.launchHeight} m au-dessus du sol, ${{ beforeStart: 'point de départ hors aérodrome, quelques km avant la porte de départ', overAirfield: 'à la verticale de l’aérodrome de départ', free: 'à l’endroit décrit dans les autres consignes' }[v.airstartAt]}`);
        L.push(`- Date ${v.date.split('-').reverse().join('/')}, départ ${v.startTime}, fenêtre de départ ${+v.window ? `${v.window} min` : '0 (régate)'}${+v.raceDelay ? `, délai ${v.raceDelay} min` : ''}`);
        L.push('');
        L.push('## Planeur');
        L.push(`- Classe ${v.gliderClass}${v.glider ? `, planeur imposé : ${v.glider}` : ', le pilote choisit son planeur dans la classe'}`);
        L.push(`- Eau : ${{ none: 'interdite (0 kg)', limit: `${v.waterLimit} kg au plus`, free: 'libre, jusqu’au maximum du planeur' }[v.waterMode]}${v.wingLoading ? `, charge alaire max. ${v.wingLoading} kg/m²` : ''}`);
        L.push(`- Essuie-moucherons : ${v.bugwipers ? 'autorisés' : 'non'}`);
        L.push('');
        L.push('## Météo');
        L.push(`- ${'fixed' === v.weatherMode ? 'Fixe, pas d’aléatoire à chaque vol' : 'Aléatoire à chaque vol'}${v.seed ? `, graine ${v.seed}` : ''}`);
        L.push(`- Zone de base : ${this.weather(v.base, false).join(', ')}`);
        L.push('- Le plafond des cumulus est compté au-dessus du sol : déduis-en la température et le point de rosée.');
        v.zones.forEach((zone, i) => {
            L.push(`- Zone ${i + 1}${zone.name ? ` « ${zone.name} »` : ''} : ${zone.where || 'emplacement au choix de Claude'}, rayon ${zone.radius} km, transition ${zone.border} km ; ${this.weather(zone, true).join(', ') || 'mêmes réglages que la base'}`);
        });
        L.push('');
        L.push('## Zones réglementaires');
        const airspaces = {
            avoid: 'désactive (DisabledAirspaces) ceux qui couvrent le circuit et liste-les',
            keep: 'tous actifs, le circuit les contourne (plancher et plafond compris)',
            pick: v.picked.length ? `désactive ceux-ci : ${v.picked.map((key) => key.split('|')[0]).join(' ; ')}` : 'aucun choisi, tous actifs',
        };
        L.push(`- Espaces aériens de la scène : ${airspaces[v.airspaceMode]}`);
        L.push(`- Pénalités : entrée en espace aérien ${v.airspacePenalty} pts, en zone de pénalité ${v.pzPenalty} pts`);
        v.pzs.forEach((zone, i) => {
            const form = { circle: `cercle de ${zone.size} km de rayon`, corridor: `couloir de ${zone.size} km de large`, polygon: `polygone d’environ ${zone.size} km` }[zone.shape];
            L.push(`- Zone de pénalité ${i + 1}${zone.name ? ` « ${zone.name} »` : ''} : ${zone.where || 'emplacement au choix de Claude'}, ${form}, de ${zone.floor ? `${zone.floor} m` : 'sol'} à ${zone.ceiling ? `${zone.ceiling} m` : 'illimité'}`);
        });
        L.push('');
        L.push('## Aides et pénalités');
        L.push(`- Aides thermiques ${+v.thermalHelpers ? `${v.thermalHelpers} km` : 'désactivées'}, aides des points de virage ${v.tpHelpers} km, icônes ${v.iconsRange} km`);
        L.push(`- Vol en nuage ${v.cloudPenalty} pts`);
        L.push(`- PDA ${yes(v.allowPda)}, vue extérieure ${yes(v.allowExternal)}, padlock ${yes(v.allowPadlock)}, fumigènes ${yes(v.allowSmoke)}, récupération du planeur ${yes(v.allowRecovery)}, récupération d’altitude ${yes(v.allowHeight)}, classement en temps réel ${yes(v.allowScoring)}`);
        L.push('');
        L.push('## Briefing');
        L.push(v.briefing.trim() ? `- Texte imposé : « ${v.briefing.trim()} »` : '- Rédige un briefing court en français : objectif, circuit, météo attendue, pièges.');
        if (v.extra.trim()) {
            L.push('');
            L.push('## Autres consignes');
            L.push(v.extra.trim());
        }
        return L.join('\n');
    }
}
