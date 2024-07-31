export class Stream {
    abortController = null;

    open(url, method = 'GET', data = {}, single = true) {
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
