import {Controller} from '@hotwired/stimulus';

export default class FileManagerController extends Controller {
    static values = {
        navigateUrl: String,
        detailsUrl: String,
        deleteUrl: String,
        moveUrl: String,
    }

    static targets = ['controls', 'listing', 'modal', 'dragImage'];
    static classes = ['grid', 'list', 'dragging', 'dropping'];

    // Used to abort ongoing fetches
    abortController = null;

    connect() {
        this.viewList();
        this.setupLoaderAnimation();
    }

    /**
     * Action: Switch the view mode to "list".
     */
    viewList() {
        this.element.classList.add(this.listClass);
        this.element.classList.remove(this.gridClass);
    }

    /**
     * Action: Switch the view mode to "grid".
     */
    viewGrid() {
        this.element.classList.add(this.gridClass);
        this.element.classList.remove(this.listClass);
    }

    /**
     * Action: Load the details panel with the current selection.
     */
    select(e) {
        this.loadDetails();
    }

    /**
     * Action: Deselect all selected items and load the details panel.
     */
    deselectAll(e) {
        if (e.target !== e.currentTarget && e.target !== this.listingTarget) {
            return;
        }

        const selected = this.getSelectedElements();

        if (!selected.length) {
            return;
        }

        selected.forEach(el => {
            el.checked = false;
        })

        this.loadDetails();
    }

    /**
     * Action: Request deletion of the selected items.
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
     * Action: Open the resource on double click (edit file/navigate to directory).
     */
    navigate(e) {
        e.preventDefault();

        const path = this.getCurrentPath(e.currentTarget);

        this.fetchAndHandleStream(
            this.navigateUrlValue + '?' + new URLSearchParams({path}).toString(),
        );
    }

    /**
     * Action: Close the modal by removing it from the DOM.
     */
    closeModal() {
        this.modalTarget?.remove();
    }

    dragStart(e) {
        const path = this.getCurrentPath(e.path.find(el => el.nodeName === 'LABEL'));

        e.dataTransfer.setData('application/filesystem-item', path);
        e.dataTransfer.effectAllowed = "move";

        // Dynamic drag image
        const radius = 15;
        const paths = [...new Set([...this.getSelectedPaths(), path])];

        const canvas = this.dragImageTarget;
        canvas.width = canvas.height = 2 * radius;
        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);

        context.strokeStyle = '#d3d5d7';
        context.strokeWidth = '2';
        context.fillStyle = '#eaecef';
        context.beginPath();
        context.arc(radius, radius, radius - 2, 0, 2 * Math.PI);
        context.fill();
        context.stroke();
        context.closePath();

        context.font = 'bold 14px sans-serif';
        context.textAlign = 'center';
        context.textBaseline = "middle";
        context.fillStyle = '#006494';
        context.fillText(paths.length.toString(), radius, radius);

        e.dataTransfer.setDragImage(canvas, radius, -10);
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

        const targetElement = e.path.find(el => el.hasAttribute('data-droppable'));

        if(targetElement?.dataset.droppable === 'file') {
            return;
        }

        const source = e.dataTransfer.getData('application/filesystem-item');
        const target = this.getCurrentPath(targetElement);

        // Move
        if (source !== '') {
            // Include currently selected
            const paths = [...new Set([...this.getSelectedPaths(), source])];

            // Ignore dropping to the same directory
            const baseDir = paths[0].match(/^(.+)\/[^\/]+$/);
            if(baseDir?.[1] ?? '' === target) {
                return;
            }

            this.fetchAndHandleStream(
                this.moveUrlValue,
                'PATCH',
                {paths, target},
                false
            );

            return;
        }

        // Uploads
        [...e.dataTransfer.items].filter(i => i.kind === 'file').forEach(item => {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                console.log(`File upload ${file.name} to ${target}`);
            } else {
                console.log(item, item.kind);
            }
        });
    }

    getCurrentPath(target) {
        if(target.nodeName === 'LABEL') {
            target = this.listingTarget.getElementById(target.htmlFor);
        }

        return target.dataset.item;
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

        if (method !== 'GET') {
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
                if (e.name !== 'AbortError')
                    console.error(e, e.type);
            })
        ;
    }
}
