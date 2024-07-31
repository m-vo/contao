import { Controller } from '@hotwired/stimulus';
import { Stream } from '../modules/stream';
import { TwigEditor } from "../modules/twig-editor";
import {
    singleProperty
} from "../../public/vendors-node_modules_pnpm_ace-builds_1_35_2_node_modules_ace-builds_src-noconflict_worker-css_js.464efab6";

export default class extends Controller {
    stream = new Stream();

    static values = {
        navigateUrl: String,
        blockInfoUrl: String,
    }

    static targets = ['editor'];
    static classes = [];

    navigate(el) {
        // todo: open tab if already open
        this.stream.open(
            this.navigateUrlValue + '?' + new URLSearchParams({item: el.currentTarget.dataset.item}).toString(),
        );
    }

    connect() {
        this.element.addEventListener('block-info', e => {
            this.stream.open(
                this.blockInfoUrlValue + '?' + new URLSearchParams(e.detail).toString(),
            )
        })

        this.element.addEventListener('navigate', e => {
            this.stream.open(
                this.navigateUrlValue + '?' + new URLSearchParams(e.detail).toString(),
            )
        })

        // // todo: remove test
        // this.stream.open(
        //     this.navigateUrlValue + '?' + new URLSearchParams({item: 'content_element/text/foo'}).toString(), 'GET', {}, false
        // );
        //
        // this.stream.open(
        //     this.blockInfoUrlValue + '?' + new URLSearchParams({item: '@Contao_Global/content_element/text/foo.html.twig', block: 'content'}).toString(),
        // );
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
}
