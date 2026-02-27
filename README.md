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

#### Styling the copy button
The copy button uses the `.external-markdown-copy-button` class. You can override it in your theme's CSS:
```css
.external-markdown-copy-button {
  font: inherit;
  padding: 0.5rem 0.85rem;
  border: 1px solid currentColor;
  border-radius: 4px;
  background: transparent;
}
```

#### Attention when using GitHub, GitLab, etc.
When embedding content from Git hosting services like GitHub or GitLab, you normally need the "raw" Markdown URL. For GitHub, this plugin also accepts standard `blob` URLs and will convert them to the jsDelivr CDN automatically when `cdn="true"` (the default). You can find the raw URL in the upper right corner of the GitHub web view.

### Attribution
* This plugin was inspired by the great [wordpress-markdown-git](https://wordpress.org/plugins/documents-from-git/) plugin, which serves a similar purpose, but uses the Git provider APIs to fetch the raw Markdown files – which is a little more complicated than this approach here.