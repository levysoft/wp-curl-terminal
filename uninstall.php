<?php
/**
 * Remove the plugin settings only when WordPress explicitly uninstalls it.
 * Deactivation and regular upgrades preserve all existing settings.
 *
 * @package WP_Curl_Terminal
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// On multisite the plugin can be enabled separately on individual sites.
if (is_multisite()) {
    $offset = 0;
    $batch_size = 100;
    do {
        $sites = get_sites(array(
            'fields' => 'ids',
            'number' => $batch_size,
            'offset' => $offset,
        ));
        foreach ($sites as $blog_id) {
            switch_to_blog((int) $blog_id);
            delete_option('wct_settings');
            restore_current_blog();
        }
        $offset += count($sites);
    } while (count($sites) === $batch_size);
    delete_site_option('wct_settings');
} else {
    delete_option('wct_settings');
}
