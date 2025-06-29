# Changelog

Newest items at top. The date indicates when they were removed from the README's ToDo-list to this file,
which might not be when they were actually implemented/committed.

## 2025/06/29 Fri



## 2025/06/28 Fri

* DONE: Get API tests working.
* DONE: Add PHPUnit configuration.
* DONE: Add PHPUnit tests for User.
* DONE: Add PHPUnit tests for Singleton.
* DONE: Add TestHelper class to handle testing APIs
* DONE: Add PHPUnit tests for TestHelper.
* DONE: Add PHPUnit tests for APIs.
* DONE: Add a Users class to represent the new database-stored users.
* DONE: Update Connection class to use the Users class when logging/validating connections.
* DONE: Add new Auth to admin page.
* DONE: Add login form as a template.
* DONE: Significant template cleanup and consolidation, to use the same CSS, JS, etc.
* DONE: Create `/Templates/` folder and move templating HTML out from the PHP code and into templates.
* DONE: Create `/Css/` and `/Js/` folders for their respective languages.
* DONE: Create `/raw/` folder for CSS, JS, icons... to prevent log spam, dropbox conflicts, etc.
* DONE: Update .htaccess to give direct access to files in `/raw/`.
* DONE: Future: a compile step for JS.
* DONE: Compile (well, consolidate) admin files from `/Css/` and `/Js/` to `/raw/admin.css` and `/raw/admin.js`
* DONE: Compile user files from `/Css/` and `/Js/` to `/raw/user.css` and `/raw/user.js`
* DONE: Do that compilation automatically before each commit, so I don't forget.
* DONE: Change templates to use the compiled files from the `/raw/` folder.
* DONE: JS is probably broken. Modify AdminApiTemplate to include all JS.
* DONE: Move Javascript logging to its own class.
* DONE: Rename `/src/Admin.php` to `/src/AdminGateway.php`, avoiding name conflict with `/admin.php`.
* DONE: Rename `/src/Index.php` to `/src/IndexGateway.php`, avoiding name conflict with `/index.php`.
* DONE: Rename `/app/` folder to `/src/` folder.
* DONE: API to be a separate file.
* DONE: Rewrite the APIs to go through a single /api.php/ file and use a consistent format.
* DONE: Clean up the API calls in the JS.
* DONE: Add response types to EndpointRegistry.
* DONE: Strip out old API handling.
* DONE: Utterly ridiculous amounts of debugging to track down all the issues with APIs.
* SKIPPED: Move source out of web root (edit autoloader?).
* DONE: Convert user management to session-and-DB based.

## 2025/06/20 Sat

* Items moved over from README's "How it should work (Phase 1)" section:
    * Initially, params. Later, mod-rewrite.
    * path: path to folder. (later, search=search terms)
    * In folders: index.txt file (or HTML?) contains default values.
    * Display breadcrumb, album notes, and all images as thumbs in a fluid table. Likely need to generate thumbnails.
    * Clicking individual images shows image details and the image.
* Items moved over from README's "Planned Features" section:
    * DONE: Get namespaces and autoloading working per PSR.
    * DONE: Get testing working (again check PSR).
    * DONE: Hierarchical nav with breadcrumbs.
    * DONE: Copy from file structure.
    * DONE: Mirror to/from dropbox.
    * Access restrict.

## 2025/06/07 Sat

* DONE: I broke file downloads from dropbox.
* DONE: Editing a user's password no longer shows it as edited until you reload.
* DONE: Adding a new user no longer works - wrong confirm prompt. Doesn't add user.

## 2025/06/01 Sun

* DONE: Show a current username on admin page.
* DONE: Make messages window limited in height, with optional autoscroll.
* DONE: Change Dropbox cursor management to make sense: either "read latest" or "read full list".
* DONE: Make setNewCursor and sqlExec return ID & rows changed.
* DONE: Make SaveFileQueue handle dup keys
* DONE: Make SaveFileQueue use the file's hash to see if a record needs updating.
* DONE: Add more syscall return validation.
* DONE: Fix FileTemplate missing array indexes.
* DONE: Move dropbox_path_prefix to ini file.
* DONE: Add `file_hash` to db schema.
* SKIPPED: can't reproduce. Undefined array key "/" in MetadataCleaner.php on line 53.

## 2025/05/29 Thu

* DONE: Improve admin UI: Always show user list, always show file details.
* DONE: Sectionize admin page UI.
* DONE: Clean up admin page logic.
* DONE: Rm obsolete cursor code from admin page.
* DONE: Convert Conf to singleton.
* DONE: Add Singleton class, inherited by Conf, DropboxManager, UserManager, Connection & Db.

## 2025/05/27 Tue

* DONE: Api to disable users. (just edit with a special/invalid password?)
* DONE: JS to edit users.
* DONE: JS to create users.
* DONE: JS to disable users.

## 2025/05/26 Mon

* DONE: Overhaul JS to manipulate users.

## 2025/05/25 Sun

* DONE: Move it to its own domain.
* DONE: Move to new domain name.
* DONE: Api to list users.
* DONE: Api to edit users.
* DONE: Api to create users.
* DONE: JS to list users.
* DONE: Hardcoded values into a config file.
* DONE: Centralize the blacklist/whitelist.
* DONE: Connection class to a singleton instead of a global var.
* DONE: Delete a whole lotta debugging output from the pages and logs.

## 2025/05/16 Fri

* DONE: API to add comments.
* DONE: Javascript to add comments.

## 2025/05/14 Wed

* FIXED: JS on loaded templates doesn't run!
* FIXED: Javascript to list comments.

## 2025/05/13 Tue

* DONE: Set up the DB for comments.
* DONE: Write API for listing comments.

## 2025/05/10 Sat

* DONE: Download files added to dropbox.
* DONE: Create thumbnails for all files.
* DONE: Create jpgs for over-sized pngs.
* DONE: Display thumbnails with a click-through to the images.
* DONE: Add laptop dev env.
* DONE: `mod_rewrite` to get nicer URLs working.
* DONE: Rename AdminDownloadTemplate to AdminApiTemplate.
* DONE: Get thumb view working.
* DONE: show filenames in thumbsTemplate.
* DONE: Thumb images shouldn't be sized.
* FIXED: Thumb images aren't in wrapped lines.
* FIXED: Clicked thumbs don't load in TreeTemplate, maybe due to a double-leading-slash?
* DONE: Show subfolders as thumbs, too.
* DONE: Subfolder thumbs should select the folder in the tree.
* DONE: Subfolder thumbs should expand the folder in the tree, if not expanded.
* FIXED: When first displaying, all open folders are '(+)', should be '(-)'.
* DONE: Add nicer collapse/expand icons for folders in treeview.
* FIXED: Bug: Right-clicking "open image in a new tab" doesn't work.
* SKIPPED: If no thumbnail, show the real image, but sized down. (Won't fix, too slow, not needed).
* FIXED: CSS of templated files being ignored.
* SKIPPED: Make all Log methods also echo, depending on a config var like LOG_ADMIN_ECHO_LEVEL. (too flaky)
* DONE: Get rid of Log::adminDebug() method. Replace w Log::debug() throughout.
* FIXED: Thumb images no longer show, instead showing as src="PATH ERROR 404"
* DONE: "PATH_ERROR_404" appears as "PATH ERROR 404" in links, complicating debugging. Rename to "PATH-ERROR-404"
* FIXED: Going to URLs doesn't load the folder, just shows "Hello, world!"
* FIXED: Clicking folders doesn't change the folders on the left-bar.

## 2025/05/05 Mon

* DONE: Add correct headers for images, so they can be opened in a new tab.
* DONE: Delete the old code for thumbs and downloads.
* DONE: Debug the dynamic downloading and thumbnail generation.
* DONE: Get it not to bother thumbing the -ICE.jpg filtered versions (manually delete the thumbnails for now).
* DONE: Check for quirks mode.
* DONE: Webhook just displays an error to normal humans.

## 2025/05/04 Sun

* DONE: Universal dates in `CHANGELOG.md`
* DONE: Thumbnails are slow, and UI is unresponsive during it.
* DONE: Replace with a responsive clientside way of doing it, adding two endpoints:
* DONE: "get list of files needing thumbs" endpoint;
* DONE: "process one thumb" endpoint (this also solves the timeout problem as we only ever process one);
* DONE: Call those with JS;
* DONE: Make Downloads handled on the same page, going first.
* FIXED: Files aren't thumb-nailing.
* DONE: Get logging to be better (call stack info on each line).
* DONE: Add counter for the files processed on admin page.

## 2025/04/27 Sun

* DONE: Clean up: move all debug to the bottom below a `HR` tag.
* DONE: Strict typing everywhere!
* DONE: Parse info texts.
* DONE: Display info texts with images.
* DONE: Understand and document how templating works.
* DONE: Should probably bold the selected item.
* FIXED: Child elements should not be bolded.
* FIXED: It doesn't show the selected files, nor the other files in its folder, on the left. The folder loads collapsed.
* FIXED: When you select a file, it collapses the current folder.
* FIXED: Stop the thumbnails from listing in TreeTemplate.
* FIXED: Stop the forbidden files like index.txt and index.txt.bak from listing in TreeTemplate.
* DONE: Expand to, and select, the current passed $path.
* DONE: Inline file view
* FIXED: We also need to UN-bold selected items, and bold NEW ones, as they are clicked.
* FIXED: Back button doesn't work.
* FIXED: Drag bar does not work. Changes cursor, but no drag.
* DONE: Drag bar may not persist when navigating back/forth. Check.
* FIXED: Child page links lack handlers, so they aren't handling the `&i=1` links well.
* DONE: Set page title. Should be non-fixed.
* DONE: replace innerHTML use (mem leaks as doesn't remove handlers for old content; and doesn't run script tags.)
* DONE: Delete `gitpull.php`
* DONE: Delete `phpinfoz.php`
* DONE: Get the files down. (done so far)
* DONE: Update composer files.
* DONE: Keep huge images visible.
* DONE: Center images.
* DONE: It would be nice to hide the ICE images.
* DONE: Autogenerate the ini files from the new files.

## 2025/04/26 Sat

* FIXED: Get FileTemplate to populate fully with readonly text from Metadata.
    * FIXED: Convert the dump to real output fields.
* FIXED: Add an image alt text (the name? Description?).
* https://midwestmemories.dewimorgan.com/?path=%2FDewi doesn't show the subfolder "2". (edit: fixed? Works for me!)

## Before

* FIXED: Change FileTemplate page title from "Folder Navigation".
* FIXED: ThumbsTemplate was not ignoring the right files.
* FIXED - https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 doesn't fill out the right hand side ("hello world").
    * Just had to populate the ONLOAD call.
* FIXED: Index: Clicking links seems broken, they don't open inline.
    * Reproduction steps:
        * Go to the index (https://midwestmemories.dewimorgan.com/).
        * Click "Dewi". It should open in the pane to the right, but opens in full page.
        * This is handled serverside by `index.php:showPage()`, and clientside by TreeTemplate.php:openLinkInline(url)
        * This typically breaks when there's a 500 error somewhere.
* FIXED: Bug: ini params with spaces are not read in correctly.
* FIXED: PHP: Parse Metadata FROM ini file.
* FIXED: Convert existing DBs to InnoDB, locally and remotely.
* FIXED: DB: Add an index to midmem_file_queue.full_path.
* FIXED: DB: Create rest of schema.
* FIXED: The admin page may be broken.
* FIXED: Log class is not logging.
* FIXED: back button doesn't populate the page correctly (doesn't parse path=...).
* FIXED: Need to change expand/collapse to be a style/class thing, so we can set the style when building the list.
* FIXED: Reloading page doesn't repopulate correctly.
* FIXED: "Span is null" error when clicking "Home". Probably any empty/root folder.
* FIXED: CSS-based folding is not working.
* FIXED: Get rid of (ideally, FIX) all code warnings. They just slow me down.
* FIXED: TreeTemplate: Expand to, and select, the current passed $path.
* FIXED: isOnTargetPath() - write this, though I've likely already got a similar class.
* FIXED: Migrate the path manipulation methods from Index to their own class.
* FIXED: Need a link to home at the top of tree-view template.
* FIXED: ThumbsTemplate: Folders first.
* FIXED: ThumbsTemplate: break HTML lines for file list.
* FIXED: back button doesn't populate the page correctly (unnecessary i=1).
* FIXED: Update browser history when navigating.
* FIXED: Db::mkRefArray(): There's apparently a `...` operator that makes this kludge redundant: see man page.
* FIXED: The ThumbTemplate doesn't fill out - maybe no suitable files with thumbs?
* FIXED: Fifth argument ($port) must be of type ?int, string given in `.../public_html/midwestmemories/src/Db.php:41`.
* FIXED: index.php double-loads the tree template.
* FIXED: Content div has it as a class but not an ID.
* FIXED: OpenLinkInline doesn't seem to do so. I had the wrong classnames.
* FIXED: Create a config file for non-secret info.
* FIXED: Read auth info through the config class.
* FIXED: Unify pre-existing logging (as in Db class) to use Log class.
* FIXED: Refactor dropbox webhook to a class, and move the class into the src/ folder.
* FIXED: Create a simple static logger class. Log::error($str), etc.
* FIXED: Create a simple static config class. `Conf::get(Conf::LOG_LEVEL)`, etc.
* FIXED: DropboxManager has some very poor naming. `dbm.iterations` and `dbm.extracted` need renaming.
* FIXED: The main page doesn't load.
* FIXED: Remove logging from autoloader.

See also [CHANGELOG.md](CHANGELOG.md)
