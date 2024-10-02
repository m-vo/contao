export class TurboCable {
    get(url, queryParams = null) {
        if(queryParams !== null) {
            url += '?' + new URLSearchParams(queryParams).toString();
        }

        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'text/vnd.turbo-stream.html',
            },
        })
            .then(response => {
                if(response.ok)
                {
                    return response.text();
                }

                throw new Error(`Something went wrong requesting resource "${url}".`);
            })
            .then(html => {
                Turbo.renderStreamMessage(html)
            })
            .catch((e) => {
                if (e.name === 'AbortError') {
                    return;
                }

                console.error(e, e.type);

                const message = document.createElement('div');
                message.classList.add('message', 'message--error');
                message.textContent = 'Oops, something went wrong fetching a resource. Please check the browser console for more details.';

                document.querySelector('*[data-message-outlet]').appendChild(message);
            });
    }
}
