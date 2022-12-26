# Project: midwestmemories

Photo archive for the family

## Planned Features

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
* In folders: readme.md file (or HTML?) contains default values.
  (Future: Ones marked with "ed" should be editable and overridable in the DB.)
    * Name:
    * Written Notes: whatever notes were saved with it.
    * [ed] Visitor Notes:
    * all-date: defaults for all album pictures.
      (Future: If these are set, and someone edits one pic's property, ask whether to update for all in the folder,
      with thumbs and checkboxes to select/unselect)
    * all-photographer:
    * all-location:
    * all-subjects:
    * all-written notes:
    * all-visitor notes:
    * [ed] foo-bar.jpg-date:
    * [ed] foo-bar.jpg-photographer:
    * [ed] foo-bar.jpg-location:
    * [ed] foo-bar.jpg-subjects:
    * [ed] foo-bar.jpg-written notes:
    * [ed] foo-bar.jpg-visitor notes:

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
* Future: upload thumbs and jogs to dropbox?

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
