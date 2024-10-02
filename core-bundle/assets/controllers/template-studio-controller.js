import { Controller } from '@hotwired/stimulus';
import { TwigEditor } from "../modules/twig-editor";
import { TurboCable } from "../modules/turbo-cable";

export default class extends Controller {
    editors = new Map();
    turboCable = new TurboCable();

    static values = {
        followUrl: String,
        blockInfoUrl: String,
    }

    static targets = ['editor'];

    connect() {
        // Subscribe to events dispatched by the editors
        this.element.addEventListener('twig-editor:lens:follow', (e) => {
            this.turboCable.get(this.followUrlValue, {name: e.detail.name});
        })

        this.element.addEventListener('twig-editor:lens:block-info', (e) => {
            this.turboCable.get(this.blockInfoUrlValue, e.detail);
        })
    }

    openTab(el) {
        fetch(el.currentTarget.dataset.url, {
            method: 'GET',
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
            },
        })
        .then(response => response.text())
        .then(html => {
            Turbo.renderStreamMessage(html)
        })
        .catch((e) => {
            if (e.name !== 'AbortError') {
                console.error(e, e.type);
            }
        });
    }

    editorTargetConnected(el) {
        this.editors.set(el, new TwigEditor(el.querySelector('textarea')));
    }

    editorTargetDisconnected(el) {
        this.editors.get(el).destroy();
        this.editors.delete(el);
    }

    colorChange(event) {
        this.editors.forEach(editor => {
            editor.setColorScheme(event.detail.mode);
        })
    }
}
