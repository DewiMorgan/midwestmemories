## Current Issues

This is the stuff I'm actually working on, as steps towards the ultimate goal.

* Finish converting user logins to new mechanism.
* Need to change the UI to use the new JS/CSS system.
* Need to change the UI to use the new API system.
* Get folder-level details showing.
* Faster page loads (smaller initial images?)
* Create a "live" site, and a "test" site.

* Create CommentManager class.
* Convert Comments to use new API.
* Generate Web page tests.

* Whitelist /nonexistent.file, so 404 page can happen?
* Rate-limit by the IP as well as the username?
* User endpoints return a string, should maybe return an object like the file endpoints?
* Make the JS also handle endpoints using the lookup table?
* Change endpoints to be loaded as a class or config file or something.
* WONTFIX: Move source out of web root (edit autoloader?).
* DONE: Convert user management to session-and-DB based.

* Handle API errors instead of just checking `result.ok` (which just checks it was a 200).
    * Also make API errors not return 200 OK.
* Maybe in add users, make save button grey out until both username and password are filled? Or just give error on save?
* Improve admin UI: add "Are you sure" to cursor renewal.
* Correctly handle (and prevent) users that try to set their passwords to the string 'DISABLED'.

* Get it commentable.
    * sanitise comments
    * allow some rich text: bold and links and such.
    * CSS to make comments usable.
    * Clean up debugging output from files template.
    * Hide a bunch of unused fields in files template table output.
* Lower priority
    * API to edit comments.
    * Javascript to edit comments.
    * API to hide comments.
    * Admin Javascript to edit comments.
    * Convert template loading to an API? Have the API specify what script to run on display of a page?
    * Add next/previous to image view.
    * Display info texts with folders.
    * Increase PHP 8 feature usage: readonly properties and promoted properties.
    * Allow users to add more info texts.

* Get it usable on a phone.
* Search template?

* Comments
    * Optimize adding comments, by show/hide the textarea instead of removing and recreating it all the time.

* ThumbsTemplate
    * https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 doesn't fill img names/title from Metadata.
    * In ThumbsTemplate, we need to populate things like $h_pageTitle per instructions in comment at top of that file.
        * The data is stored/read into Metadata. Treat same as FileTemplate.
    * Ini files don't handle subfolder details. Probably details should come from ini files in subfolders?
    * Folder Descriptions from Dropbox\midwestmemories\Auora\Horst and Karin slide tray sections.txt.
        * The descriptions are short enough, it feels like it'd maybe be worth also showing them on the page for each
          file, as context of what's going on in the pic.
    * At certain widths in the folder "section 1", one image goes to a second line, giving row lengths of 7, 7, 1, 7, 3.

* TreeTemplate
    * Left navbar should be overflow:scroll.
    * Bug: fail to resize right-bar: drag bar left, then ctrl+m-wheel down.
    * Feature? Clicking folder thumbs doesn't open *parent* folders if the current was collapsed.
    * Add some visual hint to the drag-bar to show it can be dragged. Like a vertically-centered "⋮" or something.
    * Using "fold/folder" is confusing, replace with collapse/collapser.
    * Can we get rid of the `i` param and replace it with leading /img, /api, /tpl, /search, etc.?

* Metadata class
    * Add hasNext and hasPrev properties to enable next/prev buttons in FileTemplate.
    * data from all parent folders isn't loaded at all.
    * Saving inherited data: do we save it only if it was modified? Seems sensible.
    * How do we distinguish inherited data in the returned data structure?
    * Should I instead have a getInheritedValue($filename, $key), for templates to call for missing values?
    * Versioned comments: how to represent, store, and so on? Just backup copies of the ini file? In a backup subfolder?
    * File w no data in ini file, getFileDetails returns empty array: should be populated w empty fields.
    * PHP: Parse Metadata TO ini file.
    * PHP: Parse Metadata TO database.
    * PHP: Parse Metadata FROM database.
    * PHP: Parse Metadata TO web form.
    * PHP: Parse Metadata FROM web form.
    * PHP: Display ini file contents in inline file view, tagged by type.
    * Single-line string (strip WS, strip HTML, replace \n), fixed length (display chars remaining once close/over).
    * Multi-line string (strip WS, strip HTML), unlimited or fixed length (display chars remaining once close/over).
    * Date
    * User selector (like single line string, but with drop-down hint picker)
    * Location picker (ditto)
    * Keyword picker (ditto)
    * Each with who-can-edit level (nobody, owner, admin, regular, guest)

* FileTemplate
    * https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2%2Ftest1.gif
    * Add next/prev buttons (disappear when editing? Or just prompt to save?)
    * Better formatting for visitor notes (nl2br?).
    * MVP for editing: just "add a comment".
    * Add edit button to change fields to editable.
        * Switch view mode to edit mode on edit button click? All fields edit-on-click? Always editable? Pen by each?
    * Style this template.
        * Display the file, centered, scaled to the window.
        * The various fields, both for display and for editing.
    * How do we visually distinguish inherited data from local data?
        * Is there even a programmatic difference?
        * We don't care about this for now.
        * I think inherited data should be greyed out. Editing it saves locally. Button to go to page of parent/origin?
    * CSS: Make the inline file view look like not ass.
    * The title at the top is mostly useless and takes up space, I think I'll remove it.
    * It would be nice to let the user able to toggle between ice/non-ice if available.
    * Bug: the slide numbers at the end are reversed from how they are in the filename.
    * Bug: The field for "text on the slide" is above the table, rather than in it.

* DropboxManager
    * Split off upload handling/parsing methods to their own class.

* Admin:
    * https://midwestmemories.dewimorgan.com/admin.php?action=list_files_to_download no longer works.
    * Make cursor-ignoring be dynamic, too.
        * Mostly done, need to make the AdminApiTemplate handle that too.
        * Pretty up that template bit: need a styled, scrolling output window.
        * Maybe merge the template and Admin.php together, and get the JS out to a .js file.
    * Move Git pull and log clearing into the admin.php.
    * Investigate cursor init that isn't just always-ignore, and download that isn't just always-download.
        * Detect changes! Dropbox may suck until I can do this. Or hide cursor regen as super-admin.
        * Base it on date (beware timezones)? Checksum?
    * Make updating be triggered by visiting the admin page.
    * If the webhook was working then I shouldn't be able to do a manual update. But I could, and did!
    * I think file downloads are no longer checked against the right folder path. VALID_FILE_PATH_REGEX is unused.
        * SaveFileQueue() probably does it, hardcoded.

* https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 should be https://midwestmemories.dewimorgan.com/Dewi/2
    * (mod_rewrite)
* PHP: Parse form input to database, with validation, errors, etc.
* JS: Parse and display form errors.
* Index: Inline search view.
* ThumbsTemplate: Alt text when displaying images.
* ThumbsTemplate: Display title.
* ThumbsTemplate: Display breadcrumbs?
* ThumbsTemplate: Check width and height when displaying images.
* Some kinda push tech to display error messages through Javascript in Index::ShowError().
* Update the URL as the page changes.
* "Download files from DB queue" and "Process downloaded files" are both giving me:
    * `Cursor='',"Cursor was not set in client."`, but I am not sure if that is even a true error.
    * No reproduction steps yet.

From Code comments:

* Admin: Chain all admin processes up from the web hook handler, using a single timeout time.
* Admin: Maybe have admin processes re-trigger each other or something.
* Admin: Maybe a web cron to hit the webhook? Or does cpanel allow cron jobs? Edit crontab manually?
* `inst-mwm`: Delete `inst-mwm.php`
* Admin: Make Admin.php ShowPage() a template.
* Admin: Wrap InitSession() logging in a connectionLogger.
* Index: Wrap InitSession() logging in a connectionLogger.
* Connection: isBot to use BotSign table.
* Connection: Do something with the ipLookup table.
* Connection: Timestamps with timezone-aware display.
* Connection: Make admin levels more DB-configurable.
* Connection: Ability to register accounts (with authorization)
* Connection: Ability to change passwords
* DropboxManager::processTextFile(): Some processing.
* DropboxManager::convertToJpeg(): How should this be reflected in the DB?

Low priority:

* Prettify the admin page, put the streaming actions into a separate box, show a list of errors needing attention, etc.
* Make ALL admin processing use same dynamic endpoint pattern as downloads and thumbnails.
* Parallelize maybe 4 downloads and thumbnail generations at a time?
* come up with a fun thumbnail image for the website to go in the upper left of the tab.

* TreeTemplate
    * ToDo: Migrate TreeTemplate's JS out to TreeTemplate.js.
    * ToDo: Migrate ScanDirectory out to... maybe Path.php? Its own file?
    * ToDo: Make it accept one or more callbacks to say how to recurse into, skip, or display entries.
    * Why?

* Index: Additional file types (TXT|SVG|BMP|WEBP?).
    * Add a template
    * Edit filters in existing templates.
    * Edit DropBoxManager upload handlers.
* Migrate templates into a sub-folder.
* Files within the mm folder aren't navigable to.
* site.webmanifest file could do with populating properly.
* Allow log level to be specified as a string
* Split a FileProcessor class out from DropboxManager?
* Log rolling.
* It logs the connection twice for each page load, because of the way templates work.
    * Could maybe not log unless `i` is set, or error?
* Create an always-present error-div, that's shown if it has any content. Sorta like an in-page console.
* Handle empty folders.
* ThumbsTemplate: indent HTML lines for file list.
* Dark mode
* MetadataCleaner::cleanNamesInData():
    * Optional param $checkExists to accept only pre-existing names?
    * This is why they're all bundled together, so it can be done in one query.
    * Restrict the characters that can be in a name? Remove `<script>`, etc.

* Identifying people

* Allow clicking people/things in the image to name them, storing the position in the pic as a rectangle.
* OR: allow creation of clickable faces in a pic, maybe manually.
* Highlight the clicked area when you mouseover a name?
* Search for all images of a person when you click their name using face recognition.
* Display images in a search result-set with next/previous.
* Delete files deleted from dropbox?

* Future:

* Future: limit to N items/page.
* Future: Next/prev page pagination controls at top and bottom of page.
* Future: "N items per page" option.
* Future: search box, advanced search link.
    * Clicking names shows table of search results for that name.
    * Clicking locations show nearby images.
    * Clicking dates shows images with a date range search.
* Future: back up db data to dropbox.
* For now, we only need to use dropbox in the admin page, so it should be secure.
* Future: download zip of an album or search result. Let them choose between original images, or jpgs.
* Future: create webhooks for Dropbox to call when the folder is updated.
* Future: upload thumbs and jpgs and updated txt files to dropbox?

See also [CHANGELOG.md](CHANGELOG.md)
