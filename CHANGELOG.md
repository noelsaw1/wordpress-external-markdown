# Changelog

All notable changes to this fork are documented here.
Forked from [pReya/wordpress-external-markdown](https://github.com/pReya/wordpress-external-markdown) at v0.0.1.

---

## [0.3.0] — 2026-02-26

### Added
- **`refresh` parameter** — "Grab Newest" button with a recycle icon positioned in the top-right corner. Clicking it clears the WordPress transient cache for that embed and reloads the page with fresh content. Enabled by default; hidden when caching is disabled (`ttl=0`); set `refresh="false"` to hide.
- `external_markdown_handle_refresh()` AJAX handler with nonce verification for secure cache clearing.
- Refresh button styles (`.external-markdown-refresh-button`) with loading spinner animation.

### Changed
- Plugin version bumped from `0.2.0` to `0.3.0`.
- Wrapper `<div>` now includes `data-cache-key` attribute when refresh is enabled.
- `external_markdown_build_html()` accepts a new optional `$refresh_key` parameter (default `''`).
- Cache key now includes the `refresh` value.

---

## [0.2.0] — 2026-02-26

### Added
- **`table` parameter** — YAML frontmatter (between `---` delimiters at the top of a markdown file) is parsed and rendered as a styled HTML table above the content. Enabled by default; set `table="false"` to hide.
- `external_markdown_parse_frontmatter()` helper function that extracts frontmatter key-value pairs and returns the stripped markdown.
- Frontmatter table styles (`.external-markdown-frontmatter`, `.external-markdown-frontmatter th`, `.external-markdown-frontmatter td`).

### Changed
- Plugin version bumped from `0.1.0` to `0.2.0`.
- Frontmatter is stripped from markdown before sending to GitHub API, preventing garbled rendering.
- `external_markdown_build_html()` accepts a new optional `$frontmatter` parameter (default `array()`).
- Cache key now includes the `table` value so toggling the feature invalidates cache.
- Raw markdown for "Copy to Clipboard" has frontmatter stripped.

---

## [0.1.0] — 2026-02-27

### Added
- **`excerpt` parameter** — display a collapsed excerpt based on rendered line height and reveal the full content with a "See More" button. Disabled by default; set to a line count to enable (e.g. `excerpt="300"`).
- **`copy` parameter** — "Copy to Clipboard" button that copies the raw Markdown source. Enabled by default; set `copy="false"` to hide it.
- **`cdn` parameter** — automatically rewrites GitHub `blob` and `raw.githubusercontent.com` URLs to the jsDelivr CDN before fetching, reducing rate-limit exposure. Enabled by default; set `cdn="false"` to disable.
- Excerpt fade overlay (`.external-markdown-excerpt-fade`) with a bottom gradient to indicate truncated content.
- "See More" button (`.external-markdown-see-more-button`) injected below the content div when excerpt is active; clicking it fully expands the content and removes both the button and the fade.
- Per-feature JavaScript init guards (`window.ExternalMarkdownCopyInit`, `window.ExternalMarkdownExcerptInit`) so both features initialise exactly once per page regardless of how many shortcodes are present.
- Script is now emitted even when `copy="false"` so the excerpt feature works independently.

### Changed
- Plugin version bumped from `0.0.1` to `0.1.0`.
- `external_markdown_build_html()` accepts a new optional `$excerpt` parameter (default `''`).
- Cache key now includes the `excerpt` value so different excerpt lengths are cached separately.
- Inline styles split across multiple concatenated strings for readability.

### Fixed
- Typos in README: "Guttenberg" → "Gutenberg", "your're" → "you're".

### Notes
- The excerpt fade gradient defaults to `rgba(255,255,255,0.97)` (white). Override `.external-markdown-excerpt-fade` in your theme CSS for dark backgrounds.
- "300 lines" is a visual approximation based on `getComputedStyle(lineHeight)` of the rendered container, not a raw Markdown line count.

---

## [0.0.1] — original fork baseline

Original release by Moritz Stueckler. Features: shortcode embed, GitHub Markdown API rendering, transient caching, custom CSS class.

