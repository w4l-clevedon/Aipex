# Hotspot Group Photograph Feature

## Purpose

Allow a single high-resolution image from a Dropbox folder to display an invisible interactive overlay. Hovering, tapping, or keyboard-focusing a person reveals their name and optional details.

This is designed for school photographs, military groups, sports teams, staff groups, historical societies, weddings, reunions, and archive collections.

## Folder convention

A folder can contain:

- `group-photo.jpg` — source image
- `group-photo.hotspots.json` — preferred structured hotspot data
- `group-photo.docx` — optional legacy source for assisted import
- `group-photo.csv` — optional simple import format

The JSON file is authoritative once created. Word documents are import sources only and should not be parsed on every page load.

## Recommended data model

Each person is stored with normalised coordinates so the overlay remains aligned at every responsive image size.

```json
{
  "version": 1,
  "image": "group-photo.jpg",
  "people": [
    {
      "id": "person-001",
      "name": "John Smith",
      "row": 5,
      "position_from_left": 6,
      "x": 0.642,
      "y": 0.533,
      "width": 0.035,
      "height": 0.082,
      "role": "Sergeant",
      "notes": "Optional archive notes",
      "person_url": ""
    }
  ]
}
```

Coordinates are proportions from 0 to 1 rather than pixels.

## Better workflow than a fixed grid

A fixed invisible row-and-column grid is useful for initial placement, but faces are rarely spaced evenly. The recommended workflow is:

1. Import row and position data from DOCX or CSV.
2. Generate an initial estimated grid automatically.
3. Open the image in a WordPress hotspot editor.
4. Drag each hotspot over the correct face.
5. Resize the hotspot if needed.
6. Save the final coordinates to WordPress and optionally export JSON back to Dropbox.

This preserves the existing row-based source information while producing accurate responsive hotspots.

## Elementor widget

### Aipex Hotspot Group Photograph

Controls:

- Select Dropbox gallery
- Select source image
- Select hotspot data source
- Tooltip trigger: hover, click, or both
- Tooltip fields: name, role, row, notes
- Show visible markers: never, hover only, always
- Marker style and size
- Enable search by person name
- Highlight matching person
- Enable lightbox
- Enable print purchase
- Purchase whole photograph or cropped individual portrait
- Public viewing with logged-in purchase restriction

## Accessibility

Every hotspot must be keyboard-focusable and expose the person name through accessible labels. On touch devices, tapping opens the tooltip. The feature must not depend on hover alone.

## Purchase behaviour

The widget may support:

- Purchase the complete group photograph
- Purchase a print with one person highlighted
- Purchase a crop centred around one person
- Add the selected person name to WooCommerce cart and order metadata

## Import strategy

### Preferred formats

1. JSON
2. CSV
3. DOCX assisted import

Example CSV:

```csv
name,row,position_from_left,role,notes
John Smith,5,6,Sergeant,
Jane Brown,3,2,Corporal,
```

DOCX import should extract names and row descriptions into a draft list. The administrator then verifies and positions them visually before publication.

## MVP scope

- Single image Elementor widget
- Manual hotspot editor
- Name tooltip
- Responsive normalised coordinates
- JSON and CSV import/export
- Logged-in-only purchase option
- Whole-image WooCommerce purchase

## Later phases

- DOCX automatic extraction
- Face detection to suggest hotspot positions
- Search and jump to person
- Individual face crop products
- Person archive pages
- Multiple photographs linked to the same person
- AI-assisted identity matching with mandatory human confirmation
