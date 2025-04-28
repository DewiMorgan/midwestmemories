# Project: midwestmemories

Photo archive for the family.

## Current structure

When a change is made in Dropbox, a Dropbox callback hook calls dropboxcallback.php.
This tags the user's cursor as "dirty", but doesn't actually download any updated files.

Admins can log in and manually initiate an update through admin.php.
Maybe in the future this could be automated by dropboxcallback.php or a periodic cron.

Index.php shows the nav, and the image listings.

## What we actually want:

* File structure nav panel of folders.
* Each folder shows all thumbnails and folders within it.
* Each thumbnail can be expanded to full details on that file.
* Full details can be edited.
* Edited details can be saved.
* Images and details are in Dropbox.

## Planned Features

* Get namespaces and autoloading working per PSR.
* Get testing working (again check PSR).
* Hierarchical nav with breadcrumbs.
* Copy from file structure.
* Mirror to/from dropbox.
* Audit trail.
* Access restrict.
* Sort and search by date, photographer, location, subjects, etc.
* Rename photo subjects: choose to see thumbs of other pics with that person in, with a checkbox to change any of them.
* Audited, undoable through admin.

## Objects

* Photo group/album: name, written notes, visitor notes, creation date.
* Photo: date, photographer, location, subjects, written notes, visitor notes, upload date.
* Subject: person or unknown or object.
* Object (place or thing): location?, notes, creation date.
* Person: name, relation to family tree, notes, addition date.
* Unknown: Unique ID, and other pics that specific unknown is in.

## How it should work (Phase 1)

* Initially, params. Later, mod-rewrite.
* path: path to folder. (later, search=search terms)
* In folders: index.txt file (or HTML?) contains default values.

* Display breadcrumb, album notes, and all images as thumbs in a fluid table. Likely need to generate thumbnails.
* Clicking individual images shows image details and the image.
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

## Useful links

* [Dropbox API docs](https://www.dropbox.com/developers/documentation/http/documentation#files-list_folder)
* [GitHub - spatie](https://github.com/spatie/dropbox-api) - the dropbox lib we're using.
* [Getting dropbox auth working](https://github.com/spatie/dropbox-api/issues/94) - see very bottom post.
* [GitHub - NyMedia](https://github.com/nymedia/dropbox-sdk-php) - doesn't work.
* [Github - KuNalVarMa05](https://github.com/kunalvarma05/dropbox-php-sdk) - might do downloads?
* [SO Answer](https://stackoverflow.com/questions/47469142) - download file from Dropbox to local.
* [YouTube playlist](https://www.youtube.com/playlist?list=PLfdtiltiRHWGOceoK3I3LrDL6x8mM0Ipb) (WAY outdated)
    * [Setup](https://www.youtube.com/watch?v=FsQZyNpDWv0)
    * [Upload file](https://www.youtube.com/watch?v=xFM7_1pdiFE)
    * [Download file](https://www.youtube.com/watch?v=2cIlcsrk2nA)
    * [Browsing files/folders](https://www.youtube.com/watch?v=wfb6h9JyhBY)
    * [Text editor](https://www.youtube.com/watch?v=2puV9yXHiAA)
    * [Search filenames](https://www.youtube.com/watch?v=wlB276xVgsw)

## ToDo:

Next:

* Download files added to dropbox.
* Create thumbnails for all files.
* Create jpgs for over-sized pngs.
* Display thumbnails with a click-through to the images.
* Display images in a folder with next/previous.

Then:

* PHP 8 features: readonly properties and promoted properties.
* Display info texts with folders.
* Allow users to add more info texts.
* Allow click people/things to name them, stores position in the pic.
* OR: allow creation of clickable faces in a pic, maybe manually.
* Highlight the clicked area when you mouseover a name?
* Search for all images of a person when you click their name.

Later:

* Display images in a search result-set with next/previous.
* Delete files deleted from dropbox?

## Dev/Test workflow

1) Open the project in PHPStorm EAP (OK, in PHPStorm regular: I went and bought the license).
2) In Bash, do "service mysql start". See MySqAuth.ini for auth info.
3) Browser tab: cpanel from https://porkbun.com/account/webhosting/dewimorgan.com -> file manager.
4) Browser tab: https://dewimorgan.com/midwestmemories/inst-mwm.php to run the git pull.
5) Browser tab: https://midwestmemories.dewimorgan.com/admin.php to admin it.
6) Browser tab: https://midwestmemories.dewimorgan.com/ to view the actual content.

To push a change:

1) Git push in PHPStorm.
2) Git pull in inst-mwm.

What do the commands in the admin page MEAN?

**Initialize root cursor**:
Logs the system into Dropbox, pulling down (and ignoring!) the listing of ALL files.
Not normally needed, as it maintains the cursor periodically.
If this page hangs/times out, after showing just the heading, that's OK. Give it a few minutes, then go to the next.

**Continue initializing the root cursor**: Continues downloading and ignoring that list of files,
If it timed out the first time. You can tell it to start at a certain offset, ignoring the first N files,
but that isn't necessary, and the default is fine.
Again, this is just initialization stuff, should never be needed.

**Get latest cursor updates into the DB**: This is where it gets the list of new files and puts them in the DB.
This is the list of files that have changed since the most last file listed by any of the above three commands.
This should happen automatically from the Dropbox callback. I'm not sure whether it does.

**Download files from DB queue**: This downloads those new files from the previous command.

**Process downloaded files**: Makes thumbnails, publicly publishes the files, etc.

## Templating flow

* /index.php::global - All user page processing starts here. Sets up autoloading, new Index(), and exits.
* app/Index.php::__construct() - Handles session and path.
* app/Index.php::showPage() - `i` param unset means user request: include TreeTemplate.
* app/TreeTemplate.php::global - display left-bar, set an onLoad JS to load body page.
* app/TreeTemplate.php::scanDirectory() - display left-bar, set an onLoad JS to load body page, add drag-bar listeners.
* app/TreeTemplate.php:JS:handleDragBar*() - handle drag-bar resizing.
* app/TreeTemplate.php:JS:openLinkInline() - handle onLoad and onClick, to load target into "content" div & fix history.
* /index.php::global & app/Index.php::__construct() - as above, but for the subpage.
* app/Index.php::showPage() - `i` param set by JS means internal request: include FileTemplate, ThumbsTemplate, etc.

Templates have headers... unsure how it works when JS includes them inline. Styles seem respected?

## Current Issues

See also list at the top of this file.

Current task:

* TreeTemplate
    * ToDo: Set page title. Should be non-fixed.
        * We already have `document.title = e.state.pageTitle;` - why doesn't that work?
    * Child page links lack handlers, so they aren't handling the `&i=1` links well.
    * ToDo: Migrate TreeTemplate's JS out to TreeTemplate.js.
    * ToDo: Migrate ScanDirectory out to... maybe Path.php? Its own file?
    * ToDo: Make it accept one or more callbacks to say how to recurse into, skip, or display entries.
        * Why?

Urgent:

* ThumbsTemplate
    * https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 doesn't fill img names/title from Metadata.
    * In ThumbsTemplate, we need to populate things like $h_pageTitle per instructions in comment at top of that file.
        * The data is stored/read into Metadata. Treat same as FileTemplate
    * Show subfolders as thumbs, too.
        * Ini files don't handle subfolder details. Probably details should come from ini files in subfolders?
    * Clicking images doesn't load them in TreeTemplate, I think because they have a double-leading-slash.

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
        * The various fields, both for display and for edit.
    * How do we visually distinguish inherited data from local data?
        * Is there even a programmatic difference?
        * We don't care about this for now.
        * I think inherited data should be greyed out. Editing it saves locally. Button to go to page of parent/origin?
    * CSS: Make the inline file view look like not ass.

* DropboxManager
    * Split off upload handling/parsing methods to their own class.

* https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 should be https://midwestmemories.dewimorgan.com/Dewi/2
    * (mod_rewrite)
* PHP: Parse form input to database, with validation, errors, etc.
* JS: Parse and display form errors.
* Index: Inline search view.
* replace innerHTML use (mem leaks as doesn't remove handlers for old content; and doesn't run script tags.)
* ThumbsTemplate: Alt text when displaying images.
* ThumbsTemplate: Display title.
* ThumbsTemplate: Display breadcrumbs?
* ThumbsTemplate: Check width and height when displaying images.
* Some kinda push tech to display error messages through Javascript in Index::ShowError().
* Update the URL as the page changes.
* "Download files from DB queue" and "Process downloaded files" are both giving me:
    * Cursor='',"Cursor was not set in client.", but I am not sure if that is even a true error.
    * No reproduction steps yet.

From Code comments:

* Admin: Chain all admin processes up from the web hook handler, using a single timeout time.
* Admin: Maybe have admin processes re-trigger each other or something.
* Admin: Maybe a web cron to hit the webhook? Or does cpanel allow cron jobs? Edit crontab manually?
* `gitpull`: Delete `gitpull.php`
* `inst-mwm`: Delete `inst-mwm.php`
* `phpinfoz`: Delete `phpinfoz.php`
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

* Index: Additional file types (txt? svg|bmp|webp?).
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
* Make all Log methods also echo, like Log::adminDebug(), depending on a config var something like LOG_ADMIN_ECHO_LEVEL.
* Get rid of Log::adminDebug() method. Replace w Log::debug() throughout.
* Create an always-present error-div, that's shown if it has any content. Sorta like an in-page console.
* Handle empty folders.
* ThumbsTemplate: indent HTML lines for file list.
* Dark mode

See also [CHANGELOG.md](CHANGELOG.md)
