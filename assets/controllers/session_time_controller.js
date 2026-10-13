import { Controller } from '@hotwired/stimulus';

/*
 * Time of a coming session for the reader: a countdown, and the time in the reader's own time zone
 * when it is not the one of Paris (members in La Réunion). The page gives the time of Paris; without
 * JavaScript, it stays as it is.
 */
const PARIS = 'Europe/Paris';
const DURATION = 150 * 60 * 1000; // Briefing, stop join and task (SessionCalendar::DURATION)

export default class extends Controller {
    static targets = ['countdown', 'local'];
    static values = { at: String, timed: Boolean };

    connect() {
        this.start = new Date(this.atValue);
        if (Number.isNaN(this.start.getTime())) {
            return;
        }
        this.showLocal();
        this.tick();
        this.timer = setInterval(() => this.tick(), 30 * 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        if (!this.hasCountdownTarget) {
            return;
        }
        const left = this.start.getTime() - Date.now();
        this.countdownTarget.textContent = this.timedValue ? countdown(left) : dayCountdown(this.start);
        this.countdownTarget.hidden = '' === this.countdownTarget.textContent;
    }

    showLocal() {
        const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!this.timedValue || !this.hasLocalTarget || !zone || zone === PARIS) {
            return;
        }
        const paris = clock(this.start, PARIS);
        const here = clock(this.start, zone);
        if (paris.time === here.time) {
            return;
        }
        this.localTarget.textContent = `soit ${paris.day === here.day ? '' : `${here.day} `}${here.time} chez vous`;
        this.localTarget.hidden = false;
    }
}

function countdown(left) {
    if (left <= -DURATION) {
        return '';
    }
    if (left <= 0) {
        return 'En cours';
    }
    const minutes = Math.ceil(left / 60000);
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    if (days > 0) {
        return `Dans ${days} j${hours > 0 ? ` ${hours} h` : ''}`;
    }
    if (hours > 0) {
        return `Dans ${hours} h ${String(minutes % 60).padStart(2, '0')}`;
    }

    return `Dans ${minutes} min`;
}

/* A session without time: days until its date, in the time of Paris. */
function dayCountdown(start) {
    const key = (date) => new Intl.DateTimeFormat('en-CA', { timeZone: PARIS }).format(date);
    const days = Math.round((Date.parse(key(start)) - Date.parse(key(new Date()))) / 86400000);
    if (days < 0) {
        return '';
    }

    return 0 === days ? 'Aujourd’hui' : (1 === days ? 'Demain' : `Dans ${days} jours`);
}

function clock(date, timeZone) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('fr-FR', {
        timeZone, weekday: 'long', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map((part) => [part.type, part.value]));

    return { day: parts.weekday, time: `${parts.hour}h${parts.minute}` };
}
