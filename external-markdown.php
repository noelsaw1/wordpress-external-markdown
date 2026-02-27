<?php

/**
 * Plugin Name: External Markdown
 * Author:      Moritz Stueckler
 * Description: Include and parse markdown files from external web sources like GitHub, GitLab, etc.
 * Plugin URI:  https://github.com/pReya/wordpress-external-markdown
 * Version:     0.3.0
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

function external_markdown_shortcode($atts = array())
{
  $GITHUB_MARKDOWN_API = "https://api.github.com/markdown";
  $MARKDOWN_EXAMPLE = "https://raw.githubusercontent.com/pReya/wordpress-external-markdown/main/README.md";
  // 1 hour = 60s * 60
  $DEFAULT_CACHE_TTL = strval(60 * 60);

  extract(shortcode_atts(array(
    'url' => $MARKDOWN_EXAMPLE,
    'class' => 'external-markdown',
    'ttl' => $DEFAULT_CACHE_TTL,
    'copy' => 'true',
    'cdn' => 'true',
    'excerpt' => '',
    'table' => 'true',
    'refresh' => 'true'
  ), $atts));

  $copy_enabled = !in_array(strtolower(strval($copy)), array('false', '0', 'no'), true);
  $cdn_enabled = !in_array(strtolower(strval($cdn)), array('false', '0', 'no'), true);
  $table_enabled = !in_array(strtolower(strval($table)), array('false', '0', 'no'), true);
  $refresh_enabled = !in_array(strtolower(strval($refresh)), array('false', '0', 'no'), true);
  $resolved_url = external_markdown_maybe_convert_github_to_cdn($url, $cdn_enabled);

  // TTL != 0 means caching is enabled
  if ($ttl !== strval(0)) {
    $cache_key = "external_markdown_" . md5($resolved_url . $class . $ttl . strval($copy) . strval($cdn) . strval($excerpt) . strval($table) . strval($refresh));
    $cached = get_transient($cache_key);
  }

  // Cache miss or cache disabled
  if (!(isset($cached)) || ($cached === false)) {
    $fetch_content = wp_remote_get($resolved_url);
    $content_response_body = wp_remote_retrieve_body($fetch_content);
    $content_response_code = wp_remote_retrieve_response_code($fetch_content);

    if ($content_response_code != 200) {
      return "<strong>Plugin Error:</strong> Could not fetch external markdown source.";
    }

    $parsed = external_markdown_parse_frontmatter($content_response_body);
    $frontmatter = $table_enabled ? $parsed['frontmatter'] : array();
    $markdown_for_api = $parsed['markdown'];

    $args = array(
      'body' => json_encode(array(
        "text" => $markdown_for_api
      )),
      'headers' => array(
        'Content-Type' => 'application/json'
      )
    );

    $fetch_github = wp_remote_post($GITHUB_MARKDOWN_API, $args);
    $github_response_body = wp_remote_retrieve_body($fetch_github);
    $github_response_code = wp_remote_retrieve_response_code($fetch_github);

    if ($github_response_code != 200) {
      return "<strong>Plugin Error:</strong> Could not fetch converted markdown file.";
    }

    $refresh_key = ($refresh_enabled && $ttl !== strval(0)) ? $cache_key : '';
    $html_string = external_markdown_build_html($github_response_body, $parsed['markdown'], $class, $copy_enabled, $excerpt, $frontmatter, $refresh_key);

    if ($ttl != 0) {
      set_transient($cache_key, $html_string, $ttl);
    }

    return $html_string;
  } else {
    // Cache hit
    return $cached;
  }
}

add_shortcode('external_markdown', 'external_markdown_shortcode');

function external_markdown_handle_refresh()
{
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'external_markdown_refresh')) {
    wp_send_json_error('Invalid nonce', 403);
  }
  if (!isset($_POST['cache_key']) || strpos($_POST['cache_key'], 'external_markdown_') !== 0) {
    wp_send_json_error('Invalid cache key', 400);
  }
  delete_transient(sanitize_text_field($_POST['cache_key']));
  wp_send_json_success();
}
add_action('wp_ajax_external_markdown_refresh', 'external_markdown_handle_refresh');
add_action('wp_ajax_nopriv_external_markdown_refresh', 'external_markdown_handle_refresh');

function external_markdown_build_html($rendered_html, $raw_markdown, $class, $copy_enabled, $excerpt = '', $frontmatter = array(), $refresh_key = '')
{
  static $style_added = false;
  $cache_attr = ($refresh_key !== '') ? ' data-cache-key="' . esc_attr($refresh_key) . '"' : '';
  $wrapper_open = '<div class="external-markdown-wrapper" data-external-markdown="true"' . $cache_attr . '>';
  $wrapper_close = '</div>';

  $excerpt_attr = ($excerpt !== '' && intval($excerpt) > 0) ? ' data-excerpt-lines="' . intval($excerpt) . '"' : '';
  $content_html = '<div class="' . esc_attr($class) . '"' . $excerpt_attr . '>' . $rendered_html . '</div>';
  $style_html = '';

  if (!$style_added) {
    $style_html = '<style>'
      . '.external-markdown-copy-button{font:inherit;padding:0.5rem 0.85rem;margin-bottom:1rem;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;cursor:pointer;transition:background-color 120ms ease-in-out,transform 120ms ease-in-out;}'
      . '.external-markdown-copy-button:hover{background-color:rgba(0,0,0,0.06);}'
      . '.external-markdown-copy-button:active{transform:translateY(1px);}'
      . '.external-markdown-copy-button:focus-visible{outline:2px solid currentColor;outline-offset:2px;}'
      . '.external-markdown-excerpt-fade{position:absolute;bottom:0;left:0;right:0;height:5em;background:linear-gradient(to bottom,transparent,rgba(255,255,255,0.97));pointer-events:none;}'
      . '.external-markdown-see-more-button{display:block;margin:0.75rem auto 0;font:inherit;padding:0.5rem 1.25rem;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;cursor:pointer;transition:background-color 120ms ease-in-out;}'
      . '.external-markdown-see-more-button:hover{background-color:rgba(0,0,0,0.06);}'
      . '.external-markdown-see-more-button:focus-visible{outline:2px solid currentColor;outline-offset:2px;}'
      . '.external-markdown-frontmatter{width:100%;border-collapse:collapse;margin-bottom:1.5rem;font:inherit;}'
      . '.external-markdown-frontmatter th,.external-markdown-frontmatter td{padding:0.45rem 0.75rem;border:1px solid rgba(0,0,0,0.12);text-align:left;vertical-align:top;}'
      . '.external-markdown-frontmatter th{font-weight:600;white-space:nowrap;width:1%;background-color:rgba(0,0,0,0.03);}'
      . '.external-markdown-wrapper{position:relative;}'
      . '.external-markdown-refresh-button{position:absolute;top:0;right:0;display:inline-flex;align-items:center;gap:0.35rem;font:inherit;padding:0.5rem 0.85rem;border:1px solid rgba(128,128,128,0.5);border-radius:4px;background:transparent;color:rgba(128,128,128,0.8);cursor:pointer;transition:background-color 120ms ease-in-out,transform 120ms ease-in-out;}'
      . '.external-markdown-refresh-button:hover{background-color:rgba(0,0,0,0.06);}'
      . '.external-markdown-refresh-button:active{transform:translateY(1px);}'
      . '.external-markdown-refresh-button:focus-visible{outline:2px solid currentColor;outline-offset:2px;}'
      . '.external-markdown-refresh-button svg{width:1em;height:1em;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}'
      . '.external-markdown-refresh-button.is-loading svg{animation:em-spin 0.8s linear infinite;}'
      . '@keyframes em-spin{to{transform:rotate(360deg);}}'
      . '</style>';
    $style_added = true;
  }

  // AJAX config for refresh feature (emitted once)
  static $ajax_added = false;
  $ajax_html = '';
  if (!$ajax_added && $refresh_key !== '') {
    $ajax_added = true;
    $ajax_html = '<script>window.ExternalMarkdownAjax='
      . json_encode(array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('external_markdown_refresh')
      ))
      . ';</script>';
  }

  // Script handles copy, excerpt, and refresh; each feature guards its own one-time init.
  $script_html = '<script>(function(){'
    . 'if(!window.ExternalMarkdownCopyInit){window.ExternalMarkdownCopyInit=true;'
    . 'function fallbackCopyText(text){var temp=document.createElement("textarea");temp.value=text;temp.setAttribute("readonly","");temp.style.position="absolute";temp.style.left="-9999px";document.body.appendChild(temp);temp.select();try{document.execCommand("copy");}catch(err){}document.body.removeChild(temp);}'
    . 'function copyText(text){if(!text){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).catch(function(){fallbackCopyText(text);});}else{fallbackCopyText(text);}}'
    . 'document.addEventListener("click",function(event){var button=event.target.closest(".external-markdown-copy-button");if(!button){return;}var wrapper=button.closest("[data-external-markdown]");if(!wrapper){return;}var source=wrapper.querySelector(".external-markdown-source");var text=source?source.value||source.textContent:"";copyText(text);});}'
    . 'if(!window.ExternalMarkdownExcerptInit){window.ExternalMarkdownExcerptInit=true;'
    . 'function initExcerpt(){document.querySelectorAll("[data-excerpt-lines]").forEach(function(el){var lines=parseInt(el.getAttribute("data-excerpt-lines"),10);if(!lines||lines<=0){return;}var st=window.getComputedStyle(el);var lh=parseFloat(st.lineHeight);if(isNaN(lh)){lh=parseFloat(st.fontSize)*1.5;}var threshold=lines*lh;if(el.scrollHeight<=threshold){return;}el.style.position="relative";el.style.maxHeight=threshold+"px";el.style.overflow="hidden";var fade=document.createElement("div");fade.className="external-markdown-excerpt-fade";el.appendChild(fade);var btn=document.createElement("button");btn.type="button";btn.className="external-markdown-see-more-button";btn.textContent="See More";el.parentNode.insertBefore(btn,el.nextSibling);btn.addEventListener("click",function(){el.style.maxHeight="";el.style.overflow="";el.style.position="";fade.remove();btn.remove();});});}'
    . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initExcerpt);}else{initExcerpt();}}'
    . 'if(!window.ExternalMarkdownRefreshInit&&window.ExternalMarkdownAjax){window.ExternalMarkdownRefreshInit=true;'
    . 'document.addEventListener("click",function(event){var button=event.target.closest(".external-markdown-refresh-button");if(!button||button.classList.contains("is-loading")){return;}var wrapper=button.closest("[data-cache-key]");if(!wrapper){return;}var cacheKey=wrapper.getAttribute("data-cache-key");if(!cacheKey){return;}button.classList.add("is-loading");var origText=button.lastChild;origText.textContent="Refreshing\u2026";var cfg=window.ExternalMarkdownAjax;var fd=new FormData();fd.append("action","external_markdown_refresh");fd.append("cache_key",cacheKey);fd.append("nonce",cfg.nonce);fetch(cfg.url,{method:"POST",body:fd}).then(function(){location.reload();}).catch(function(){button.classList.remove("is-loading");origText.textContent="Grab Newest";});});}'
    . '})();</script>';

  $frontmatter_html = '';
  if (!empty($frontmatter)) {
    $frontmatter_html = '<table class="external-markdown-frontmatter"><tbody>';
    foreach ($frontmatter as $key => $value) {
      $frontmatter_html .= '<tr><th>' . esc_html($key) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
    $frontmatter_html .= '</tbody></table>';
  }

  $refresh_html = '';
  if ($refresh_key !== '') {
    $refresh_html = '<button type="button" class="external-markdown-refresh-button">'
      . '<svg viewBox="0 0 24 24"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>'
      . '<span>Grab Newest</span></button>';
  }

  if (!$copy_enabled) {
    return $wrapper_open . $ajax_html . $style_html . $refresh_html . $frontmatter_html . $content_html . $script_html . $wrapper_close;
  }

  $button_html = '<button type="button" class="external-markdown-copy-button">Copy to Clipboard</button>';
  $source_html = '<textarea class="external-markdown-source" readonly tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">' . esc_textarea($raw_markdown) . '</textarea>';

  return $wrapper_open . $ajax_html . $style_html . $button_html . $source_html . $refresh_html . $frontmatter_html . $content_html . $script_html . $wrapper_close;
}

function external_markdown_parse_frontmatter($raw_markdown)
{
  $result = array('frontmatter' => array(), 'markdown' => $raw_markdown);

  // Strategy 1: Standard YAML frontmatter between --- delimiters
  $yaml_pattern = '/\A---\s*\r?\n(.*?)\r?\n---\s*\r?\n/s';
  if (preg_match($yaml_pattern, $raw_markdown, $matches)) {
    $result['markdown'] = substr($raw_markdown, strlen($matches[0]));
    $lines = preg_split('/\r?\n/', $matches[1]);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || strpos($line, '#') === 0) {
        continue;
      }
      if (preg_match('/^([A-Za-z0-9_\-\s]+):\s*(.*)$/', $line, $kv)) {
        $key = trim($kv[1]);
        $value = trim($kv[2]);
        if (
          (strlen($value) >= 2) &&
          (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
          $value = substr($value, 1, -1);
        }
        if ($key !== '') {
          $result['frontmatter'][$key] = $value;
        }
      }
    }
    return $result;
  }

  // Strategy 2: Bold markdown **Key:** value lines after an optional heading
  $lines = preg_split('/\r?\n/', $raw_markdown);
  $meta_lines = array();
  $start_index = 0;
  $end_index = 0;

  // Skip optional leading heading line(s) and blank lines
  foreach ($lines as $i => $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || preg_match('/^#{1,6}\s/', $trimmed)) {
      $start_index = $i + 1;
      continue;
    }
    break;
  }

  // Collect consecutive **Key:** value lines (allow blank lines between them)
  for ($i = $start_index; $i < count($lines); $i++) {
    $trimmed = trim($lines[$i]);
    if ($trimmed === '') {
      continue;
    }
    if (preg_match('/^\*\*([^*]+):\*\*\s*(.*)$/', $trimmed, $kv)) {
      $key = trim($kv[1]);
      $value = trim($kv[2]);
      // Strip markdown italic markers from values like _not yet audited_
      $value = preg_replace('/^_(.*)_$/', '$1', $value);
      if ($key !== '') {
        $meta_lines[] = array('key' => $key, 'value' => $value);
        $end_index = $i;
      }
    } else {
      // First non-meta, non-blank line ends the block
      break;
    }
  }

  if (!empty($meta_lines)) {
    foreach ($meta_lines as $pair) {
      $result['frontmatter'][$pair['key']] = $pair['value'];
    }
    // Strip the meta lines from the markdown (keep heading, remove meta block)
    $remaining = array();
    $in_meta = false;
    for ($i = 0; $i < count($lines); $i++) {
      if ($i >= $start_index && $i <= $end_index) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '' || preg_match('/^\*\*[^*]+:\*\*/', $trimmed)) {
          continue; // skip meta lines and interstitial blanks
        }
      }
      $remaining[] = $lines[$i];
    }
    $result['markdown'] = implode("\n", $remaining);
  }

  return $result;
}

function external_markdown_maybe_convert_github_to_cdn($url, $cdn_enabled)
{
  if (!$cdn_enabled) {
    return $url;
  }

  $parsed = wp_parse_url($url);
  if (!is_array($parsed) || !isset($parsed['host']) || !isset($parsed['path'])) {
    return $url;
  }

  $host = strtolower($parsed['host']);
  $path = ltrim($parsed['path'], '/');

  if ($host === 'github.com') {
    $parts = explode('/', $path);
    if (count($parts) < 5 || $parts[2] !== 'blob') {
      return $url;
    }

    $owner = $parts[0];
    $repo = $parts[1];
    $branch = $parts[3];
    $file_path = implode('/', array_slice($parts, 4));

    return 'https://cdn.jsdelivr.net/gh/' . $owner . '/' . $repo . '@' . $branch . '/' . $file_path;
  }

  if ($host === 'raw.githubusercontent.com') {
    $parts = explode('/', $path);
    if (count($parts) < 4) {
      return $url;
    }

    $owner = $parts[0];
    $repo = $parts[1];
    $branch = $parts[2];
    $file_path = implode('/', array_slice($parts, 3));

    return 'https://cdn.jsdelivr.net/gh/' . $owner . '/' . $repo . '@' . $branch . '/' . $file_path;
  }

  return $url;
}
