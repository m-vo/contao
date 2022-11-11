import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    selected = [];
    selectedElements = [];

    renaming = null;
    renameInput = null;

    static values = {
        propertiesUrl: String,
        renameUrl: String,
    }

    select(event) {
        event.stopPropagation();

        const selected = event.params.path;
        const element = event.currentTarget;

        if (this.selected.contains(selected)) {
            element.classList.remove('selected');

            this.selected = this.selected.filter(v => v !== selected);
            this.selectedElements = this.selectedElements.filter(v => v !== element);
        } else {
            element.classList.add('selected');

            this.selected.push(event.params.path);
            this.selectedElements.push(event.currentTarget);
        }

        this.requestPropertiesForSelection();

        this.stopRename();
    }

    rename(event) {
        const selected = event.params.path;
        const element = event.currentTarget;

        if (!this.selected.contains(selected) || this.renaming === selected) {
            return;
        }

        event.stopPropagation();

        this.renaming = selected;
        this.renameInput = document.createElement('form');

        const input = document.createElement('input');
        input.value = element.innerText;
        this.renameInput.append(input);
        element.before(this.renameInput);
        input.select();

        this.renameInput.addEventListener('submit', (e) => {
           e.preventDefault();

            fetch(this.renameUrlValue, {
                method: 'POST',
                headers: {
                    'Accept': 'text/vnd.turbo-stream.html',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    path: this.renaming,
                    new: input.value,
                })
            })
                .then(response => response.text())
                .then(html => {
                    Turbo.renderStreamMessage(html)
                })

           this.stopRename();
        });
    }

    stopRename() {
        if (this.renaming === null) {
            return;
        }

        this.renameInput.remove();
        this.renameInput = null;
        this.renaming = null;
    }

    deselect() {
        this.selectedElements.forEach(el => el.classList.remove('selected'));

        this.selected = [];
        this.selectedElements = [];

        this.requestPropertiesForSelection();

        this.stopRename();
    }

    requestPropertiesForSelection() {
        fetch(this.propertiesUrlValue, {
            method: 'POST',
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                paths: this.selected
            })
        })
            .then(response => response.text())
            .then(html => {
                Turbo.renderStreamMessage(html)
            })
    }
}
