# Project: midwestmemories

Photo archive for the family.

##

Current structure:

When a change is made in Dropbox, a Dropbox callback hook calls dropboxcallback.php.
This tags the user's cursor as "dirty", but doesn't actually download any updated files.

Admins can log in and manually initiate an update through admin.php.
Maybe in the future this could be automated by dropboxcallback.php or a periodic cron.

Index.php shows the nav, and the image listings.

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

* [API docs](https://www.dropbox.com/developers/documentation/http/documentation#files-list_folder)
* [GitHub - spatie](https://github.com/spatie/dropbox-api) - the one we're using.
* [Getting auth working](https://github.com/spatie/dropbox-api/issues/94) - see the very bottom post.
* [GitHub - NyMedia](https://github.com/nymedia/dropbox-sdk-php) - doesn't work.
* [Github - KuNalVarMa05](https://github.com/kunalvarma05/dropbox-php-sdk) - might do downloads, see
  [SO Answer](https://stackoverflow.com/questions/47469142/how-can-i-download-file-to-local-directly-from-dropbox-api)
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

* PHP 8 features: readonly properties and promoted properties. Strict typing everywhere!
* Parse info texts.
* Display info texts with folders and images.
* Allow users to add more info texts.
* Allow click people/things to name them, stores position in the pic.
* OR: allow creation of clickable faces in a pic, maybe manually.
* Highlight the clicked area when you mouseover a name?
* Search for all images of a person when you click their name.

Later:

* Display images in a search result-set with next/previous.
* Delete files deleted from dropbox?

## Test workflow

I've been avoiding working on this as it became a bigger and bigger
molehill-mountain in my head, partly because I forget the dev workflow. So here are the steps:

1) Open the project in PHPStorm EAP (OK, in PHPStorm regular: I went and bought the license).
2) In Bash, do "service mysql start". See MySqAuth.ini for auth info.
3) Browser tab: cpanel from https://porkbun.com/account/webhosting/dewimorgan.com -> file manager.
4) Browser tab: https://dewimorgan.com/midwestmemories/inst-mwm.php to run the git pull.
5) Browser tab: https://midwestmemories.dewimorgan.com/admin.php to admin it.
6) Browser tab: https://midwestmemories.dewimorgan.com/ to view the actual content.

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

To push a change:

1) Git push in PHPStorm.
2) Git pull in inst-mwm.

## Current Issues

Urgent:

* OpenLinkInline doesn't seem to do so. Steps to reproduce:
    * None yet.
* [Download files from DB queue] and [Process downloaded files]are both giving me:
    * Cursor='',"Cursor was not set in client.", but I am not sure if that is even a true error.
    * No reproduction steps yet.
* Index: Inline file view
* Index: Inline search view

From ToDos:

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
* Db::mkRefArray(): There's apparently a `...` operator that makes this redundant: see man page.
* DropboxManager::processTextFile(): Some processing.
* DropboxManager::convertToJpeg(): How should this be reflected in the DB?
* ThumbsTemplate: Folders first.
* ThumbsTemplate: Alt text when displaying images.
* ThumbsTemplate: Check width and height when displaying images.
* TreeTemplate: Expand to, and select, currently passed $path.
* TreeTemplate: Make it accept one or more callbacks to say how to recurse into, skip, or display entries.

Low priority:

* Allow log level to be specified as a string
* Split a FileProcessor class out from DropboxManager?
* Log rolling.
* It logs the connection twice for each page load, but needn't.
* Make all Log methods also echo, like Log::adminDebug(), depending on a config var something like LOG_ADMIN_ECHO_LEVEL.
* Get rid of Log::adminDebug() method. Replace w Log::debug() throughout.

Fixed:

* FIXED: Create a config file for non-secret info.
* FIXED: Read auth info through the config class.
* FIXED: Unify pre-existing logging (as in Db class) to use Log class.
* FIXED: Refactor dropboxcallback to a class, and move the class into the app/ folder.
* FIXED: Create a simple static logger class. Log::error($str), etc.
* FIXED: Create a simple static config class. Conf::get(Conf::LOG_LEVEL), etc.
* FIXED: DropboxManager has some very poor naming. `dbm.iterations` and `dbm.extracted` need renaming.
* FIXED: Main page doesn't load.
* FIXED: Remove logging from autoloader.
