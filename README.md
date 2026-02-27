# Fork of by Hypercart:
https://github.com/pReya/wordpress-external-markdown

# External Markdown WordPress Plugin

This is a plugin for WordPress to embed Markdown files from external web sources (e.g. GitHub, GitLab, etc) into WordPress content using the shortcode `[external_markdown]`. A possible use case for this is to show content from a single source of truth (like a GitHub repository) on your WordPress website.

### Features
* Embed any publicly accessible markdown file from other websites
* Uses the public and free [GitHub API](https://docs.github.com/en/rest/reference/markdown) to transform Markdown to HTML (there is a rate limit – so don't overdo it)
* Caching support (so you can configure how often the file is being fetched from the source – default is a **once per hour** maximum)
* Customise the CSS class of the markdown container to adjust styles
* **"Copy to Clipboard" button** to copy the raw Markdown content (enabled by default)
* **Automatic GitHub CDN conversion** — GitHub `blob` and `raw.githubusercontent.com` URLs are transparently rewritten to jsDelivr for faster, rate-limit-free delivery

### Instructions / Examples
Download and unpack or clone this repository into your `wp-content/plugins` folder, so the PHP file resides in `wp-content/plugins/external-markdown/external-markdown.php`.

If you use the Gutenberg editor, you need to add a "Shortcode" block first. If you're using the classic editor, you can use the shortcode right away.

#### Simple embed
The `url` parameter takes the URL to the Markdown file you want to embed. Make sure to [use the "raw" URL when you use GitHub, GitLab, etc](#attention-when-using-github-gitlab-etc).
```
[external_markdown url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

#### Automatic GitHub CDN conversion
When `cdn="true"` (default), GitHub `blob` or `raw.githubusercontent.com` URLs are automatically converted to jsDelivr before fetching. Example:
```
[external_markdown url="https://github.com/Hypercart-Dev-Tools/Love-2-Hug-Recipes/blob/main/4X4.md"]
```
This will fetch:
```
https://cdn.jsdelivr.net/gh/Hypercart-Dev-Tools/Love-2-Hug-Recipes@main/4X4.md
```
To disable CDN conversion:
```
[external_markdown cdn="false" url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

#### Adjust caching duration
The `ttl` parameter controls how long the cached version is used before it's fetched again. The value is given in seconds. So 24 hours (24 × 60 × 60) result in a value of 86400.
```
[external_markdown ttl=86400 url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

#### Adjust container CSS class
The `class` parameter takes your desired class name for the external markdown container. The default class name is `external-markdown`.
```
[external_markdown class="my-classname" url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

#### Disable the copy button
The `copy` parameter controls whether the "Copy to Clipboard" button is shown. The default is `true`.
```
[external_markdown copy="false" url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

#### Collapsible excerpt with "See More" button
The `excerpt` parameter limits the visible content to an approximate number of rendered lines. A gradient fade and a "See More" button are injected automatically below the truncated content. Clicking "See More" fully expands the block and removes both the button and the fade. The feature is **disabled by default** — only active when `excerpt` is set.
```
[external_markdown excerpt="300" url="https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md"]
```

> **Note:** The line count is a visual approximation based on the container's computed `line-height`, not a count of raw Markdown lines. The result will vary slightly depending on your theme's typography.

#### Styling the copy button and excerpt elements
The copy button uses `.external-markdown-copy-button` and the "See More" button uses `.external-markdown-see-more-button`. The excerpt fade overlay uses `.external-markdown-excerpt-fade`. Override any of these in your theme's CSS:
```css
.external-markdown-copy-button,
.external-markdown-see-more-button {
  font: inherit;
  padding: 0.5rem 1.25rem;
  border: 1px solid currentColor;
  border-radius: 4px;
  background: transparent;
}

/* For dark-background themes, update the fade gradient end colour */
.external-markdown-excerpt-fade {
  background: linear-gradient(to bottom, transparent, #1e1e1e);
}
```

#### Adding a copy button outside the content area (e.g. a navigation bar)

The built-in copy button lives **inside** the plugin's wrapper element (`<div data-external-markdown="true">`). The plugin's JavaScript finds the raw Markdown source by traversing *up* to that wrapper, so a button placed elsewhere on the page (such as a sticky nav bar) won't work with the default wiring.

To place a copy button anywhere on the page you need two things:

**1. Give the shortcode block a unique ID via its `class` parameter**

Use a unique value for `class` so you can target the right block when multiple embeds are on the same page:
```
[external_markdown class="my-recipe" url="https://raw.githubusercontent.com/..."]
```
This renders as:
```html
<div data-external-markdown="true">
  <textarea class="external-markdown-source">…raw markdown…</textarea>
  <div class="my-recipe">…rendered HTML…</div>
</div>
```

**2. Add a button in your theme's nav (or anywhere) with a `data-target` attribute**

Point `data-target` at the same class name you used in the shortcode:
```html
<button type="button"
        class="external-markdown-copy-button-remote"
        data-target="my-recipe">
  Copy Recipe
</button>
```

**3. Add custom JavaScript to wire the button up**

Place the following snippet in your theme's `functions.php` (using `wp_add_inline_script` or `wp_footer`), or paste it into the **Appearance → Customize → Additional CSS/JS** panel if your theme supports it:

```js
document.addEventListener('click', function (event) {
  var button = event.target.closest('.external-markdown-copy-button-remote');
  if (!button) return;

  var targetClass = button.dataset.target;
  if (!targetClass) return;

  // Find the wrapper that contains a block with the target class
  var block = document.querySelector('.' + targetClass);
  if (!block) return;

  var wrapper = block.closest('[data-external-markdown]');
  if (!wrapper) return;

  var source = wrapper.querySelector('.external-markdown-source');
  var text = source ? (source.value || source.textContent) : '';
  if (!text) return;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).catch(function () {
      fallback(text);
    });
  } else {
    fallback(text);
  }

  function fallback(text) {
    var temp = document.createElement('textarea');
    temp.value = text;
    temp.style.position = 'absolute';
    temp.style.left = '-9999px';
    document.body.appendChild(temp);
    temp.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(temp);
  }
});
```

**How it works:** instead of walking *up* to the wrapper from the button (which fails when the button is outside the wrapper), this script walks *down* — it finds the content `div` by its class name, then calls `.closest('[data-external-markdown]')` on that to reach the wrapper, and finally reads the hidden `<textarea class="external-markdown-source">` that holds the raw Markdown.

> **Note:** If you have multiple embeds on the same page, make sure each shortcode uses a **unique** `class` value, and that each remote button's `data-target` matches the corresponding class.

#### Attention when using GitHub, GitLab, etc.
When embedding content from Git hosting services like GitHub or GitLab, you normally need the "raw" Markdown URL. For GitHub, this plugin also accepts standard `blob` URLs and will convert them to the jsDelivr CDN automatically when `cdn="true"` (the default). You can find the raw URL in the upper right corner of the GitHub web view.

### Attribution
* This plugin was inspired by the great [wordpress-markdown-git](https://wordpress.org/plugins/documents-from-git/) plugin, which serves a similar purpose, but uses the Git provider APIs to fetch the raw Markdown files – which is a little more complicated than this approach here.