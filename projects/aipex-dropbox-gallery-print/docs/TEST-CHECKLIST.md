# MVP validation checklist

## Activation

- [ ] Plugin activates on PHP 8.1+ without a fatal error.
- [ ] Dropbox Galleries and Gallery Photos appear in WordPress admin.
- [ ] Hourly `aipex_dgp_scheduled_sync` event is registered.

## Dropbox OAuth

- [ ] App key and secret save successfully.
- [ ] App secret, refresh token and access token are encrypted in the options table.
- [ ] OAuth callback rejects an invalid state.
- [ ] OAuth callback stores a refresh token.
- [ ] Expired access token refreshes automatically.
- [ ] Disconnect removes stored Dropbox account tokens.

## Synchronisation

- [ ] Initial folder sync imports supported images.
- [ ] A second sync uses the cursor and does not duplicate photos.
- [ ] New image is added.
- [ ] Updated image refreshes its cached preview.
- [ ] Renamed image retains its Dropbox ID.
- [ ] Deleted image is moved to draft.
- [ ] Unsupported files are ignored.
- [ ] Cached thumbnails are written below the WordPress uploads directory.
- [ ] Full resync completes successfully.
- [ ] Hourly cron syncs all published galleries.

## Hotspot files

- [ ] Matching `.hotspots.json` imports into the photograph.
- [ ] Matching `.hotspots.csv` imports into the photograph.
- [ ] Companion file imported before the image is applied when the image later syncs.
- [ ] Normalised hotspot coordinates remain aligned responsively.
- [ ] Hotspots work with mouse hover and keyboard focus.

## Elementor

- [ ] Masonry widget appears in Elementor.
- [ ] Gallery selection lists published galleries.
- [ ] Responsive column controls work.
- [ ] Elementor lightbox opens.
- [ ] Hotspot photograph widget appears.
- [ ] Purchase button includes the selected photo ID.

## WooCommerce

- [ ] Public visitors can view galleries.
- [ ] Logged-in-only gallery redirects purchase attempts to My Account.
- [ ] The requested product URL is preserved for post-login return.
- [ ] Selected photograph appears in cart item data.
- [ ] Selected photograph and Dropbox file ID are saved to the order line item.
