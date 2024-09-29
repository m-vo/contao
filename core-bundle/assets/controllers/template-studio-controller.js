import {Controller} from '@hotwired/stimulus';
import {Stream} from '../modules/stream';
import {TwigEditor} from "../modules/twig-editor";

export default class extends Controller {
    stream = new Stream();
    fullscreen = false;
    editors = new Map();

    trackUrlHashDelegate = this.trackUrlHash.bind(this);
    onStreamRenderDelegate = this.onStreamRender.bind(this);

    static values = {
        resolveAndOpenUrl: String,
        blockInfoUrl: String,
    }

    static targets = ['chrome', 'editor', 'info'];
    static classes = [];

    connect() {
        window.addEventListener('turbo:before-stream-render', this.onStreamRenderDelegate);
        window.addEventListener('hashchange', this.trackUrlHashDelegate);

        this.trackUrlHash();

        // Connect editor events
        this.element.addEventListener('block-info', e => {
            this.stream.performRequest(this.blockInfoUrlValue, 'GET', e.detail);
        })

        this.element.addEventListener('open', e => {
            this.stream.performRequest(this.resolveAndOpenUrlValue, 'GET', {name: e.detail.name});
        })

        // Connect studio events
        this.element.addEventListener('template-studio:before-action', (event) => this.onAction(event));
    }

    disconnect() {
        window.removeEventListener('turbo:before-stream-render', this.onStreamRenderDelegate);
        window.removeEventListener('hashchange', this.trackUrlHashDelegate);
    }

    /**
     * Action: visit
     */
    visit(el) {
        this.stream.performRequest(el.currentTarget.dataset.url);
    }

    /**
     * Action: action
     */
    action(el) {
        let params = {
            method: 'POST',
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'Content-Type': 'application/json'
            },
        };

        this.element.dispatchEvent(
            new CustomEvent('template-studio:before-action', {
                bubbles: true,
                detail: {
                    action: el.currentTarget.dataset.actionName,
                    params,
                }
            })
        );

        fetch(el.currentTarget.dataset.url, params)
            .then(response => response.text())
            .then(html => {
                Turbo.renderStreamMessage(html)
            })
            .catch((e) => {
                if (e.name !== 'AbortError')
                    console.error(e, e.type);
            })
        ;
    }

    /**
     * Action: submit
     */
    submit(e) {
        e.preventDefault();
        this.stream.performSubmit(e);
    }

    editorTargetConnected(el) {
        this.editors.set(el, new TwigEditor(el.querySelector('textarea')));
    }

    editorTargetDisconnected(el) {
        this.editors.get(el).destroy();
        this.editors.delete(el);
    }

    getActiveEditor() {
        // todo: we could ask the tab controller for the active tab instead of assuming internals
        for(const el of this.element.querySelectorAll('#template-studio--tab-panels .tab-panel[data-active] *[data-contao--template-studio-target="editor"]')) {
            const editor = this.editors.get(el);
            if(editor && editor.isEditable()) {
                return editor;
            }
        }

        return null;
    }

    /**
     * Action: closeTab
     */
    closeTab(el) {
        el.target.closest('.tab').remove();
    }

    /**
     * Action: closePanel
     */
    closePanel(el) {
        el.target.closest('*[data-panel]').textContent = '';
    }

    modalTargetConnected(el) {
        el.showModal();
        el.querySelector('input')?.focus();
    }

    /**
     * Action: closeModal
     */
    closeModal(el) {
        el.target.closest('dialog').close();
    }

    /**
     * Action: enterFullscreen
     */
    enterFullscreen() {
        if (this.fullscreen) {
            return;
        }

        this.fullscreen = true;
        this.chromeTarget.classList.add('fullscreen');
        history.pushState({}, '', '#fullscreen');
    }

    exitFullscreen() {
        if (!this.fullscreen) {
            return;
        }

        this.fullscreen = false;
        this.chromeTarget.classList.remove('fullscreen');
        history.pushState({}, '', '#');
    }

    trackKeydown(event) {
        // todo: this should work by setting keydown.esc->… in the action, but it doesn't smh
        if (event.key === 'Escape') {
            this.exitFullscreen();
        }
    }

    trackUrlHash() {
        if (window.location.hash === '#fullscreen') {
            this.enterFullscreen();
        } else {
            this.exitFullscreen();
        }
    }

    onStreamRender(event) {
        const originalAction = event.detail.render

        event.detail.render = (streamElement) => {
            const componentUrl = streamElement.getAttribute('component-url');

            if (streamElement.action === 'refresh-components') {
                this.element.querySelectorAll('*[data-component-url]').forEach((el) => {
                    this.stream.performRequest(el.dataset.componentUrl);
                });

                return;
            }

            originalAction(streamElement);

            if (streamElement.action === 'update' && componentUrl !== null) {
                document.getElementById(streamElement.getAttribute('target')).dataset.componentUrl = componentUrl;
            }
        }
    }

    onAction(event) {
        // Populate data for the save action
        if(event.detail.action !== 'save_custom_template') {
            return;
        }

        event.detail.params.body = this.getActiveEditor()?.getContent();
    }
}
