=== WP Curl Terminal ===
Contributors: levysoft
Tags: terminal, curl, ansi, text, multilingual
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A colorful, terminal-friendly version of public WordPress content for curl clients. The regular website stays unchanged; no SSH, Telnet or server configuration changes are required.

== Installation ==

1. In WordPress, go to Plugins > Add New > Upload Plugin and select the ZIP.
2. If upgrading, choose to replace the existing version; saved settings are retained.
3. Open Settings directly from the plugin row in Plugins > Installed Plugins, or visit Settings > Curl Terminal.
4. Try these commands (replace example.org with your domain):

   curl -L https://example.org/
   curl -L https://example.org/your-post/
   curl -L 'https://example.org/?s=wordpress'
   curl -L 'https://example.org/?terminal=plain'
   curl -L -H 'X-Original: true' https://example.org/

== Features ==

* Site-title-based ASCII logo with a compact fallback for long/unsupported names.
* Home page with latest posts, links, dates and navigation hints.
* Readable terminal rendering of posts, pages, categories, tags, searches and archives.
* Text wrapping, code blocks, lists, quoted text, basic headings and links.
* Explicit ANSI/plain/HTML modes and automatic curl User-Agent detection.
* Color palette, number of listed posts and logo options in WordPress admin.
* Direct Settings links below the plugin name and description in the plugin list.
* No shortcode or dynamic block execution for terminal requests.

== Languages ==

The original plugin language is English (en_US). An Italian translation (it_IT)
is bundled in languages/wp-curl-terminal-it_IT.mo and loads automatically
from the language selected in Settings > General > Site Language.
WordPress users may select another admin profile language for their own dashboard;
the public terminal pages follow the site's public locale.
Translation source files (.po and .pot) are also included for contributors.
No language setting, database migration, or extra plugin is required.
For Italian instructions, see readme-it_IT.txt.

== Security and caches ==

* Public read-only views; this plugin does not provide remote command execution.
* Password-protected content is not exposed as full text.
* Designed for WordPress 6.0+ on shared PHP 7.4+ hosting.
* A reverse proxy, CDN or page-cache layer must distinguish/bypass curl requests;
  HTTP cache response headers cannot bypass a cache ahead of WordPress.

== Settings and uninstall ==

Settings are stored in the WordPress option wct_settings.
Deactivation and updates retain them. Uninstalling from the WordPress Plugins
screen deletes this option, including per-site options on multisite.
Removing plugin files via FTP does not execute uninstall.php.

== Changelog ==

= 1.2.1 =
* Added Settings links to the plugin actions and metadata on the Installed Plugins screen.
* Included Italian translation for the new Settings labels.
* No database changes; existing options and uninstall behavior are preserved.

= 1.2.0 =
* Added native English/Italian localization using the WordPress translation system.
* Bundled editable Italian .po, compiled .mo, and translation template .pot.
* Localized admin settings, terminal labels, status/error texts and image placeholders.
* Preserved existing option names, URL behavior, generated logos and uninstall cleanup.

= 1.1.1 =
* Site title-based ASCII logo and no hard-coded site branding in terminal output.
* Clean uninstall removing stored settings, including multisite.

= 1.1.0 =
* ASCII logo, navigation and improved article formatting.
* Avoid executing dynamic blocks and third-party shortcodes in terminal mode.

= 1.0.1 =
* Compact home page without theme widget duplication.

== Credits ==

Plugin author: Antonio Troise (Levysoft)
Plugin URI: https://www.levysoft.it/
Inspired by: https://nytpu.com/gemlog/2026-03-18
