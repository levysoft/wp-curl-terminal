<?php
/**
 * Plugin Name: WP Curl Terminal
 * Description: Provides a colorful terminal-friendly view of public WordPress content for curl clients, without changing the normal website.
 * Version: 1.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Plugin URI: https://www.levysoft.it/
 * Author: Antonio Troise (Levysoft)
 * Author URI: https://levysoft.it/
 * License: GPL-2.0-or-later
 * Text Domain: wp-curl-terminal
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Register bundled translations at init (WordPress 6.7+ JIT-safe). */
function wct_load_textdomain() {
    load_plugin_textdomain('wp-curl-terminal', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'wct_load_textdomain');

function wct_defaults() {
    return array(
        'enabled'       => '1',
        'autodetect'    => '1',
        'color'         => 'cyan',
        'posts_per_page'=> 10,
        'show_urls'     => '1',
        'ascii_logo'    => '1',
    );
}

function wct_options() {
    $saved = get_option('wct_settings', array());
    return array_merge(wct_defaults(), is_array($saved) ? $saved : array());
}

/** Explicit parameters win. HTML Accept (or X-Original) wins over UA autodetection. */
function wct_request_mode() {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) ||
        (defined('DOING_AJAX') && DOING_AJAX) ||
        (defined('DOING_CRON') && DOING_CRON) ||
        (defined('WP_CLI') && WP_CLI)) {
        return '';
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
        return '';
    }

    $options = wct_options();
    if ($options['enabled'] !== '1') {
        return '';
    }

    $parameter = $_GET['terminal'] ?? null;
    if (is_string($parameter)) {
        $parameter = strtolower(trim($parameter));
        if (in_array($parameter, array('0', 'off', 'html', 'false'), true)) {
            return '';
        }
        if (in_array($parameter, array('1', 'on', 'ansi', 'true'), true)) {
            return 'ansi';
        }
        if (in_array($parameter, array('plain', 'text'), true)) {
            return 'plain';
        }
    }

    if (strtolower(trim((string) ($_SERVER['HTTP_X_ORIGINAL'] ?? ''))) === 'true') {
        return '';
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (strpos($accept, 'text/x-ansi') !== false) {
        return 'ansi';
    }
    if (strpos($accept, 'text/html') !== false) {
        return '';
    }

    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $options['autodetect'] === '1' && preg_match('~(?:^|[\s(])curl/[0-9]~i', $agent)
        ? 'ansi'
        : '';
}

/* This helps cache plugins that respect DONOTCACHEPAGE after WordPress loads.
   A server-side full-page cache may still need separate bypass configuration. */
if (wct_request_mode() && !defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}

function wct_choose_template($template) {
    if (!wct_request_mode()) {
        return $template;
    }
    // Do not change the behavior of feeds, embeds, admin, or other special endpoints.
    if (is_feed() || is_embed() || is_trackback() || is_preview() || is_attachment()) {
        return $template;
    }
    // Limit to public WordPress screens; don't replace unrelated plugin routes.
    if (!(is_front_page() || is_home() || is_singular(array('post', 'page')) ||
          is_category() || is_tag() || is_search() || is_author() || is_date() || is_404())) {
        return $template;
    }
    return plugin_dir_path(__FILE__) . 'templates/terminal.php';
}
add_filter('template_include', 'wct_choose_template', 99);

/** Advertise content negotiation on HTML as well as terminal responses. */
function wct_vary_headers($headers) {
    if (is_admin() || wct_options()['enabled'] !== '1') {
        return $headers;
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, array('GET', 'HEAD'), true) || is_feed() || is_embed() || is_trackback()) {
        return $headers;
    }
    $existing = isset($headers['Vary']) ? (string) $headers['Vary'] : '';
    $tokens = array_filter(array_map('trim', explode(',', $existing)), 'strlen');
    foreach (array('User-Agent', 'Accept', 'X-Original') as $token) {
        $has_token = false;
        foreach ($tokens as $item) {
            if (strcasecmp($item, $token) === 0 || trim($item) === '*') {
                $has_token = true;
                break;
            }
        }
        if (!$has_token) { $tokens[] = $token; }
    }
    $headers['Vary'] = implode(', ', $tokens);
    return $headers;
}
add_filter('wp_headers', 'wct_vary_headers', 20);

/** Remove terminal escape and control characters from untrusted content. */
function wct_safe_text($text) {
    $text = (string) $text;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return $text;
}

/**
 * Convert the saved WordPress markup to readable terminal text.
 * Deliberately avoid do_shortcode(), do_blocks() and the_content filters:
 * dynamic blocks and third-party shortcodes must not run for curl clients.
 * No DOM/mbstring extensions are required on shared hosting.
 */
function wct_html_to_text($html) {
    $html = (string) $html;
    $html = preg_replace('~<(script|style|iframe|noscript|svg|form|canvas)\b[^>]*>.*?</\1\s*>~is', '', $html);
    $html = preg_replace('~<!--.*?-->~s', '', $html);
    $placeholders = array();

    // Capture PRE before stripping markup, preserving indentation and blank lines.
    $html = preg_replace_callback('~<pre\b[^>]*>(.*?)</pre\s*>~is', function ($m) use (&$placeholders) {
        $code = wp_strip_all_tags($m[1], false);
        $code = wct_safe_text($code);
        $code = str_replace(array("\r\n", "\r", "\xC2\xA0"), array("\n", "\n", ' '), $code);
        $code = trim($code, "\n");
        $lines = explode("\n", $code);
        $indented = array();
        foreach ($lines as $line) {
            $indented[] = '    ' . rtrim($line, "\r");
        }
        $marker = 'WCT_PRE_BLOCK_' . count($placeholders) . '_PLACEHOLDER';
        $placeholders[$marker] = "\n\n```\n" . implode("\n", $indented) . "\n```\n\n";
        return "\n\n" . $marker . "\n\n";
    }, $html);

    // Links: accept only web/mail links or relative paths, never executable schemes.
    $html = preg_replace_callback('~<a\b([^>]*)>(.*?)</a\s*>~is', function ($m) {
        $label = trim(wct_safe_text(wp_strip_all_tags($m[2])));
        $url = '';
        if (preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $m[1], $attr)) {
            $candidate = trim(wct_safe_text($attr[2]));
            $scheme = wp_parse_url($candidate, PHP_URL_SCHEME);
            if ($scheme === null || $scheme === false || in_array(strtolower($scheme), array('https', 'http', 'mailto'), true)) {
                $url = esc_url_raw($candidate);
            }
        }
        return $url !== '' && $label !== $url
            ? ($label !== '' ? $label . ' ' : '') . '(' . $url . ')'
            : ($label !== '' ? $label : $url);
    }, $html);

    $html = preg_replace_callback('~<img\b([^>]*)/?>~is', function ($m) {
        if (preg_match('~\balt\s*=\s*(["\'])(.*?)\1~is', $m[1], $attr)) {
            $alt = trim(wct_safe_text(wp_strip_all_tags($attr[2])));
            return $alt === '' ? '' : sprintf(__( '[Image: %s]', 'wp-curl-terminal' ), $alt);
        }
        return '';
    }, $html);

    // Headings, inline code, quotes, lists and paragraphs.
    $html = preg_replace_callback('~<h([1-6])\b[^>]*>(.*?)</h\1\s*>~is', function ($m) {
        return "\n\n" . str_repeat('#', min(4, (int) $m[1])) . ' ' . trim(wct_safe_text(wp_strip_all_tags($m[2]))) . "\n\n";
    }, $html);
    $html = preg_replace_callback('~<blockquote\b[^>]*>(.*?)</blockquote\s*>~is', function ($m) {
        $quote = trim(wct_safe_text(wp_strip_all_tags($m[1])));
        $quote = preg_replace('/\s*\n\s*/', ' ', $quote);
        return $quote === '' ? '' : "\n\n> " . $quote . "\n\n";
    }, $html);
    $html = preg_replace_callback('~<code\b[^>]*>(.*?)</code\s*>~is', function ($m) {
        return '`' . trim(wct_safe_text(wp_strip_all_tags($m[1]))) . '`';
    }, $html);
    $html = preg_replace('~<hr\b[^>]*/?>~i', "\n\n----------------------------------------\n\n", $html);
    $html = preg_replace('~<br\b[^>]*/?>~i', "\n", $html);
    $html = preg_replace('~<li\b[^>]*>~i', "\n  * ", $html);
    $html = preg_replace('~</(?:p|div|ul|ol|table|tr|section|article|figure|figcaption)\s*>~i', "\n\n", $html);
    $html = preg_replace('~<(?:p|div|tr|figcaption)\b[^>]*>~i', "\n", $html);
    $html = preg_replace('~</(?:td|th)\s*>~i', ' | ', $html);
    $html = wp_strip_all_tags($html, false);
    $html = wct_safe_text($html);
    $html = str_replace(array("\r\n", "\r", "\xC2\xA0"), array("\n", "\n", ' '), $html);
    $html = preg_replace('/[ \t]+\n/', "\n", $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    foreach ($placeholders as $marker => $block) {
        $html = str_replace($marker, $block, $html);
    }
    return trim(preg_replace('/\n{3,}/', "\n\n", $html));
}

/** Estimate printable column width without requiring mbstring. */
function wct_text_width($text) {
    if (function_exists('mb_strwidth')) {
        return mb_strwidth($text, 'UTF-8');
    }
    $count = preg_match_all('/./u', (string) $text, $matches);
    return $count === false ? strlen((string) $text) : $count;
}

/** Wrap prose and headings, but not code blocks or long standalone URLs. */
function wct_wrap_line($text, $max = 76, $prefix = '', $continuation = '') {
    $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) {
        return rtrim($prefix);
    }
    $lines = array();
    $line = $prefix;
    $has_word = false;
    foreach ($words as $word) {
        $candidate = $line . ($has_word ? ' ' : '') . $word;
        if ($has_word && wct_text_width($candidate) > $max) {
            $lines[] = $line;
            $line = $continuation . $word;
        } else {
            $line = $candidate;
        }
        $has_word = true;
    }
    $lines[] = $line;
    return implode("\n", $lines);
}

function wct_render_body($body, $mode) {
    $code = false;
    foreach (explode("\n", $body) as $raw) {
        $line = rtrim($raw);
        if (trim($line) === '```') {
            $code = !$code;
            wct_line(wct_paint($line, 'muted', $mode));
            continue;
        }
        if ($code) {
            wct_line(wct_paint($line, 'code', $mode));
            continue;
        }
        if ($line === '') {
            wct_line();
        } elseif (preg_match('/^(#{1,4})\s+(.+)$/u', trim($line), $heading)) {
            wct_line(wct_paint(wct_wrap_line($heading[2], 76, $heading[1] . ' ', '    '), 'heading', $mode));
        } elseif (preg_match('/^\s*([>*])\s+(.+)$/u', $line, $marker)) {
            $prefix = $marker[1] === '>' ? '> ' : '  * ';
            wct_line(wct_paint(wct_wrap_line($marker[2], 76, $prefix, str_repeat(' ', wct_text_width($prefix))), $marker[1] === '>' ? 'muted' : 'accent', $mode));
        } else {
            wct_line(wct_wrap_line(trim($line), 76));
        }
    }
}

function wct_palette() {
    $options = wct_options();
    $map = array(
        'cyan'  => array('heading' => '1;36', 'accent' => '1;33', 'muted' => '2;37', 'code' => '0;32'),
        'green' => array('heading' => '1;32', 'accent' => '1;36', 'muted' => '2;37', 'code' => '0;32'),
        'amber' => array('heading' => '1;33', 'accent' => '1;35', 'muted' => '2;37', 'code' => '0;32'),
        'mono'  => array('heading' => '1',    'accent' => '1',    'muted' => '2', 'code' => '0'),
    );
    return $map[$options['color']] ?? $map['cyan'];
}

function wct_paint($text, $role, $mode) {
    $text = wct_safe_text($text);
    if ($mode === 'plain') {
        return $text;
    }
    $colors = wct_palette();
    $color = $colors[$role] ?? '0';
    return "\033[" . $color . 'm' . $text . "\033[0m";
}

function wct_line($content = '') {
    echo $content . "\n";
}

function wct_heading($name, $mode) {
    wct_line(wct_paint(str_repeat('=', 60), 'heading', $mode));
    wct_line(wct_paint(wct_wrap_line(wct_safe_text($name), 76, '  ', '  '), 'heading', $mode));
    wct_line(wct_paint(str_repeat('=', 60), 'heading', $mode));
}

/**
 * Render an automatically generated ASCII wordmark using the WordPress site title.
 * No external binaries, extensions, remote services or server shell are needed.
 * For names that cannot be represented in this small font, use the original name
 * in a compact framed banner rather than silently changing the site's identity.
 */
function wct_logo_font() {
    return array(
        'A' => array(' ### ', '#   #', '#####', '#   #', '#   #'),
        'B' => array('#### ', '#   #', '#### ', '#   #', '#### '),
        'C' => array(' ####', '#    ', '#    ', '#    ', ' ####'),
        'D' => array('#### ', '#   #', '#   #', '#   #', '#### '),
        'E' => array('#####', '#    ', '#### ', '#    ', '#####'),
        'F' => array('#####', '#    ', '#### ', '#    ', '#    '),
        'G' => array(' ####', '#    ', '# ###', '#   #', ' ####'),
        'H' => array('#   #', '#   #', '#####', '#   #', '#   #'),
        'I' => array('#####', '  #  ', '  #  ', '  #  ', '#####'),
        'J' => array('#####', '   # ', '   # ', '#  # ', ' ##  '),
        'K' => array('#   #', '#  # ', '###  ', '#  # ', '#   #'),
        'L' => array('#    ', '#    ', '#    ', '#    ', '#####'),
        'M' => array('#   #', '## ##', '# # #', '#   #', '#   #'),
        'N' => array('#   #', '##  #', '# # #', '#  ##', '#   #'),
        'O' => array(' ### ', '#   #', '#   #', '#   #', ' ### '),
        'P' => array('#### ', '#   #', '#### ', '#    ', '#    '),
        'Q' => array(' ### ', '#   #', '#   #', '#  ##', ' ####'),
        'R' => array('#### ', '#   #', '#### ', '#  # ', '#   #'),
        'S' => array(' ####', '#    ', ' ### ', '    #', '#### '),
        'T' => array('#####', '  #  ', '  #  ', '  #  ', '  #  '),
        'U' => array('#   #', '#   #', '#   #', '#   #', ' ### '),
        'V' => array('#   #', '#   #', '#   #', ' # # ', '  #  '),
        'W' => array('#   #', '#   #', '# # #', '## ##', '#   #'),
        'X' => array('#   #', ' # # ', '  #  ', ' # # ', '#   #'),
        'Y' => array('#   #', ' # # ', '  #  ', '  #  ', '  #  '),
        'Z' => array('#####', '   # ', '  #  ', ' #   ', '#####'),
        '0' => array(' ### ', '#  ##', '# # #', '##  #', ' ### '),
        '1' => array('  #  ', ' ##  ', '  #  ', '  #  ', '#####'),
        '2' => array('#### ', '    #', ' ### ', '#    ', '#####'),
        '3' => array('#### ', '    #', ' ### ', '    #', '#### '),
        '4' => array('#   #', '#   #', '#####', '    #', '    #'),
        '5' => array('#####', '#    ', '#### ', '    #', '#### '),
        '6' => array(' ####', '#    ', '#### ', '#   #', ' ### '),
        '7' => array('#####', '   # ', '  #  ', ' #   ', ' #   '),
        '8' => array(' ### ', '#   #', ' ### ', '#   #', ' ### '),
        '9' => array(' ### ', '#   #', ' ####', '    #', '#### '),
        ' ' => array('     ', '     ', '     ', '     ', '     '),
        '-' => array('     ', '     ', '#####', '     ', '     '),
        '.' => array('     ', '     ', '     ', ' ### ', ' ### '),
        '&' => array(' ##  ', '#  # ', ' ## #', '#  # ', ' ## #'),
        '!' => array('  #  ', '  #  ', '  #  ', '     ', '  #  '),
        '?' => array(' ### ', '#   #', '  ## ', '     ', '  #  '),
        "'" => array(' #   ', ' #   ', '     ', '     ', '     '),
    );
}

/** Keep a custom title readable even when it is too long or non-Latin. */
function wct_logo_fallback($name, $mode) {
    wct_line(wct_paint(str_repeat('=', 60), 'heading', $mode));
    wct_line(wct_paint(wct_wrap_line($name, 76, '  ', '  '), 'heading', $mode));
    wct_line(wct_paint(str_repeat('=', 60), 'heading', $mode));
}

function wct_logo($mode) {
    $name = trim(wct_safe_text(wp_strip_all_tags(get_bloginfo('name'))));
    if ($name === '') {
        $name = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    }
    if ($name === '') {
        $name = 'WordPress';
    }

    // WordPress handles common accented Latin characters; unsupported scripts
    // remain intact in the fallback banner, without misrepresenting the name.
    $ascii = strtoupper(preg_replace('/\s+/u', ' ', trim(remove_accents($name))));
    $font = wct_logo_font();
    if ($ascii === '' || strlen($ascii) > 36 || preg_match('/[^\x20-\x7E]/', $ascii)) {
        wct_logo_fallback($name, $mode);
        return;
    }
    foreach (str_split($ascii) as $character) {
        if (!isset($font[$character])) {
            wct_logo_fallback($name, $mode);
            return;
        }
    }

    // Preserve word boundaries when possible, with a maximum of 12 glyphs
    // per row (71 columns), and split long single words if needed.
    $chunks = array();
    $current = '';
    foreach (explode(' ', $ascii) as $word) {
        if ($word === '') {
            continue;
        }
        while (strlen($word) > 12) {
            if ($current !== '') {
                $chunks[] = $current;
                $current = '';
            }
            $chunks[] = substr($word, 0, 12);
            $word = substr($word, 12);
        }
        if ($word === '') {
            continue;
        }
        if ($current !== '' && strlen($current) + strlen($word) + 1 > 12) {
            $chunks[] = $current;
            $current = '';
        }
        $current .= ($current === '' ? '' : ' ') . $word;
    }
    if ($current !== '') {
        $chunks[] = $current;
    }
    if (!$chunks || count($chunks) > 3) {
        wct_logo_fallback($name, $mode);
        return;
    }

    foreach ($chunks as $index => $chunk) {
        if ($index > 0) {
            wct_line();
        }
        $lines = array_fill(0, 5, '');
        foreach (str_split($chunk) as $character) {
            foreach ($font[$character] as $row => $glyph) {
                $lines[$row] .= $glyph . ' ';
            }
        }
        foreach ($lines as $line) {
            wct_line(wct_paint(rtrim($line), 'heading', $mode));
        }
    }
}

function wct_section($name, $mode) {
    wct_line();
    wct_line(wct_paint(strtoupper($name), 'accent', $mode));
    wct_line(wct_paint(str_repeat('-', 40), 'muted', $mode));
}

/** Compact listings: no theme/shortcode content, just dates, titles and optional URLs. */
function wct_list_posts($posts, $mode) {
    $options = wct_options();
    $count = 0;
    foreach ($posts as $post) {
        if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || post_password_required($post)) {
            continue;
        }
        ++$count;
        $title = wct_safe_text(get_the_title($post));
        $date = get_the_date('d/m/Y', $post);
        wct_line(wct_paint(wct_wrap_line($title, 76, sprintf('%2d  %s  ', $count, $date), '                '), 'accent', $mode));
        if ($options['show_urls'] === '1') {
            wct_line('    ' . wct_safe_text(get_permalink($post)));
        }
    }
    if (!$count) {
        wct_line(__( 'No public posts available.', 'wp-curl-terminal' ));
    }
}

function wct_render_single($post, $mode) {
    if ($post->post_status !== 'publish') {
        wct_line(__( 'Content unavailable.', 'wp-curl-terminal' ));
        return;
    }
    wct_heading(get_the_title($post), $mode);
    wct_line();
    if ($post->post_type === 'post') {
        $author = get_the_author_meta('display_name', (int) $post->post_author);
        $metadata = sprintf(__( 'Published: %s', 'wp-curl-terminal' ), get_the_date('d/m/Y', $post));
        if ($author !== '') {
            $metadata .= sprintf(__( ' | Author: %s', 'wp-curl-terminal' ), wct_safe_text($author));
        }
        wct_line(wct_paint($metadata, 'muted', $mode));
        wct_line();
    }
    if (post_password_required($post)) {
        wct_line(__( 'This content is password protected. Open it in a browser.', 'wp-curl-terminal' ));
    } else {
        // Saved HTML is rendered as text without executing dynamic blocks or shortcodes.
        $plain = wct_html_to_text($post->post_content);
        wct_render_body($plain !== '' ? $plain : __( '(No text content)', 'wp-curl-terminal' ), $mode);
    }
    wct_section(__( 'Links', 'wp-curl-terminal' ), $mode);
    $link = wct_safe_text(get_permalink($post));
    wct_line(sprintf(__( 'Web version: %s', 'wp-curl-terminal' ), $link));
    wct_line(sprintf(__( 'Homepage: %s', 'wp-curl-terminal' ), wct_safe_text(home_url('/'))));
    if ($post->post_type === 'post') {
        $categories = get_the_category($post->ID);
        if (is_array($categories)) {
            $shown = 0;
            foreach ($categories as $category) {
                if ($shown >= 2) { break; }
                $caturl = get_category_link($category->term_id);
                if (!is_wp_error($caturl)) {
                    wct_line(sprintf(__( 'Category: %1$s (%2$s)', 'wp-curl-terminal' ), wct_safe_text($category->name), wct_safe_text($caturl)));
                    ++$shown;
                }
            }
        }
    }
    wct_line(sprintf(__( 'Original HTML: curl -L -H "X-Original: true" %s', 'wp-curl-terminal' ), $link));
}

function wct_render_home($mode) {
    $options = wct_options();
    if ($options['ascii_logo'] === '1') {
        wct_logo($mode);
    } else {
        wct_heading(get_bloginfo('name'), $mode);
    }
    wct_line(wct_paint(wct_safe_text(get_bloginfo('name')), 'accent', $mode));
    $description = wct_safe_text(get_bloginfo('description'));
    if ($description !== '') {
        wct_line(wct_paint($description, 'muted', $mode));
    }
    wct_section(__( 'Latest posts', 'wp-curl-terminal' ), $mode);
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'has_password' => false,
        'posts_per_page' => (int) $options['posts_per_page'],
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ));
    wct_list_posts($query->posts, $mode);
    wp_reset_postdata();

    wct_section(__( 'Explore', 'wp-curl-terminal' ), $mode);
    wct_line('$ curl -L ' . wct_safe_text(home_url('/')));
    wct_line("$ curl -L '" . wct_safe_text(add_query_arg('s', 'python', home_url('/'))) . "'");
    wct_line("$ curl -L '" . wct_safe_text(add_query_arg('terminal', 'plain', home_url('/'))) . "'");
    wct_line(__( 'For posts: curl -L <post URL>', 'wp-curl-terminal' ));
}

function wct_render_archive($mode) {
    global $wp_query;
    if (is_search()) {
        $title = sprintf(__( 'Search: %s', 'wp-curl-terminal' ), get_search_query(false));
    } elseif (is_category() || is_tag()) {
        $title = single_term_title('', false);
    } elseif (is_author()) {
        $title = get_the_author_meta('display_name', (int) get_query_var('author'));
    } else {
        $title = wp_get_document_title();
    }
    wct_heading($title, $mode);
    wct_line();
    wct_list_posts($wp_query->posts, $mode);
    if ((int) $wp_query->max_num_pages > 1) {
        wct_line(sprintf(__( 'Page %1$d of %2$d', 'wp-curl-terminal' ), max(1, (int) get_query_var('paged')), (int) $wp_query->max_num_pages));
        $next = get_next_posts_page_link();
        if ($next) {
            wct_line(sprintf(__( 'Next page: %s', 'wp-curl-terminal' ), wct_safe_text($next)));
        }
    }
}

function wct_render() {
    $mode = wct_request_mode();
    if ($mode === '') {
        return;
    }
    status_header(is_404() ? 404 : 200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: User-Agent, Accept, X-Original');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
        return;
    }
    if (is_404()) {
        wct_heading(__( '404 | Page not found', 'wp-curl-terminal' ), $mode);
    } elseif (is_front_page() || is_home()) {
        wct_render_home($mode);
    } elseif (is_singular(array('post', 'page'))) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            wct_render_single($post, $mode);
        }
    } else {
        wct_render_archive($mode);
    }
    wct_line();
    wct_line(wct_paint(sprintf(__( 'Website: %s', 'wp-curl-terminal' ), home_url('/')), 'muted', $mode));
    if (is_front_page() || is_home() || is_404()) {
        wct_line(wct_paint(sprintf(__( 'Original HTML: curl -L -H "X-Original: true" %s', 'wp-curl-terminal' ), home_url('/')), 'muted', $mode));
    }
}

function wct_sanitize_settings($raw) {
    $raw = is_array($raw) ? $raw : array();
    $colors = array('cyan', 'green', 'amber', 'mono');
    return array(
        'enabled'        => !empty($raw['enabled']) ? '1' : '0',
        'autodetect'     => !empty($raw['autodetect']) ? '1' : '0',
        'color'          => in_array($raw['color'] ?? '', $colors, true) ? $raw['color'] : 'cyan',
        'posts_per_page' => max(1, min(30, (int) ($raw['posts_per_page'] ?? 10))),
        'show_urls'      => !empty($raw['show_urls']) ? '1' : '0',
        'ascii_logo'     => !empty($raw['ascii_logo']) ? '1' : '0',
    );
}

function wct_register_settings() {
    register_setting('wct_group', 'wct_settings', array(
        'type' => 'array',
        'sanitize_callback' => 'wct_sanitize_settings',
        'default' => wct_defaults(),
    ));
}
add_action('admin_init', 'wct_register_settings');

function wct_admin_menu() {
    add_options_page('WP Curl Terminal', 'Curl Terminal', 'manage_options', 'wp-curl-terminal', 'wct_settings_page');
}
add_action('admin_menu', 'wct_admin_menu');

/**
 * Make settings easily discoverable on Plugins > Installed Plugins.
 * The action link appears under the plugin name, and the meta link
 * appears directly below the description, next to the other plugin details.
 */
function wct_plugin_settings_link() {
    return '<a href="' . esc_url(admin_url('options-general.php?page=wp-curl-terminal')) . '">' .
        esc_html__('Settings', 'wp-curl-terminal') . '</a>';
}

function wct_plugin_action_links($links) {
    if (!current_user_can('manage_options')) {
        return $links;
    }
    array_unshift($links, wct_plugin_settings_link());
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wct_plugin_action_links');

function wct_plugin_row_meta($links, $file) {
    if ($file !== plugin_basename(__FILE__) || !current_user_can('manage_options')) {
        return $links;
    }
    $links[] = wct_plugin_settings_link();
    return $links;
}
add_filter('plugin_row_meta', 'wct_plugin_row_meta', 10, 2);

function wct_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $options = wct_options();
    ?>
    <div class="wrap">
        <h1>WP Curl Terminal</h1>
        <p><?php esc_html_e('Automatically serve terminal-friendly ANSI content to curl visitors without modifying your WordPress theme.', 'wp-curl-terminal'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('wct_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Activation', 'wp-curl-terminal'); ?></th>
                    <td><label><input type="checkbox" name="wct_settings[enabled]" value="1" <?php checked($options['enabled'], '1'); ?>> <?php esc_html_e('Enable terminal pages', 'wp-curl-terminal'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic detection', 'wp-curl-terminal'); ?></th>
                    <td><label><input type="checkbox" name="wct_settings[autodetect]" value="1" <?php checked($options['autodetect'], '1'); ?>> <?php esc_html_e('Recognize the curl User-Agent', 'wp-curl-terminal'); ?></label>
                    <p class="description"><?php esc_html_e('You can also request terminal output using ?terminal=1 or Accept: text/x-ansi.', 'wp-curl-terminal'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wct_color"><?php esc_html_e('ANSI colors', 'wp-curl-terminal'); ?></label></th>
                    <td><select id="wct_color" name="wct_settings[color]">
                        <?php foreach (array(
                            'cyan' => __('Cyan and yellow', 'wp-curl-terminal'),
                            'green' => __('Green', 'wp-curl-terminal'),
                            'amber' => __('Amber', 'wp-curl-terminal'),
                            'mono' => __('Monochrome', 'wp-curl-terminal'),
                        ) as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($options['color'], $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></td>
                </tr>
                <tr>
                    <th scope="row"><label for="wct_posts"><?php esc_html_e('Posts on the homepage', 'wp-curl-terminal'); ?></label></th>
                    <td><input id="wct_posts" type="number" min="1" max="30" name="wct_settings[posts_per_page]" value="<?php echo esc_attr($options['posts_per_page']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Site ASCII logo', 'wp-curl-terminal'); ?></th>
                    <td><label><input type="checkbox" name="wct_settings[ascii_logo]" value="1" <?php checked($options['ascii_logo'], '1'); ?>> <?php esc_html_e('Generate the banner from the WordPress site title', 'wp-curl-terminal'); ?></label>
                    <p class="description"><?php esc_html_e('Uses the site title from Settings > General. Long names or unsupported characters fall back to a compact banner.', 'wp-curl-terminal'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Links', 'wp-curl-terminal'); ?></th>
                    <td><label><input type="checkbox" name="wct_settings[show_urls]" value="1" <?php checked($options['show_urls'], '1'); ?>> <?php esc_html_e('Show full URLs in post listings', 'wp-curl-terminal'); ?></label></td>
                </tr>
            </table>
            <?php submit_button(__('Save settings', 'wp-curl-terminal')); ?>
        </form>
        <h2><?php esc_html_e('Try it now', 'wp-curl-terminal'); ?></h2>
        <p><code>curl -L <?php echo esc_html(home_url('/')); ?></code></p>
        <p><code>curl -L '<?php echo esc_html(add_query_arg('terminal', 'plain', home_url('/'))); ?>'</code> (<?php esc_html_e('without ANSI codes', 'wp-curl-terminal'); ?>)</p>
        <p><code>curl -L -H 'X-Original: true' <?php echo esc_html(home_url('/')); ?></code> (<?php esc_html_e('original HTML', 'wp-curl-terminal'); ?>)</p>
        <p><a href="<?php echo esc_url(add_query_arg('terminal', 'plain', home_url('/'))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview plain text in your browser', 'wp-curl-terminal'); ?></a></p>
        <p><?php esc_html_e('Shortcodes and dynamic blocks are not executed in terminal mode. Content produced by other plugins or page builders may be only partially rendered.', 'wp-curl-terminal'); ?></p>
        <p><strong><?php esc_html_e('Cache', 'wp-curl-terminal'); ?>:</strong> <?php esc_html_e('If you use a caching plugin, CDN, or hosting HTML cache, configure a bypass for curl requests and ?terminal=. No-cache headers alone cannot bypass a cache that responds before WordPress runs.', 'wp-curl-terminal'); ?></p>
    </div>
    <?php
}
