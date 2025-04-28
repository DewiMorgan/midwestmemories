# Changelog

Newest items at top. The date indicates when they were removed from the README's ToDo-list to this file,
which might not be when they were actually implemented/committed.

## 4/27/2025

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
* DONE: Expand to, and select, currently passed $path.
* DONE: Inline file view
* FIXED: We also need to UN-bold selected items, and bold NEW ones, as they are clicked.
* FIXED: Back button doesn't work.

## 4/26/2025

* FIXED: Get FileTemplate to populate fully with readonly text from Metadata.
    * FIXED: Convert the dump to real output fields.
* FIXED: Add image alt text (the name? Description?).
* https://midwestmemories.dewimorgan.com/?path=%2FDewi doesn't show the subfolder "2". (edit: fixed? Works for me!)

## Before

* FIXED: Change FileTemplate page title from "Folder Navigation".
* FIXED: ThumbsTemplate wasn't ignoring the right files.
* FIXED - https://midwestmemories.dewimorgan.com/?path=%2FDewi%2F2 doesn't fill out the right hand side ("hello world").
    * Just had to populate the ONLOAD call.
* FIXED: Index: Clicking links seems broken, they don't open inline.
    * Reproduction steps:
        * Go to the index (https://midwestmemories.dewimorgan.com/).
        * Click "Dewi". It should open in the pane to the right, but opens in full page.
        * This is handled serverside by index.php:showPage(), and clientside by TreeTemplate.php:openLinkInline(url)
        * This typically breaks when there's a 500 error somewhere.
* FIXED: Bug: ini params with spaces are not read in correctly.
* FIXED: PHP: Parse Metadata FROM ini file.
* FIXED: Convert existing DBs to InnoDB, locally and remotely.
* FIXED: DB: Add index to midmem_file_queue.full_path.
* FIXED: DB: Create rest of schema.
* FIXED: The admin page may be broken.
* FIXED: Log class is not logging.
* FIXED: back button doesn't populate page correctly (doesn't parse path=...).
* FIXED: Need to change expand/collapse to be a style/class thing, so we can set the style when building the list.
* FIXED: Reloading page doesn't repopulate correctly.
* FIXED: "Span is null" error when clicking "Home". Probably any empty/root folder.
* FIXED: CSS-based folding is not working.
* FIXED: Get rid of (ideally, FIX) all code warnings. They just slow me down.
* FIXED: TreeTemplate: Expand to, and select, currently passed $path.
* FIXED: isOnTargetPath() - write this, though I've likely already got a similar class.
* FIXED: Migrate the path manipulation methods from Index to their own class.
* FIXED: Need a link to home at the top of tree-view template.
* FIXED: ThumbsTemplate: Folders first.
* FIXED: ThumbsTemplate: break HTML lines for file list.
* FIXED: back button doesn't populate page correctly (unnecessary i=1).
* FIXED: Update browser history when navigating.
* FIXED: Db::mkRefArray(): There's apparently a `...` operator that makes this kludge redundant: see man page.
* FIXED: The ThumbTemplate doesn't fill out - maybe no suitable files with thumbs?
* FIXED: Argument #5 ($port) must be of type ?int, string given in .../public_html/midwestmemories/app/Db.php:41
* FIXED: index.php double-loads the tree template.
* FIXED: Content div has it as a class but not an ID.
* FIXED: OpenLinkInline doesn't seem to do so. I had the wrong classnames.
* FIXED: Create a config file for non-secret info.
* FIXED: Read auth info through the config class.
* FIXED: Unify pre-existing logging (as in Db class) to use Log class.
* FIXED: Refactor dropboxcallback to a class, and move the class into the app/ folder.
* FIXED: Create a simple static logger class. Log::error($str), etc.
* FIXED: Create a simple static config class. Conf::get(Conf::LOG_LEVEL), etc.
* FIXED: DropboxManager has some very poor naming. `dbm.iterations` and `dbm.extracted` need renaming.
* FIXED: Main page doesn't load.
* FIXED: Remove logging from autoloader.
