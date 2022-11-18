import {Controller} from '@hotwired/stimulus';

export default class FileManagerController extends Controller {
    static values = {
        navigateUrl: String,
        detailsUrl: String,
        deleteUrl: String,
        moveUrl: String,
    }

    static targets = ['controls', 'listing', 'modal'];
    static classes = ['grid', 'list', 'dragging', 'dropping'];

    abortController = null;

    connect() {
        this.viewList();
        this.setupLoaderAnimation();
    }

    /**
     * Switch the view mode to "list".
     */
    viewList() {
        this.element.classList.add(this.listClass);
        this.element.classList.remove(this.gridClass);
    }

    /**
     * Switch the view mode to "grid".
     */
    viewGrid() {
        this.element.classList.add(this.gridClass);
        this.element.classList.remove(this.listClass);
    }

    /**
     * Select/deselect an item and load the details panel.
     */
    select(e) {
        e.preventDefault();
        e.stopPropagation();

        // Select the corresponding checkbox
        this.element
            .querySelector(`input[type="checkbox"][data-item="${e.currentTarget.dataset.item}"]`)
            ?.click();

        this.loadDetails();
    }

    /**
     * Deselect all selected items and load the details panel.
     */
    deselectAll(e) {
        if (e.target !== e.currentTarget && e.target !== this.listingTarget) {
            return;
        }

        const selected = this.getSelectedElements();

        if(!selected.length) {
            return;
        }

        selected.forEach(el => {
            el.checked = false;
        })

        this.loadDetails();
    }

    /**
     * Request deletion of the selected items.
     */
    delete(e) {
        if (!confirm(e.currentTarget.dataset.confirm)) {
            return;
        }

        this.fetchAndHandleStream(
            this.deleteUrlValue,
            'DELETE',
            {paths: this.getSelectedPaths()},
            false
        );
    }

    /**
     * Navigate to directory or open file after a double click.
     */
    navigate(e) {
        e.preventDefault();

        this.fetchAndHandleStream(
            `${this.navigateUrlValue}/${e.currentTarget.dataset.item}`,
        );
    }

    /**
     * Close the modal by removing it from the DOM.
     */
    closeModal() {
        this.modalTarget?.remove();
    }

    dragStart(e) {
        e.dataTransfer.setData(
            'application/filesystem-item',
            e.target.closest('*[data-item]').dataset.item
        );

        e.dataTransfer.effectAllowed = "move";
    }

    dragEnter(e) {
        e.preventDefault();

        // Adding the "dragging" class makes sure, that pointer events are
        // deactivated on all children of the drop targets. We reset this once
        // the drag&drop operation is completed (dragEnd).
        this.element.classList.add(this.draggingClass);
        e.target.classList.add(this.droppingClass);
    }

    dragLeave(e) {
        e.target.classList.remove(this.droppingClass);
    }

    dragOver(e) {
        e.preventDefault();
    }

    dragEnd(e) {
        this.element.classList.remove(this.draggingClass);
    }

    dragDrop(e) {
        e.preventDefault();
        e.target.classList.remove(this.droppingClass);

        if (!e.target.hasAttribute('data-item')) {
            // todo: default target

            return;
        }

        const to = e.target.dataset.item;

        // Move
        const source = e.dataTransfer.getData('application/filesystem-item');

        if (source !== '') {
            const from = [...new Set([...this.getSelectedPaths(), source])];

            if (from.contains(to)) {
                return;
            }

            this.fetchAndHandleStream(
                this.moveUrlValue,
                'PATCH',
                {from, to},
                false
            );

            return;
        }

        // Uploads
        [...e.dataTransfer.items].filter(i => i.kind === 'file').forEach(item => {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                console.log(`File upload ${file.name} to ${to}`);
            } else {
                console.log(item, item.kind);
            }
        });
    }

    getSelectedElements() {
        return [...this.listingTarget.querySelectorAll('input[type="checkbox"][data-item]')]
            .filter(e => e.checked);
    }

    getSelectedPaths() {
        return this.getSelectedElements().map(el => el.dataset.item);
    }

    setupLoaderAnimation() {
        document.addEventListener('turbo:before-fetch-request', (event) => {
            if (!event.detail.url.pathname.startsWith(`${this.navigateUrlValue}/`)) {
                return;
            }

            this.element.classList.add('loading');
        });

        document.addEventListener('turbo:before-fetch-response', () => {
            this.element.classList.remove('loading');
        });
    }

    loadDetails() {
        this.fetchAndHandleStream(
            this.detailsUrlValue,
            'POST',
            {paths: this.getSelectedPaths()},
        );
    }

    fetchAndHandleStream(url, method = 'GET', data = {}, single = true) {
        let params = {
            method,
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'Content-Type': 'application/json'
            },
        };

        if(method !== 'GET') {
            params = {
                ...params,
                body: JSON.stringify(data),
            }
        }

        if (single) {
            if (null !== this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();

            params = {
                ...params,
                signal: this.abortController.signal,
            }
        }

        fetch(url, params)
            .then(response => response.text())
            .then(html => {
                Turbo.renderStreamMessage(html)
            })
            .catch((e) => {
                if(e.name !== 'AbortError')
                    console.error(e, e.type);
            })
        ;
    }
}
