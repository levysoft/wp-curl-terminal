# WP Curl Terminal

[English](README.md) | [Italiano](README.it.md)

**Turn your WordPress website into a colorful, readable terminal experience with a single `curl` command.** The regular HTML site remains unchanged for browser visitors.

WP Curl Terminal detects requests from the `curl` command-line client and serves ANSI-colored, plain-text versions of your public WordPress posts, pages and archives. It works on ordinary shared hosting with PHP: **no SSH, Telnet, extra services or web-server configuration changes required**.

Created by **Antonio Troise (Levysoft)**. Try it on a live WordPress site:

```bash
curl -L https://www.levysoft.it/
```

## Why this plugin exists

The idea came from [Alex (nytpu)'s *Fancy Curl Sites*](https://nytpu.com/gemlog/2026-03-18), discovered through a [Hacker News discussion](https://news.ycombinator.com/item?id=50093612). Alex built a terminal-friendly version of his own website using ANSI control sequences and a custom publishing tool. I wanted to bring the same experience to an existing WordPress blog without maintaining a second website or requiring server access.

An early approach converted the entire theme-generated HTML to text, including duplicated menus and widgets. WP Curl Terminal instead reads the public content through WordPress itself and creates a compact terminal view on demand. New posts appear automatically, without exporting or rebuilding anything.

## Features

- **Automatic `curl` detection** through the HTTP `User-Agent`, with explicit ANSI, plain-text and original-HTML overrides.
- **Site-specific ASCII logo** generated from the WordPress site title, with a compact fallback for long names or unsupported characters. No hard-coded Levysoft branding in the output.
- **Compact homepage** with the latest posts, publication dates, optional full URLs and navigation hints.
- **Readable post and page rendering** with headings, paragraphs, lists, blockquotes, links, image alternative text and code blocks with indentation preserved. Regular text wraps at approximately 76 columns.
- **Public archive views** for categories, tags, search results, author and date archives, including pagination information where applicable.
- **Configurable colors**: cyan/yellow, green, amber or monochrome.
- **Settings in WordPress**: enable/disable terminal output, automatic detection, color scheme, homepage post count (1 to 30), ASCII logo and full URLs. Direct **Settings** links are available in the installed plugins list.
- **English and Italian** for the plugin interface and terminal labels, with bundled `.po`, `.mo` and `.pot` files. Post content is not automatically translated.
- **Read-only public output**: no remote shell or interactive server access. Password-protected posts do not expose their protected body, and third-party shortcodes/dynamic blocks are not executed for terminal rendering.
- **Clean uninstall**, including multisite: removing the plugin via the WordPress administration deletes its saved option.

## Requirements

- WordPress **6.0 or later**
- PHP **7.4 or later**
- An HTTP/HTTPS WordPress site on hosting that runs PHP
- A terminal with `curl` to try the ANSI interface

## Installation

1. Download `wp-curl-terminal.zip` from the [latest GitHub release](https://github.com/levysoft/wp-curl-terminal/releases/latest). **Download the release asset**, not GitHub's automatically generated source-code ZIP.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install and activate it. If upgrading, choose to **replace the existing plugin**, rather than deleting it first.
4. Open **Settings → Curl Terminal**, or click **Settings** directly under WP Curl Terminal on the installed plugins screen.
5. Try `curl -L https://your-domain.example/` from your terminal.

Alternatively, extract the ZIP and upload the `wp-curl-terminal/` folder to `wp-content/plugins/` by FTP, then activate it in WordPress.

## Usage

Replace the example URL with your own domain:

```bash
# Terminal homepage, automatically detected
curl -L https://www.levysoft.it/

# A public article (use its real WordPress permalink)
curl -L https://www.levysoft.it/archivio/2026/10/08/asd-ste100-e-eli5-20-modi-per-farsi-spiegare-meglio-le-cose-dagli-llm/

# Search results
curl -L 'https://www.levysoft.it/?s=python'

# Plain text without ANSI colors
curl -L 'https://www.levysoft.it/?terminal=plain'

# Force ANSI output without relying on User-Agent detection
curl -L -H 'Accept: text/x-ansi' https://www.levysoft.it/

# Request the original HTML, even from curl
curl -L -H 'X-Original: true' https://www.levysoft.it/

# Alternatively, explicitly accept HTML
curl -L -H 'Accept: text/html' https://www.levysoft.it/
```

## Why use `-L`?

`curl` does not follow HTTP redirects unless you ask it to. Some WordPress sites redirect `http://` to `https://` or redirect between hostnames with and without `www`. For example, requesting a Levysoft article over HTTP returns Apache's `301 Moved Permanently` page rather than the ANSI-formatted article:

```bash
curl http://www.levysoft.it/dizionario-ai/
```

```html
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head>
<title>301 Moved Permanently</title>
</head><body>
<h1>Moved Permanently</h1>
<p>The document has moved <a href="https://www.levysoft.it/dizionario-ai/">here</a>.</p>
<hr>
<address>Apache Server at www.levysoft.it Port 80</address>
</body></html>
```

Adding `-L` follows the redirect to HTTPS, where WordPress and WP Curl Terminal can return the article:

```bash
curl -L http://www.levysoft.it/dizionario-ai/
```

```text
============================================================
  Dizionario dell’Intelligenza Artificiale
============================================================
```

The same applies to the homepage. When the original URL already serves the content without redirecting, as with `https://www.levysoft.it/`, `-L` makes no additional HTTP requests. That is why the examples in this README use `-L` by default.

For long posts, pipe the output to **`less -R`**, which preserves supported ANSI colors and allows backward scrolling and searching:

```bash
curl -fsSL https://www.levysoft.it/ | less -R
```

Press `q` to exit `less`. If you want the displayed text to remain visible after exiting (terminal-dependent), use `less -RX`:

```bash
curl -fsSL https://www.levysoft.it/ | less -RX
```

`more` may display raw ANSI escape codes rather than colors. The `-fsSL` options suppress the progress meter, report errors, fail on HTTP errors and follow redirects; for debugging a 404 response body, use `curl -L` without `-f`.

## How it works

The plugin selects a terminal response for supported public WordPress views when it recognizes `curl`, an explicit `?terminal=ansi` / `?terminal=plain` parameter, or the `Accept: text/x-ansi` header. `X-Original: true` and `Accept: text/html` can request the regular HTML representation, unless an explicit `terminal` parameter takes precedence.

Content is retrieved from WordPress at request time. No separate static site, additional database tables, scheduled generation jobs or server-side binaries are required. Standard browsers, REST endpoints and other excluded WordPress requests retain their normal behavior.

**Caching note:** the plugin sends response headers such as `Vary` and no-cache directives, but a CDN, reverse proxy or full-page cache running *before* WordPress may still need to bypass or distinguish terminal requests.

## Languages

- **English** is the source language.
- **Italian** (`it_IT`) is included and selected automatically by WordPress localization settings.
- The site language controls public terminal labels; an administrator may use their own WordPress profile language for the dashboard.
- To contribute another translation, start from `languages/wp-curl-terminal.pot`.

## Settings and uninstall

Settings are stored in the WordPress `wct_settings` option (normally in the `wp_options` table; the table prefix may differ). Deactivating or upgrading keeps those settings. **Deleting the plugin through the WordPress Plugins screen** invokes `uninstall.php` and removes them, including site-specific options on multisite installations. Deleting files via FTP alone does **not** run the uninstall procedure.

## Limitations and security

The terminal view is designed for published, publicly accessible content. Complex layouts, page-builder output, third-party shortcodes and dynamic blocks may not render exactly as in a browser. The plugin does not expose Telnet, SSH or an execution shell. Like any WordPress plugin, it should be kept updated and tested with the site's caching configuration.

## Release and source code

- [Latest release and installable ZIP](https://github.com/levysoft/wp-curl-terminal/releases/latest)
- [Direct ZIP download from Levysoft](https://www.levysoft.it/plugin_levysoft/wp-curl-terminal-v1.2.1.zip)
- [Source code and issue tracker](https://github.com/levysoft/wp-curl-terminal)
- [Levysoft website](https://www.levysoft.it/)

For WordPress installation, use the pre-built release asset `wp-curl-terminal.zip` containing the `wp-curl-terminal/` plugin directory.

## License and credits

Released under **GPL-2.0-or-later**. See [LICENSE](LICENSE).

Author: **Antonio Troise (Levysoft)**  
Plugin website: https://www.levysoft.it/  
Author website: https://www.levysoft.it/

Inspired by [Alex (nytpu), *Fancy Curl Sites*](https://nytpu.com/gemlog/2026-03-18), and by the earlier terminal-site experiments mentioned there.
