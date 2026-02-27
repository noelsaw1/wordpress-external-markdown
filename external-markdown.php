<?php

/**
 * Plugin Name: External Markdown
 * Author:      Moritz Stueckler
 * Description: Include and parse markdown files from external web sources like GitHub, GitLab, etc.
 * Plugin URI:  https://github.com/pReya/wordpress-external-markdown
 * Version:     0.1.0
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
    'excerpt' => ''
  ), $atts));

  $copy_enabled = !in_array(strtolower(strval($copy)), array('false', '0', 'no'), true);
  $cdn_enabled = !in_array(strtolower(strval($cdn)), array('false', '0', 'no'), true);
  $resolved_url = external_markdown_maybe_convert_github_to_cdn($url, $cdn_enabled);

  // TTL != 0 means caching is enabled
  if ($ttl !== strval(0)) {
    $cache_key = "external_markdown_" . md5($resolved_url . $class . $ttl . strval($copy) . strval($cdn) . strval($excerpt));
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

    $args = array(
      'body' => json_encode(array(
        "text" => $content_response_body
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

    $html_string = external_markdown_build_html($github_response_body, $content_response_body, $class, $copy_enabled, $excerpt);

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

function external_markdown_build_html($rendered_html, $raw_markdown, $class, $copy_enabled, $excerpt = '')
{
  static $style_added = false;
  $wrapper_open = '<div class="external-markdown-wrapper" data-external-markdown="true">';
  $wrapper_close = '</div>';

  $excerpt_attr = ($excerpt !== '' && intval($excerpt) > 0) ? ' data-excerpt-lines="' . intval($excerpt) . '"' : '';
  $content_html = '<div class="' . esc_attr($class) . '"' . $excerpt_attr . '>' . $rendered_html . '</div>';
  $style_html = '';

  if (!$style_added) {
    $style_html = '<style>'
      . '.external-markdown-copy-button{font:inherit;padding:0.5rem 0.85rem;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;cursor:pointer;transition:background-color 120ms ease-in-out,transform 120ms ease-in-out;}'
      . '.external-markdown-copy-button:hover{background-color:rgba(0,0,0,0.06);}'
      . '.external-markdown-copy-button:active{transform:translateY(1px);}'
      . '.external-markdown-copy-button:focus-visible{outline:2px solid currentColor;outline-offset:2px;}'
      . '.external-markdown-excerpt-fade{position:absolute;bottom:0;left:0;right:0;height:5em;background:linear-gradient(to bottom,transparent,rgba(255,255,255,0.97));pointer-events:none;}'
      . '.external-markdown-see-more-button{display:block;margin:0.75rem auto 0;font:inherit;padding:0.5rem 1.25rem;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;cursor:pointer;transition:background-color 120ms ease-in-out;}'
      . '.external-markdown-see-more-button:hover{background-color:rgba(0,0,0,0.06);}'
      . '.external-markdown-see-more-button:focus-visible{outline:2px solid currentColor;outline-offset:2px;}'
      . '</style>';
    $style_added = true;
  }

  // Script handles both copy and excerpt; each feature guards its own one-time init.
  $script_html = '<script>(function(){'
    . 'if(!window.ExternalMarkdownCopyInit){window.ExternalMarkdownCopyInit=true;'
    . 'function fallbackCopyText(text){var temp=document.createElement("textarea");temp.value=text;temp.setAttribute("readonly","");temp.style.position="absolute";temp.style.left="-9999px";document.body.appendChild(temp);temp.select();try{document.execCommand("copy");}catch(err){}document.body.removeChild(temp);}'
    . 'function copyText(text){if(!text){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).catch(function(){fallbackCopyText(text);});}else{fallbackCopyText(text);}}'
    . 'document.addEventListener("click",function(event){var button=event.target.closest(".external-markdown-copy-button");if(!button){return;}var wrapper=button.closest("[data-external-markdown]");if(!wrapper){return;}var source=wrapper.querySelector(".external-markdown-source");var text=source?source.value||source.textContent:"";copyText(text);});}'
    . 'if(!window.ExternalMarkdownExcerptInit){window.ExternalMarkdownExcerptInit=true;'
    . 'function initExcerpt(){document.querySelectorAll("[data-excerpt-lines]").forEach(function(el){var lines=parseInt(el.getAttribute("data-excerpt-lines"),10);if(!lines||lines<=0){return;}var st=window.getComputedStyle(el);var lh=parseFloat(st.lineHeight);if(isNaN(lh)){lh=parseFloat(st.fontSize)*1.5;}var threshold=lines*lh;if(el.scrollHeight<=threshold){return;}el.style.position="relative";el.style.maxHeight=threshold+"px";el.style.overflow="hidden";var fade=document.createElement("div");fade.className="external-markdown-excerpt-fade";el.appendChild(fade);var btn=document.createElement("button");btn.type="button";btn.className="external-markdown-see-more-button";btn.textContent="See More";el.parentNode.insertBefore(btn,el.nextSibling);btn.addEventListener("click",function(){el.style.maxHeight="";el.style.overflow="";el.style.position="";fade.remove();btn.remove();});});}'
    . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",initExcerpt);}else{initExcerpt();}}'
    . '})();</script>';

  if (!$copy_enabled) {
    return $wrapper_open . $style_html . $content_html . $script_html . $wrapper_close;
  }

  $button_html = '<button type="button" class="external-markdown-copy-button">Copy to Clipboard</button>';
  $source_html = '<textarea class="external-markdown-source" readonly tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">' . esc_textarea($raw_markdown) . '</textarea>';

  return $wrapper_open . $style_html . $button_html . $source_html . $content_html . $script_html . $wrapper_close;
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
