import {Controller} from '@hotwired/stimulus';

export default class ClipboardCopyController extends Controller {
    static classes = ['copied'];
    static instances = [];

    connect() {
        if(!ClipboardCopyController.instances.contains(this)) {
            ClipboardCopyController.instances.push(this);
        }

        this.element.addEventListener('click', e => {
            e.preventDefault();

            navigator.clipboard
                .writeText(this.element.textContent)
                .then(() => {
                    // (Re-) add "copied" class to give a visual feedback
                    this.reset();

                    requestAnimationFrame(() => {
                        this.element.classList.add(this.copiedClass);
                    });

                    // Notify others
                    ClipboardCopyController.instances
                        .filter(i => i !== this)
                        .forEach(i => i.reset())
                    ;
                })
        });
    }

    disconnect() {
        ClipboardCopyController.instances = ClipboardCopyController.instances.filter(v => v !== this);
    }

    reset() {
        this.element.classList.remove(this.copiedClass);
    }
}
