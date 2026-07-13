<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp7rss_activate() {
    add_option('wp7rss_settings', wp7rss_default_settings());
    add_option('wp7rss_block_defaults', wp7rss_default_block_settings());
    add_option('wp7rss_search_bot_settings', wp7rss_default_bot_settings());
    add_option('wp7rss_ai_status', wp7rss_get_ai_connector_status());
    add_option('wp7rss_topic_map_status', array(
        'status' => wp7rss_ai_connector_available() ? 'pending_initial_build' : 'disabled_no_ai_connector',
        'plugin_version' => WP7RSS_VERSION,
    ));

    wp7rss_create_tables();
    wp7rss_register_cron_hooks();

    if (wp7rss_ai_connector_available() && !wp_next_scheduled('wp7rss_build_topic_map')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'wp7rss_build_topic_map', array('activation'));
    }

    if (!wp_next_scheduled('wp7rss_cleanup_ai_logs')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wp7rss_cleanup_ai_logs');
    }
}

function wp7rss_deactivate() {
    wp_clear_scheduled_hook('wp7rss_build_topic_map');
    wp_clear_scheduled_hook('wp7rss_cleanup_ai_logs');
}

function wp7rss_register_cron_hooks() {
    if (!wp_next_scheduled('wp7rss_cleanup_ai_logs')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wp7rss_cleanup_ai_logs');
    }
}

function wp7rss_create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $logs = $wpdb->prefix . 'wp7rss_ai_logs';
    $topic = $wpdb->prefix . 'wp7rss_topic_map';

    dbDelta("CREATE TABLE $logs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        call_id varchar(64) NOT NULL,
        call_type varchar(80) NOT NULL,
        created_at datetime NOT NULL,
        trigger_source varchar(80) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        search_query text NULL,
        source_page_url text NULL,
        source_page_title text NULL,
        block_instance_id varchar(120) NOT NULL DEFAULT '',
        is_search_bot tinyint(1) NOT NULL DEFAULT 0,
        connector_name varchar(120) NOT NULL DEFAULT '',
        connector_provider varchar(120) NOT NULL DEFAULT '',
        connector_model varchar(120) NOT NULL DEFAULT '',
        status varchar(80) NOT NULL DEFAULT '',
        prompt longtext NULL,
        request_packet longtext NULL,
        raw_response longtext NULL,
        parsed_response longtext NULL,
        semantic_terms longtext NULL,
        corrected_query text NULL,
        intent_summary text NULL,
        error_code varchar(120) NOT NULL DEFAULT '',
        error_message text NULL,
        retry_count int unsigned NOT NULL DEFAULT 0,
        timeout_ms int unsigned NOT NULL DEFAULT 0,
        duration_ms int unsigned NOT NULL DEFAULT 0,
        token_input int unsigned NULL,
        token_output int unsigned NULL,
        token_total int unsigned NULL,
        estimated_cost decimal(12,6) NULL,
        cache_status varchar(40) NOT NULL DEFAULT '',
        response_used tinyint(1) NOT NULL DEFAULT 0,
        rejection_reason text NULL,
        plugin_version varchar(40) NOT NULL DEFAULT '',
        wordpress_version varchar(40) NOT NULL DEFAULT '',
        relevanssi_status varchar(80) NOT NULL DEFAULT '',
        ai_connector_status varchar(80) NOT NULL DEFAULT '',
        topic_map_version varchar(80) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY call_type (call_type),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset;");

    dbDelta("CREATE TABLE $topic (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        version_hash varchar(80) NOT NULL DEFAULT '',
        status varchar(40) NOT NULL DEFAULT '',
        topic_map longtext NULL,
        source_packet longtext NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY version_hash (version_hash),
        KEY status (status)
    ) $charset;");
}

function wp7rss_build_topic_map($source = 'scheduled') {
    if (!wp7rss_ai_connector_available()) {
        update_option('wp7rss_topic_map_status', array_merge(wp7rss_get_topic_status(), array(
            'status' => 'disabled_no_ai_connector',
            'last_attempt' => current_time('mysql'),
            'plugin_version' => WP7RSS_VERSION,
        )));
        return;
    }

    $started = microtime(true);
    $status = wp7rss_get_topic_status();
    update_option('wp7rss_topic_map_status', array_merge($status, array(
        'status' => 'building',
        'last_attempt' => current_time('mysql'),
        'last_error' => '',
    )));

    $packet = wp7rss_collect_site_vocabulary_packet();
    $response = wp7rss_ai_build_topic_map_response($packet);
    $connector = wp7rss_get_ai_connector_status();

    if (!is_array($response) || empty($response['topics']) || !is_array($response['topics'])) {
        $error_code = is_wp_error($response) ? $response->get_error_code() : 'invalid_response';
        $error_message = is_wp_error($response) ? $response->get_error_message() : __('AI Connector returned an invalid topic map response.', WP7RSS_TEXT_DOMAIN);
        $latest_ready = wp7rss_get_latest_ready_topic_map_record();
        update_option('wp7rss_topic_map_status', array_merge(wp7rss_get_topic_status(), array(
            'status' => $latest_ready ? 'ready' : 'failed',
            'last_attempt' => current_time('mysql'),
            'source_items' => count($packet['items']),
            'source_terms' => count($packet['terms']),
            'last_error' => $error_message,
            'plugin_version' => WP7RSS_VERSION,
        )));
        wp7rss_log_ai_call(array(
            'call_type' => 'topic_map_build',
            'trigger_source' => sanitize_text_field($source),
            'connector_name' => $connector['name'],
            'connector_provider' => $connector['provider'],
            'connector_model' => $connector['model'],
            'status' => 'invalid_response',
            'request_packet' => $packet,
            'raw_response' => $response,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'timeout_ms' => absint(wp7rss_get_settings()['topic_map_timeout_ms']),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ));
        return;
    }

    global $wpdb;
    $hash = md5(wp_json_encode($response));
    $table = $wpdb->prefix . 'wp7rss_topic_map';
    $wpdb->insert($table, array(
        'version_hash' => $hash,
        'status' => 'ready',
        'topic_map' => wp_json_encode($response),
        'source_packet' => wp_json_encode($packet),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ));

    update_option('wp7rss_topic_map_status', array(
        'status' => 'ready',
        'last_build' => current_time('mysql'),
        'last_success' => current_time('mysql'),
        'last_attempt' => current_time('mysql'),
        'source_items' => count($packet['items']),
        'source_terms' => count($packet['terms']),
        'topic_count' => count($response['topics']),
        'protected_terms_count' => empty($response['protected_terms']) ? 0 : count((array) $response['protected_terms']),
        'version_hash' => $hash,
        'last_error' => '',
        'plugin_version' => WP7RSS_VERSION,
    ));

    wp7rss_log_ai_call(array(
        'call_type' => 'topic_map_build',
        'trigger_source' => sanitize_text_field($source),
        'connector_name' => $connector['name'],
        'connector_provider' => $connector['provider'],
        'connector_model' => $connector['model'],
        'status' => 'success',
        'request_packet' => $packet,
        'raw_response' => $response,
        'parsed_response' => $response,
        'timeout_ms' => absint(wp7rss_get_settings()['topic_map_timeout_ms']),
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        'response_used' => 1,
    ));
}

function wp7rss_collect_site_vocabulary_packet() {
    $post_types = get_post_types(array('public' => true, 'exclude_from_search' => false), 'objects');
    $items = array();
    $terms = array();

    foreach ($post_types as $post_type => $object) {
        $terms[] = $object->labels->name;
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        foreach ($posts as $post_id) {
            $title = get_the_title($post_id);
            $slug = get_post_field('post_name', $post_id);
            $items[] = array('id' => $post_id, 'type' => $post_type, 'title' => $title, 'slug' => $slug);
            $terms[] = $title;
            $terms[] = str_replace('-', ' ', $slug);
        }
    }

    foreach (get_taxonomies(array('public' => true), 'objects') as $taxonomy => $object) {
        $terms[] = $object->labels->name;
        $taxonomy_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 200));
        if (is_wp_error($taxonomy_terms)) {
            continue;
        }
        foreach ($taxonomy_terms as $term) {
            $terms[] = $term->name;
            if (!empty($term->description)) {
                $terms[] = $term->description;
            }
        }
    }

    $terms = wp7rss_sanitize_text_list($terms);

    return array(
        'call_type' => 'site_topic_map_build',
        'site_name' => get_bloginfo('name'),
        'site_url' => home_url('/'),
        'locale' => get_locale(),
        'searchable_post_types' => array_keys($post_types),
        'items' => $items,
        'terms' => array_slice($terms, 0, 1000),
        'plugin_version' => WP7RSS_VERSION,
    );
}

function wp7rss_cleanup_ai_logs() {
    global $wpdb;
    $settings = wp7rss_get_settings();
    $table = $wpdb->prefix . 'wp7rss_ai_logs';
    $days = max(1, absint($settings['log_retention_days']));
    $count = max(1, absint($settings['log_retention_count']));

    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)", current_time('mysql'), $days));
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table ORDER BY created_at DESC LIMIT 18446744073709551615 OFFSET %d", $count));
    if (!empty($ids)) {
        $ids = array_map('absint', $ids);
        $wpdb->query("DELETE FROM $table WHERE id IN (" . implode(',', $ids) . ")");
    }
}
