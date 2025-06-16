/* jshint esversion: 6 */
window.HtmlUtils = class {
    /**
     * Enable or disable a button element.
     * @param {HTMLButtonElement|null} buttonElement
     * @param {boolean} isEnabled
     */
    static setButtonEnabled(buttonElement, isEnabled) {
        if (buttonElement) {
            if ('disabled' in buttonElement) {
                /** @type {{ disabled: boolean }} */ (buttonElement).disabled = !isEnabled;
            }

            buttonElement.style.opacity = isEnabled ? '' : disabledOpacity;
            buttonElement.style.cursor = isEnabled ? '' : 'default';
        }
    }

    static createTd(content) {
        const cell = document.createElement('td');
        if (content) {
            cell.appendChild(content);
        }
        return cell;
    }

    static createTextSpan(className, text) {
        const span = document.createElement('span');
        span.className = className;
        span.textContent = text;
        return span;
    }

    static createInput(className, value = '') {
        /** @type {HTMLInputElement} */
        const input = document.createElement('input');
        input.type = 'text';
        input.className = className;
        input.value = value;
        input.style.display = 'none';
        return input;
    }

    /**
     * Create a styled HTML button component.
     * @param {string} labelText Text or icon to display.
     * @param {string} className
     * @param {(event: MouseEvent) => void} [handler] Onclick callback.
     * @param {HTMLTableRowElement} [row]
     * @param {boolean} [hidden=false]
     * @returns {HTMLButtonElement}
     */
    static createButton(labelText, className, handler = null, row = null, hidden = false) {
        /** @type {HTMLButtonElement} */
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = labelText;
        button.className = className;
        if (handler) {
            const boundHandler = row ? handler.bind(null, row) : handler;
            button.addEventListener('click', boundHandler);
        }
        if (hidden) {
            button.style.display = 'none';
        }
        return button;
    }

};
