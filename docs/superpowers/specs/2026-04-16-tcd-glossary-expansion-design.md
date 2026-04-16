# TCD Glossary Plugin Expansion — Design Spec

## Overview

Expand the TCD Glossary plugin from a simple shortcode into a full-featured Elementor widget with admin settings, frontend taxonomy filtering, full style controls, and GitHub-based auto-updates.

The plugin **consumes** existing CPTs and taxonomies (does not register its own). Users configure which CPT and taxonomy to use via a settings page.

## File Structure

```
tcd-glossary/
├── tcd-glossary.php                        # Bootstrap, constants, loader
├── readme.txt                              # WordPress readme
├── includes/
│   ├── class-tcd-glossary.php              # Core orchestrator (loads modules, shortcode)
│   ├── class-tcd-settings.php              # Settings page (Settings → TCD Glossary)
│   ├── class-tcd-query.php                 # Shared query logic (shortcode + widget + AJAX)
│   ├── class-tcd-updater.php               # GitHub release update checker
│   └── elementor/
│       ├── class-tcd-elementor.php         # Registers "TCD" widget category + widget
│       └── class-tcd-glossary-widget.php   # Elementor widget with content + style controls
├── assets/
│   ├── css/
│   │   └── tcd-glossary.css                # Frontend styles
│   └── js/
│       └── tcd-glossary.js                 # Frontend taxonomy filtering (AJAX)
└── templates/
    └── glossary.php                        # Shared HTML template (shortcode + widget)
```

## Settings Page

- **Location:** Settings → TCD Glossary
- **Implementation:** WordPress Settings API (`register_setting`, `add_settings_section`, `add_settings_field`)
- **Option name:** `tcd_glossary_settings`

### Fields

| Field | Type | Source | Default |
|-------|------|--------|---------|
| Post Type | Dropdown | `get_post_types(['public' => true])` | Empty (must configure) |
| Taxonomy | Dropdown | `get_taxonomies(['public' => true])` | Empty |

The shortcode reads from these settings. The Elementor widget reads them as defaults but can override per instance.

## Elementor Integration

### Loading

`class-tcd-elementor.php` is only loaded when Elementor is active (`did_action('elementor/loaded')`). It registers the "TCD" widget category and the glossary widget.

### Widget: Content Controls

| Control | Type | Notes |
|---------|------|-------|
| Post Type | Select | Defaults to settings page value |
| Taxonomy | Select | Defaults to settings page value |
| Default taxonomy term | Select | Populated from chosen taxonomy |
| Show A-Z navigation | Toggle | Default: on |
| Show taxonomy filter | Toggle | Default: off |
| Filter style | Select | Pills or Dropdown. Visible only when taxonomy filter is enabled |

### Widget: Style Controls

| Section | Controls |
|---------|----------|
| Container | Max width, padding, background color, border type/color/radius, box shadow, custom CSS class |
| A-Z Navigation | Background, border, border-radius, padding, sticky toggle, typography, active color, hover color, disabled color |
| Taxonomy Filter | Typography, background, active background, text color, active text color, border-radius, gap, padding |
| Letter Headings | Typography, color, border-bottom color/width, margin/padding |
| Term Titles | Typography, color, margin |
| Term Definitions | Typography, color |
| Empty State | Background, border, typography, text color, padding |

All style controls use Elementor's native control types (`Group_Control_Typography`, `Group_Control_Border`, `Group_Control_Box_Shadow`, color pickers, sliders) for responsive and state support.

## Shared Query Logic (`class-tcd-query`)

Centralizes all WP_Query logic. Both the shortcode and Elementor widget call it with the same interface:

```php
TCD_Query::get_grouped_terms( $post_type, $taxonomy, $term_slug = '' )
```

- Queries all published posts of the given CPT
- Optionally filters by taxonomy term
- Returns an array grouped by first letter: `['A' => [WP_Post, ...], 'B' => [...]]`

Also provides the AJAX handler (`wp_ajax_tcd_glossary_filter` / `wp_ajax_nopriv_tcd_glossary_filter`) for frontend filtering.

## Shared Template (`templates/glossary.php`)

Receives parameters and renders the full glossary HTML:

| Parameter | Description |
|-----------|-------------|
| `$posts_grouped` | A-Z grouped posts array |
| `$show_nav` | Whether to render A-Z navigation |
| `$show_filter` | Whether to render taxonomy pills/dropdown |
| `$filter_style` | `pills` or `dropdown` |
| `$taxonomy_terms` | Available terms for the filter |
| `$active_term` | Currently active taxonomy term slug (empty = "All") |

### Rendering paths

- **Shortcode:** Calls `TCD_Query` with settings page values, includes template with defaults (nav on, filter off unless taxonomy is configured)
- **Elementor widget:** Calls `TCD_Query` with widget control values (falls back to settings page), includes template with widget-specific overrides. Inline styles from Elementor controls are applied.
- **AJAX handler:** Calls `TCD_Query` with the filtered term, renders just the `glossary__sections` portion, returns the HTML fragment.

## Frontend Taxonomy Filtering

When taxonomy filter is enabled:

1. Horizontal pill/tab buttons render above the A-Z nav
2. "All" button shown first (active by default, unless a default term is set)
3. One pill per taxonomy term that has published posts in the selected CPT
4. Clicking a pill fires an AJAX request that re-queries and replaces the glossary sections
5. A-Z nav updates to reflect which letters have terms in the filtered result set
6. Active pill receives the active style; inactive pills receive the default style

`tcd-glossary.js` handles click events and AJAX communication. The JS passes `post_type`, `taxonomy`, and `term_slug` as AJAX parameters so the handler is stateless and works correctly with multiple widget instances on the same page.

## A-Z Navigation

- Rendered inside a rounded container with evenly spaced letters
- Active letters (those with at least one term) displayed in dark text
- Inactive letters greyed out with `aria-disabled="true"`
- Hover/focus on active letters shows accent color
- Sticky positioning at top of viewport
- Updates dynamically when taxonomy filter changes

## Shortcode

The existing `[tcd_glossary]` shortcode continues to work alongside the Elementor widget. It uses settings page values (CPT and taxonomy) and renders via the shared template with default display options.

## GitHub Auto-Updater

### Mechanism

`class-tcd-updater.php` hooks into WordPress's native update system:

1. **`pre_set_site_transient_update_plugins`** — On the normal WP update schedule, calls the GitHub releases API:
   ```
   https://api.github.com/repos/{owner}/{repo}/releases/latest
   ```
   Compares the remote tag version against `TCD_GLOSSARY_VERSION`. If newer, injects update data into the transient so WordPress shows the update notice in the admin.

2. **`plugins_api`** — Provides plugin details (description, changelog, download URL) for the "View Details" modal on the plugins screen.

### Configuration

- `owner/repo` string stored as a constant in `tcd-glossary.php` (e.g., `TCD_GLOSSARY_GITHUB_REPO`)
- Public repo, no authentication required

### Release workflow

1. Update `TCD_GLOSSARY_VERSION` in `tcd-glossary.php` and `Stable tag` in `readme.txt`
2. Build the plugin zip with folder name `tcd-glossary`
3. Create a GitHub release with a semver tag (e.g., `v1.1.0`)
4. Attach the zip as a release asset

### Caching

GitHub API response cached in a WordPress transient for 12 hours to avoid excessive API calls.

### Download URL

Points to the zip asset attached to the GitHub release (not the auto-generated source zip) so the extracted folder name is correct (`tcd-glossary`).
