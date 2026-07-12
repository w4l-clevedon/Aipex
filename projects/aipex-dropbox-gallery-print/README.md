# Aipex Dropbox Gallery & Print

Version 0.2.0 MVP foundation.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Elementor
- WooCommerce for print purchasing
- A Dropbox API app with scoped access

## Dropbox app setup

1. Create an app in the Dropbox App Console.
2. Select Scoped access.
3. Choose App folder or Full Dropbox according to the folders the site must access.
4. Enable these scopes:
   - `files.metadata.read`
   - `files.content.read`
5. Install and activate this plugin.
6. Open **Dropbox Galleries > Dropbox Settings**.
7. Copy the displayed OAuth redirect URI into the Dropbox app's Redirect URIs list.
8. Enter the Dropbox App key and App secret, then save.
9. Click **Connect Dropbox** and approve access.

The refresh token, access token and app secret are encrypted before being stored in WordPress options.

## Creating a gallery

1. Open **Dropbox Galleries > Add New**.
2. Enter a gallery title.
3. Enter the Dropbox folder path, for example `/Galleries/Event Name`.
4. Choose the default sorting order.
5. Enter the WooCommerce print product ID.
6. Choose whether anyone or logged-in customers may purchase.
7. Publish the gallery.
8. Click **Sync now**.

The plugin imports JPG, JPEG, PNG and WebP files as Gallery Photo records and caches display previews under:

`wp-content/uploads/aipex-dropbox-gallery/`

The original Dropbox file ID and path remain attached to the photo and order records.

## Synchronisation

- Initial sync uses Dropbox `files/list_folder`.
- Subsequent syncs use the stored Dropbox cursor and `files/list_folder/continue`.
- Renamed files retain their Dropbox file identity.
- Deleted files are changed to draft rather than permanently removed.
- WordPress cron runs an incremental sync hourly.
- **Full resync** clears normal incremental assumptions and lists the folder again.

## Hotspot companion files

Place a companion file next to its photograph:

- `group-photo.jpg`
- `group-photo.hotspots.json`

or:

- `group-photo.jpg`
- `group-photo.hotspots.csv`

### JSON

```json
{
  "version": 1,
  "image": "group-photo.jpg",
  "people": [
    {
      "name": "John Smith",
      "role": "Sergeant",
      "row": 5,
      "position_from_left": 6,
      "x": 0.642,
      "y": 0.533,
      "width": 0.035,
      "height": 0.082,
      "notes": "Optional notes"
    }
  ]
}
```

### CSV

```csv
name,role,row,position_from_left,x,y,width,height,notes
John Smith,Sergeant,5,6,0.642,0.533,0.035,0.082,Optional notes
```

Coordinates use proportions from 0 to 1 so hotspots remain aligned responsively.

## Elementor widgets

- **Aipex Dropbox Masonry Gallery**
- **Aipex Hotspot Group Photograph**

The masonry widget uses Elementor's lightbox and links each image to the configured WooCommerce print product.

## Current MVP limitations

- The folder path is entered manually; the API endpoint for folder browsing exists but the visual browser interface is not yet wired into the gallery editor.
- Cached previews are used for lightbox display; production-resolution originals remain in Dropbox.
- The visual drag-and-drop hotspot editor is not yet implemented. JSON and CSV coordinates are currently imported directly.
- DOCX hotspot extraction is specified but not yet implemented.
- Dropbox webhooks are not yet implemented; hourly cron and manual sync are active.
