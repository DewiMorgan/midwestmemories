# Project: midwestmemories

This is a small personal project "midwest memories" or "mwm", to create a photo archive for the family.

Users can:

* navigate through the archive's file structure,
* view folder contents as thumbnail lists, and
* view and comment on individual images.

## Project Structure

* The back end is written in PHP + MySQL.
* The front end is written in HTML, CSS, and JS.
* The images are held and deployed through Dropbox.
* Image details and descriptions are stored in an index.txt in the same folder.
* Code deployment is handled by pulling from git.

## File and folder Structure

As well as the usual files that would be in a project root or web root, the midwestmemories project root folder has:

Web request routers:

* index.php - router for all HTML pages, files and images are routed through here.
* api.php - router for all API endpoints.
* admin.php - router for admin interface for user and file management.
* dropboxwebhook.php - router for Dropbox's callback webhook.
* inst-mwm.php - minimalist script to git pull and install the codebase on the server.

Config files:

* DropboxAuth.ini - authentication info for the Dropbox API.
* MySqAuth.ini - authentication info for the MySQL database.
* MidwestMemories.ini - configuration file for the project.
* .htpasswd - "Basic auth" config for inst-mwm to keep it minimalist.
* phpunit.xml - PHPUnit config.

Subfolders:

* /docs - Documentation.
* /docs/schema.sql - SQL schema for the database.
* /midwestmemories - Default thumbnails. Once deployed, all images will also be stored within here.
* /src - All code (additional subfolders should be created to organize).
* /src/Api - API endpoint code.
* /src/Enum - PHP Enum classes.
* /src/JS - JS code.
* /test - All tests. Javascript has no tests yet.
* /test/unit - PHPUnit tests.
* /test/e2e - End-to-end tests.
* /tools - phpunit.phar and any other tools needed.
* /vendor - minimal composer dependencies for vendor/spatie/dropbox-api library. Avoid adding more dependencies!

## Objects

These are concepts relevant to the project, which might end up turning into classes, etc.

* Photo group/album: name, written notes, visitor notes, creation date.
* Photo: date, photographer, location, subjects, written notes, visitor notes, upload date.
* Subject: person or object.
* Object (place or thing): location?, notes, creation date.
* Person: name, relation to family tree, notes, addition date.

## Planned Features

* Sort and search by date, photographer, location, subjects, etc.
* Rename photo subjects: choose to see thumbs of other pics with that person in, with a checkbox to change any of them.
* Edit all fields for a photo.
* All changes audited.
* All changes undoable through admin.

## Manual Dev/Test workflow

1) Open the project in PHPStorm.
2) In Bash, do "service mysql start" if needed. See MySqAuth.ini for auth info.
3) cpanel from https://porkbun.com/account/webhosting/dewimorgan.com -> file manager.
4) https://dewimorgan.com/midwestmemories/inst-mwm.php to run the git pull.
5) https://midwestmemories.family/admin.php to admin it.
6) https://midwestmemories.family/ to view the actual content.

To push a change to the live or test server:

1) Git push in PHPStorm.
2) Git pull in inst-mwm on the server.

What do the commands in the admin page MEAN?

**Initialize root cursor**:
Logs the system into Dropbox, and marks the root cursor as "dirty".
Not normally needed, as it maintains the cursor periodically.
Doing this will make it check all files in the Dropbox folder for updates.

## File change flow

When a photo is added to Dropbox, Dropbox's callback webhook calls DropboxWebhook.php.
This tags the user's Dropbox cursor as "dirty", but doesn't actually download the new files.

When admins log in to admin.php, the admin interface automatically starts downloading and processing any updates.
Maybe in the future this could be automated by DropboxWebhook.php, or a periodic cron.

## User page view - templating flow

Index.php shows the user UI layout, applied from src/TreeTemplate.php: a lefthand bar displays a directory tree.
On the right is shown another templated page, in a "content" div, containing one of:

* src/ThumbsTemplate.php - a directory of file thumbnails.
* src/FileTemplate.php - the details of a single image.
* src/searchTemplate.php - a search form, with search results.
* src/rawTemplate.php - the literal binary content of a file, such as an image for an img tag.

So when displaying a user page, the following code steps are taken:

* /index.php::global - All user page processing starts here. Sets up autoloading, new Index(), and exits.
* src/Index.php::__construct() - Handles session and path.
* src/Index.php::showPage() - `i` param unset means user request: include TreeTemplate.
* src/TreeTemplate.php::global - display left-bar, set an onLoad JS to load body page.
* src/TreeTemplate.php::scanDirectory() - display left-bar, set an onLoad JS to load body page, add drag-bar listeners.
* src/TreeTemplate.php:JS:handleDragBar*() - JS to handle drag-bar resizing.
* src/TreeTemplate.php:JS:openLinkInline() - JS for onLoad and onClick, to load target into "content" div & fix history.
* The JS then makes a request to load the content into the right-hand pane.
* /index.php::global & src/Index.php::__construct() - as above, but with i param set for the subpage.
* src/Index.php::showPage() - `i` param set by JS means internal request: include FileTemplate, ThumbsTemplate, etc.

Templates to be shown in the content div must have exactly one script and one stylesheet in their header.
Both tags are parsed into the header of the parent template (src/TreeTemplate.php) when they are read inline.
