import { Controller } from '@hotwired/stimulus';
import { Stream } from '../modules/stream';
import { TwigEditor } from "../modules/twig-editor";

export default class extends Controller {
    stream = new Stream();
    fullscreen = false;
    trackUrlHashDelegate = this.trackUrlHash.bind(this);

    static values = {
        resolveAndOpenUrl: String,
        blockInfoUrl: String,
    }

    static targets = ['chrome', 'editor', 'info'];
    static classes = [];

    connect() {
        this.element.addEventListener('block-info', e => {
            this.stream.performRequest(this.blockInfoUrlValue, 'GET', e.detail);
        })

        this.element.addEventListener('open', e => {
            this.stream.performRequest(this.resolveAndOpenUrlValue, 'GET', {name: e.detail.name});
        })

        this.element.addEventListener('save', e => {
            this.stream.performRequest(e.detail.resourceUrl, 'PUT', e.detail.content);
        })

        window.addEventListener('hashchange', this.trackUrlHashDelegate);
        this.trackUrlHash();
    }

    disconnect() {
        window.removeEventListener('hashchange', this.trackUrlHashDelegate);
    }

    visit(el) {
        this.stream.performRequest(el.currentTarget.dataset.url);
    }

    editorTargetConnected(el) {
        this.twigEditor = new TwigEditor(el.querySelector('textarea'));
    }

    editorTargetDisconnected(el){
        this.twigEditor?.destroy();
    }

    closeTab(el) {
        el.target.closest('.tab').remove();
    }

    closeInfo(el) {
        el.target.closest('turbo-frame').textContent = '';
    }

    enterFullscreen() {
        if(this.fullscreen) {
            return;
        }

        this.fullscreen = true;
        this.chromeTarget.classList.add('fullscreen');
        history.pushState({}, '', '#fullscreen');
    }

    exitFullscreen() {
        if(!this.fullscreen) {
            return;
        }

        this.fullscreen = false;
        this.chromeTarget.classList.remove('fullscreen');
        history.pushState({}, '', '#');
    }

    trackKeydown(e) {
        // todo: this should work by setting keydown.esc->… in the action, but it doesn't smh
        if (e.key === 'Escape') {
            this.exitFullscreen();
        }
    }

    trackUrlHash() {
        if(window.location.hash === '#fullscreen') {
            this.enterFullscreen();
        } else {
            this.exitFullscreen();
        }
    }
}
