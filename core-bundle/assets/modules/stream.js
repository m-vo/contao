export class Stream {
    abortController = null;

    performRequest(url, method = 'GET', data = {}, single = false) {
        let params = {
            method,
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
                'Content-Type': 'application/json'
            },
        };

        if (method === 'GET') {
            url += '?' + new URLSearchParams(data).toString();
        } else {
            params = {
                ...params,
                body: typeof data === 'object' ? JSON.stringify(data) : data,
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
