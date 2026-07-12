# Architecture

## Core entities

- `aipex_gallery`: Dropbox folder configuration, sorting, WooCommerce product and access policy.
- `aipex_photo`: Stable Dropbox file identity, cached preview, dimensions, gallery relation and hotspot data.

## Dropbox authentication

The plugin uses Dropbox OAuth 2 with offline access. The refresh token, short-lived access token and app secret are encrypted using libsodium when available, with an authenticated OpenSSL fallback.

## Sync lifecycle

1. Initial sync calls `files/list_folder`.
2. Pagination continues through `files/list_folder/continue`.
3. The returned cursor is stored against the gallery.
4. Incremental sync starts with that cursor.
5. Stable Dropbox file IDs prevent duplicates when files are renamed.
6. Deleted files are retained as draft records for order-history integrity.
7. Supported image previews are cached locally for gallery delivery.
8. Original files remain in Dropbox and are referenced by stable ID and path.

## Scheduling

Published galleries are synchronised hourly through WordPress cron. Administrators can also trigger incremental or full sync from the gallery editor.

## Hotspot companions

Companion JSON or CSV files are matched by basename:

- `group-photo.jpg`
- `group-photo.hotspots.json`

The companion may arrive before or after the image; pending hotspot data is stored against the gallery until the matching photograph exists.

## Commerce

A gallery points to a shared WooCommerce print product. The selected `aipex_photo` ID and Dropbox ID are carried through cart and order line metadata. Gallery visibility remains public while purchasing can require authentication.
