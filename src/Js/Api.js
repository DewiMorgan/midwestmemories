// iOS lacks native console debugging, so we work around it here:
(function () {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

    if (isIOS) {
        const originalLog = console.log;
        console.log = function () {
            const debugDiv = document.getElementById("debug");
            if (debugDiv) {
                const args = Array.from(arguments).map(arg =>
                    typeof arg === "object" ? JSON.stringify(arg) : String(arg)
                );
                debugDiv.innerHTML += args.join(" ") + "<br>";
            }
            originalLog.apply(console, arguments); // Preserve default behavior
        };
    }
})();


/* jshint esversion: 6 */
window.Api = class {
    /**
     * API wrapper to call an endpoint and return the data object, or an exception on error.
     * @template T
     * @param {string} url - The API endpoint to fetch.
     * @param {string} [method='GET'] - The HTTP method.
     * @param {Object|null} [payload=null] - Optional payload for POST/PUT/PATCH.
     * @param {'string'|'object'|'array'} expectedType - Expected type for the `data` payload.
     * @returns {Promise<T>} - The validated data response.
     * @throws {Error} - If the response status or data type is incorrect.
     */
    // noinspection FunctionWithMoreThanThreeNegationsJS
    static async fetchApiData(url, method = 'GET', expectedType = 'array', payload = null) {
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'same-origin'  // or 'include' if cross-origin with CORS
        };

        if (null !== payload && ['POST', 'PUT', 'PATCH'].includes(method)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        console.log(`Fetch()ing API call ${method} ${url} ${expectedType}`, options); // DELETEME DEBUG
        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }

        const jsonResponse = await response.json();

        if (!jsonResponse.hasOwnProperty('data')) {
            throw new Error("Response JSON does not contain a 'data' property.");
        }

        const data = jsonResponse.data;

        const actualType = Array.isArray(data) ? 'array' : typeof data;
        if (actualType !== expectedType) {
            console.log(`Received ${actualType}, expected ${expectedType}`, data);
            throw new Error(`Expected 'data' to be ${expectedType}, but got ${actualType}.`);
        }

        if (jsonResponse.hasOwnProperty('error')) {
            throw new Error(jsonResponse.error);
        }

        return data;
    }
};
