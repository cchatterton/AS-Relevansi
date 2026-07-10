<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = (array) get_option('wp7rss_settings', array());
if (empty($settings['delete_data_on_uninstall'])) {
    return;
}

global $wpdb;
delete_option('wp7rss_settings');
delete_option('wp7rss_block_defaults');
delete_option('wp7rss_search_bot_settings');
delete_option('wp7rss_ai_status');
delete_option('wp7rss_topic_map_status');

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp7rss_ai_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp7rss_topic_map");
