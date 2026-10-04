import { Controller } from '@hotwired/stimulus';

/*
 * Click-to-load video: replaces the poster with the YouTube (nocookie) or Vimeo player only when
 * the reader asks for it, so no third-party content is loaded before.
 */
export default class extends Controller {
    static values = { src: String, title: String };

    play(event) {
        if (!this.srcValue) {
            return;
        }
        event.preventDefault();
        const iframe = document.createElement('iframe');
        iframe.src = this.srcValue;
        iframe.title = this.titleValue;
        iframe.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
        iframe.allowFullscreen = true;
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        this.element.replaceChildren(iframe);
        iframe.focus();
    }
}
