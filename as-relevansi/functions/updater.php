<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('pre_set_site_transient_update_plugins', 'wp7rss_add_github_update_data');
add_filter('site_transient_update_plugins', 'wp7rss_add_github_update_data');
add_filter('plugins_api', 'wp7rss_plugins_api', 10, 3);
add_filter('plugin_row_meta', 'wp7rss_plugin_row_meta', 10, 2);
add_action('admin_init', 'wp7rss_handle_update_check');
add_action('upgrader_process_complete', 'wp7rss_clear_github_release_cache', 10, 2);

function wp7rss_github_config() {
    return array(
        'owner' => 'cchatterton',
        'repo' => 'AS-Relevansi',
        'slug' => 'as-relevansi',
        'asset' => 'as-relevansi.zip',
        'release_transient' => 'wp7rss_github_latest_release',
        'error_transient' => 'wp7rss_github_latest_release_error',
    );
}

function wp7rss_add_github_update_data($transient) {
    if (!is_object($transient)) {
        $transient = new stdClass();
    }

    $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
    $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();

    $release = wp7rss_get_github_release();
    if (!$release || version_compare($release['version'], WP7RSS_VERSION, '<=')) {
        unset($transient->response[WP7RSS_PLUGIN_BASENAME], $transient->no_update[WP7RSS_PLUGIN_BASENAME]);
        return $transient;
    }

    $config = wp7rss_github_config();
    $transient->response[WP7RSS_PLUGIN_BASENAME] = (object) array(
        'id' => 'https://github.com/' . $config['owner'] . '/' . $config['repo'],
        'slug' => $config['slug'],
        'plugin' => WP7RSS_PLUGIN_BASENAME,
        'new_version' => $release['version'],
        'url' => $release['html_url'],
        'package' => $release['download_url'],
        'requires' => '6.0',
        'requires_php' => '8.1',
    );

    return $transient;
}

function wp7rss_get_github_release() {
    $config = wp7rss_github_config();
    $force = wp7rss_is_forced_update_check();

    if ($force) {
        delete_site_transient($config['release_transient']);
    }

    $cached = get_site_transient($config['release_transient']);
    if (is_array($cached)) {
        return $cached;
    }

    $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', rawurlencode($config['owner']), rawurlencode($config['repo']));
    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'headers' => array(
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Relevanssi-Extended/' . WP7RSS_VERSION,
        ),
    ));

    if (is_wp_error($response)) {
        set_site_transient($config['error_transient'], array('type' => 'wp_error', 'message' => $response->get_error_message()), 10 * MINUTE_IN_SECONDS);
        return false;
    }

    if (200 !== wp_remote_retrieve_response_code($response)) {
        set_site_transient($config['error_transient'], array('type' => 'http_error', 'code' => wp_remote_retrieve_response_code($response)), 10 * MINUTE_IN_SECONDS);
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || empty($body['tag_name'])) {
        return false;
    }

    $download = '';
    foreach ((array) ($body['assets'] ?? array()) as $asset) {
        if ($config['asset'] === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
            $download = esc_url_raw($asset['browser_download_url']);
            break;
        }
    }

    if (!$download) {
        return false;
    }

    $release = array(
        'version' => ltrim((string) $body['tag_name'], 'vV'),
        'html_url' => esc_url_raw($body['html_url'] ?? 'https://github.com/' . $config['owner'] . '/' . $config['repo']),
        'download_url' => $download,
        'body' => wp_kses_post($body['body'] ?? ''),
    );

    $ttl = version_compare($release['version'], WP7RSS_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
    set_site_transient($config['release_transient'], $release, $ttl);
    delete_site_transient($config['error_transient']);

    return $release;
}

function wp7rss_is_forced_update_check() {
    if (!current_user_can('update_plugins')) {
        return false;
    }

    $keys = array('force-check', 'action', 'action2');
    foreach ($keys as $key) {
        if (!empty($_REQUEST[$key])) {
            $value = sanitize_key(wp_unslash($_REQUEST[$key]));
            if (in_array($value, array('1', 'force-check', 'update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true)) {
                return true;
            }
        }
    }

    return false;
}

function wp7rss_plugins_api($result, $action, $args) {
    $config = wp7rss_github_config();
    if ('plugin_information' !== $action || empty($args->slug) || $config['slug'] !== $args->slug) {
        return $result;
    }

    $release = wp7rss_get_github_release();
    if (!$release) {
        return $result;
    }

    return (object) array(
        'name' => 'Relevanssi Extended',
        'slug' => $config['slug'],
        'version' => $release['version'],
        'author' => 'AlphaSys',
        'homepage' => 'https://github.com/' . $config['owner'] . '/' . $config['repo'],
        'download_link' => $release['download_url'],
        'requires' => '6.0',
        'requires_php' => '8.1',
        'sections' => array(
            'description' => __('Extends Relevanssi with optional semantic search tools.', WP7RSS_TEXT_DOMAIN),
            'changelog' => $release['body'],
        ),
    );
}

function wp7rss_plugin_row_meta($links, $file) {
    if (WP7RSS_PLUGIN_BASENAME !== $file || !current_user_can('update_plugins')) {
        return $links;
    }

    $config = wp7rss_github_config();
    $plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
    $check_url = wp_nonce_url(add_query_arg('wp7rss_check_updates', '1', $plugins_url), 'wp7rss_check_updates');
    $links[] = '<a href="' . esc_url('https://github.com/' . $config['owner'] . '/' . $config['repo']) . '">' . esc_html__('GitHub', WP7RSS_TEXT_DOMAIN) . '</a>';
    $links[] = '<a href="' . esc_url($check_url) . '">' . esc_html__('Check for updates', WP7RSS_TEXT_DOMAIN) . '</a>';

    return $links;
}

function wp7rss_handle_update_check() {
    if (empty($_GET['wp7rss_check_updates'])) {
        return;
    }

    if (!current_user_can('update_plugins') || !check_admin_referer('wp7rss_check_updates')) {
        wp_die(esc_html__('You are not allowed to check plugin updates.', WP7RSS_TEXT_DOMAIN));
    }

    wp7rss_clear_github_release_cache();
    delete_site_transient('update_plugins');
    wp_update_plugins();
    wp_safe_redirect(is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php'));
    exit;
}

function wp7rss_clear_github_release_cache($upgrader = null, $options = null) {
    $config = wp7rss_github_config();
    delete_site_transient($config['release_transient']);
    delete_site_transient($config['error_transient']);
}
