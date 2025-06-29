/**
 * UserPage.js
 * Handle functionality for non-admin pages.
 */

///////////////////
// Tree view event listeners to handle expand/collapse behavior.
///////////////////

// Get all the folder elements in the tree view.
const folders = document.querySelectorAll('.expand-collapse');

// Add a click event listener to each folder
folders.forEach(addFoldClickHandler);

function addFoldClickHandler(folder) {
    console.log("Adding onClick to fold: " + folder.textContent);
    folder.addEventListener('click', foldElement);
}

function foldElement(e) {
    // Get the span element that was clicked: should probably be a class rather than just span.
    // Toggle the expand/collapse state of the folder.
    this.parentElement.classList.toggle("expanded");
    this.parentElement.classList.toggle("collapsed");
    if ('<?= ICON_EXPANDED ?>' === this.textContent) {
        this.textContent = '<?= ICON_COLLAPSED ?>';
    } else if ('<?= ICON_COLLAPSED ?>' === this.textContent) { // Explicit elseif lets OTHER spans to go untouched.
        this.textContent = '<?= ICON_EXPANDED ?>';
    }

    e.stopPropagation();
}

///////////////////
// Tree view event listeners to handle link-click behavior, and the initial onLoad().
///////////////////

// Get all the folder elements in the tree view.
const links = document.querySelectorAll('.path-link');

// Add a click event listener to each folder
links.forEach(addLinkClickHandler);

// Add a listener to handle browser back/forward buttons.
window.onpopstate = handleNavigation;

function handleNavigation(e) {
    if (e.state) {
        openLinkInline(e.state.html + "?i=1", false);
        document.title = e.state.pageTitle;
    }
}

/**
 * Handle link clicking, to load content into the content div
 * @param {string} url The link to load.
 * @param saveHistory True to add the followed link to browser history: false for back/forward button handling.
 * @returns {Promise<void>}
 */
async function openLinkInline(url, saveHistory = true) {
    console.log("Opening link inline: " + url);

    const content = document.getElementById("content");
    const newContent = clearContentDiv(content); // Ensure event listeners are removed.
    clearAddedStyles(); // Remove any styles we loaded from a previous page load.

    let title;
    try {
        // Import the title, body and styles from the loaded document.
        const doc = await fetchRemoteDocument(url);
        title = doc.querySelector('title')?.innerText;
        importRemoteStyles(doc.head);
        importRemoteContent(doc.body, newContent);
        console.log("Got to writing.");
    } catch (error) {
        // Report our failure.
        console.error(error);
        title = 'Error loading page';
        const element = document.createElement('h1');
        element.textContent = title;
        newContent.appendChild(element);
    }
    document.title = getSiteName() + ' - ' + title;

    // Ensure our handler loads all child links in the content div.
    addLinksToContent(newContent);

    // Ensure that history will work.
    if (saveHistory) {
        const historyUrl = url.replace(/(?:\?|&(?:amp;)?)i=\d+/, ''); // Strip out "inline" instruction.
        console.log("Updating URL to '" + historyUrl + "'.");
        window.history.pushState({"html": historyUrl, "pageTitle": title}, '', historyUrl);
    }
}

/** Fetch and parse the HTML document from a URL. */
async function fetchRemoteDocument(url) {
    const response = await fetch(url);
    const html = await response.text();
    const parser = new DOMParser();
    return parser.parseFromString(html, 'text/html');
}

/** Remove all <style> and <link rel="stylesheet"> tags from the document's <head> node, except the first one. */
function clearAddedStyles() {
    const stylesAndLinks = document.head.querySelectorAll('style, link[rel="stylesheet"]');
    for (let i = 1; i < stylesAndLinks.length; i++) {
        stylesAndLinks[i].remove();
    }
}

/** Append all <style> and <link rel="stylesheet"> elements from a <head> element into the current document. */
function importRemoteStyles(remoteHead) {
    const remoteStylesAndLinks = remoteHead.querySelectorAll('style, link[rel="stylesheet"]');
    for (const el of remoteStylesAndLinks) {
        const clonedChild = el.cloneNode(true);
        document.head.appendChild(clonedChild);
    }
}

/** Get predefined template elements from the remote page to the target container.
 * @param remoteBody The downloaded web page.
 * @param targetContainer The div we should put the body text into.
 */
function importRemoteContent(remoteBody, targetContainer) {
    // Cleanup from previous template
    if ('function' === typeof window.cleanupTemplate) {
        window.cleanupTemplate();
        window.cleanupTemplate = undefined;
    }

    // Load new content
    const content = remoteBody.querySelector('#template-content');
    const script = remoteBody.querySelector('#template-script');

    if (content) {
        const clonedNode = content.cloneNode(true);
        targetContainer.appendChild(clonedNode);
    }

    if (script) {
        const newScript = document.createElement('script');
        newScript.textContent = script.textContent;
        targetContainer.appendChild(newScript);

        // Wait for DOM update and script execution.
        // Use a small timeout to ensure the script has time to define setupTemplate.
        console.log("Waiting to call setup");
        setTimeout(callSetupTemplate, 100);
    }
}

function callSetupTemplate() {
    console.log("Checking setup exists");
    if ('function' === typeof window.setupTemplate) {
        console.log("Calling setup");
        window.setupTemplate();
    } else {
        console.log("Setup not found");
    }
}

/** Ensure our handler loads all child links in the content div. */
function addLinksToContent(content) {
    const links = content.querySelectorAll('a');
    links.forEach(addLinkClickHandler);
}

/** Safely clear the div using the DOM, so all event handlers are cleanly killed without memory leaks. */
function clearContentDiv(oldContentDiv) {
    // Find the parent element (where the div is located)
    const parent = document.getElementById('parent-container');

    // Remove the old content div
    let nextSibling = null;
    if (oldContentDiv) {
        nextSibling = oldContentDiv.nextSibling;
        oldContentDiv.remove(); // Remove the div along with its children and event listeners
    }

    // Create the new content div, with the same properties as the original.
    const newContentDiv = document.createElement('div');
    newContentDiv.classList.add('content');
    newContentDiv.classList.add('right-column');
    newContentDiv.id = 'content';

    // Insert the new div at the same position
    if (nextSibling) {
        parent.insertBefore(newContentDiv, nextSibling); // Insert it before the next sibling of the old div.
    } else {
        parent.appendChild(newContentDiv); // If no next sibling (so, the last child), append the new div.
    }
    return newContentDiv;
}

/**
 * Add an onclick handler to a link.
 * @param {Element} link
 */
function addLinkClickHandler(link) {
    const attr = link.getAttribute("href");
    if (attr.includes('?i=1')) {
        console.log("Adding onClick to child link: " + attr);
        link.addEventListener('click', clickLink);
    } else {
        console.log("Not adding onClick to primary link: " + attr);
    }
}

function clickLink(e) {
    console.log("Onclick link.");
    // Prevent link from navigating.
    e.preventDefault();

    // Remove 'selected' from any previously selected li and apply to current.
    const selectedItems = document.querySelectorAll('li.selected');
    selectedItems.forEach(removeSelectedClass);

    // Can't just use this.parent, as it might be from a link in a template.
    const targetUrl = e.currentTarget.href;
    const selectedParent = document.querySelector(`li > a[href="${targetUrl}"]`)?.parentElement;
    if (selectedParent) {
        selectedParent.classList.add('selected'); // Assumes the href is an immediate child of the li.
        if (selectedParent.classList.contains('collapsed')) {
            const child = selectedParent.querySelector('.expand-collapse');
            if (child && 'function' === typeof child.click) {
                child.click(); // Call the click handler of the expander, to expand and swap icons.
            }
        }
    }

    const attr = this.getAttribute("href");
    openLinkInline(attr);
}

function removeSelectedClass(listItem) {
    listItem.classList.remove('selected');
}

/** Just to troll my wife, get a random name for the site. */
function getSiteName() {
    const a = [
        'Memories', 'Mayhem', 'Merriment', 'Madness', 'Moonshine', 'Mountains', 'Mastery', 'Machines',
        'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
    ];
    return 'Midwest ' + a[~~(Math.random() * a.length)];
}
