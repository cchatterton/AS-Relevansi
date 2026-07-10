<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'wp7rss_register_rest_routes');

function wp7rss_register_rest_routes() {
    register_rest_route('wp7rss/v1', '/status', array(
        'methods' => 'GET',
        'callback' => 'wp7rss_rest_status',
        'permission_callback' => 'wp7rss_rest_permissions',
    ));
}

function wp7rss_rest_permissions() {
    return current_user_can('manage_options');
}

function wp7rss_rest_status() {
    return rest_ensure_response(array(
        'plugin_version' => WP7RSS_VERSION,
        'relevanssi_active' => wp7rss_is_relevanssi_active(),
        'ai_connector' => wp7rss_get_ai_connector_status(),
        'topic_map' => wp7rss_get_topic_status(),
    ));
}
