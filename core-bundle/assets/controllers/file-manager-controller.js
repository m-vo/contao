import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        navigateUrl: String,
        detailsUrl: String,
        deleteUrl: String,
    }

    static targets = ['controls', 'listing'];
    static classes = ['grid', 'list'];

    abortController = null;

    connect() {
        this.viewList();
        this.setupLoaderAnimation();
    }

    viewList() {
        this.element.classList.add(this.listClass);
        this.element.classList.remove(this.gridClass);
    }

    viewGrid() {
        this.element.classList.add(this.gridClass);
        this.element.classList.remove(this.listClass);
    }

    select(e) {
        e.preventDefault();
        e.stopPropagation();

        e.currentTarget.previousElementSibling.click();

        this.loadDetails();
    }

    deselect(e) {
        if(e.target !== e.currentTarget) {
            return;
        }

        const selected = this.getSelectedElements();

        selected.forEach(el => {
            el.checked = false;
        })

        this.loadDetails();
    }

    delete(e) {
        if(!confirm(e.currentTarget.dataset.confirm)) {
            return;
        }

        this.fetchAndHandleStream(this.deleteUrlValue);
    }

    getSelectedElements() {
        return Array
            .from(this.listingTarget.querySelectorAll('input[type="checkbox"][data-item]'))
            .filter(e => e.checked)
        ;
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
        this.fetchAndHandleStream(this.detailsUrlValue, true);
    }

    fetchAndHandleStream(url, abortPrevious = false) {
        let params = {
            method: 'POST',
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({paths: this.getSelectedPaths()}),
        };

        if(abortPrevious) {
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
            .then(html => { Turbo.renderStreamMessage(html)})
            .catch(() => {})
        ;
    }
}
