<?php
/**
 * Plugin Name: Relevanssi Extended
 * Plugin URI: https://github.com/cchatterton/AS-Relevansi/releases/latest
 * Description: Extends Relevanssi with a reusable search block, optional AI semantic expansion, a site topic map, search companion, and AI telemetry.
 * Version: 0.1.18
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Update URI: https://github.com/cchatterton/AS-Relevansi
 * Author: AlphaSys
 * Author URI: https://alphasys.com.au
 * Text Domain: as-relevansi
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP7RSS_VERSION', '0.1.18');
define('WP7RSS_PLUGIN_FILE', __FILE__);
define('WP7RSS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP7RSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP7RSS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP7RSS_TEXT_DOMAIN', 'as-relevansi');

require_once WP7RSS_PLUGIN_DIR . 'functions/helpers.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/ai-adapter.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/setup.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/assets.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/admin.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/rest.php';
require_once WP7RSS_PLUGIN_DIR . 'functions/updater.php';

register_activation_hook(__FILE__, 'wp7rss_activate');
register_deactivation_hook(__FILE__, 'wp7rss_deactivate');

add_action('plugins_loaded', 'wp7rss_bootstrap');
