/* jshint esversion: 6 */

/**
 * Handles logging messages to the admin interface.
 */
window.Log = class {
    /**
     * Log a message to the `messages` container.
     * @param {string} message - The message to log.
     * @returns {HTMLParagraphElement} The created paragraph element.
     */
    static message(message) {
        const messagesDiv = document.getElementById('messages');
        if (!messagesDiv) {
            console.warn('Messages container not found');
            return null;
        }

        const p = document.createElement('p');
        p.textContent = message;
        messagesDiv.appendChild(p);

        const autoscroll = document.getElementById('autoscroll');
        if (autoscroll?.checked) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        return p;
    }

    /**
     * Clear all messages from the messages container.
     */
    static clear() {
        const messagesDiv = document.getElementById('messages');
        if (messagesDiv) {
            messagesDiv.replaceChildren();
        }
    }
};
