/* jshint esversion: 6 */
window.Dropbox = class {
    /**
     * API wrapper for dropbox polling endpoint. Call the endpoint until all items are processed.
     * @returns {Promise<void>}
     */
    static async handleDropboxPolling() {
        Log.message(`Asking Dropbox to get the next page of files...`);
        try {
            while (true) {
                /**
                 * @typedef {{numAddedFiles:int, numTotalFiles:int, hasMoreFiles:boolean, error:string}} FileStatus
                 * @type {Promise<FileStatus>}
                 */
                const data = await Api.fetchApiData('/api/v1.0/cursor', 'GET', 'object');

                // Enforce syntax.
                data.moreFilesToGo ??= false;
                data.numAddedFiles ??= 0;
                data.numTotalFiles ??= 0;

                if (true === data.moreFilesToGo) {
                    Log.message(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, more coming...`);
                } else {
                    Log.message(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, finished!`);
                    return;
                }
            }
        } catch (err) {
            Log.message(`= Listing new files failed: ${err.message}`);
        }
    }

    /**
     * Generic file task handler. GET a list from an API endpoint, then POST to the same endpoint to process each item.
     * @param {string} actionName Just for logging, what API action we're performing.
     * @param {string} endpoint The endpoint to `GET` for a list, and POST for each item.
     * @returns {Promise<void>}
     */
    static async handleFileTask(actionName, endpoint) {
        Log.message(`Getting list of files for ${actionName}...`);
        try {
            const files = await Api.fetchApiData(endpoint, 'GET', 'array');
            const numFiles = files.length;
            if (0 === numFiles) {
                Log.message(`= Got zero files for ${actionName}.`);
            }
            for (const [index, filename] of files.entries()) {
                Log.message(`= ${index + 1}/${numFiles} ${actionName} ${filename}...`);
                await Api.fetchApiData(endpoint, 'POST', 'object');
            }
            Log.message(`= ${actionName} complete!`);
        } catch (err) {
            Log.message(`= ${actionName} failed: ${err.message}`);
        }
    }

    initializeCursor() {
        // Get the initial page of files, resetting the cursor.
        Log.message('Reinitializing Dropbox list...');
        try {
            // noinspection VoidExpressionJS
            void Api.fetchApiData('/api/v1.0/cursor', 'POST', 'object');
            Log.message(`= Reinitializing Dropbox list succeeded.`);
        } catch (err) {
            Log.message(`= Reinitializing Dropbox list failed: ${err.message}`);
        }

        // Get all remaining pages and process them.
        // noinspection VoidExpressionJS
        void Dropbox.runAllUpdates()
    }

    /**
     * @returns {Promise<void>}
     */
    static async runAllUpdates() {
        // Get and queue updates from Dropbox.
        await Dropbox.handleDropboxPolling();
        // Download queued downloads.
        await Dropbox.handleFileTask('Downloading', '/api/v1.0/download');
        // Generate queued thumbnails.
        await Dropbox.handleFileTask('Postprocessing', '/api/v1.0/process');
    }
};
