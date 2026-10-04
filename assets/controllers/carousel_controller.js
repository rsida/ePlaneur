import { Controller } from '@hotwired/stimulus';

/*
 * Image carousel: previous/next arrows, thumbnails, keyboard arrows when focused, counter and
 * caption of the current picture.
 */
export default class extends Controller {
    static targets = ['slide', 'thumb', 'caption', 'current'];

    connect() {
        this.index = 0;
        this.keydown = (event) => {
            if (event.key === 'ArrowRight') this.next();
            if (event.key === 'ArrowLeft') this.previous();
        };
        this.element.addEventListener('keydown', this.keydown);
    }

    disconnect() {
        this.element.removeEventListener('keydown', this.keydown);
    }

    next() {
        this.go(this.index + 1);
    }

    previous() {
        this.go(this.index - 1);
    }

    show(event) {
        this.go(event.params.index);
    }

    go(index) {
        const count = this.slideTargets.length;
        this.index = (index + count) % count;
        const toggle = (elements) => elements.forEach((element, i) => { element.hidden = i !== this.index; });
        toggle(this.slideTargets);
        toggle(this.captionTargets);
        this.thumbTargets.forEach((thumb, i) => {
            if (i === this.index) {
                thumb.setAttribute('aria-current', 'true');
            } else {
                thumb.removeAttribute('aria-current');
            }
        });
        if (this.hasCurrentTarget) {
            this.currentTarget.textContent = String(this.index + 1).padStart(2, '0');
        }
    }
}
