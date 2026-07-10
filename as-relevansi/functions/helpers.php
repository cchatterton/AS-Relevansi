<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp7rss_bootstrap() {
    add_action('init', 'wp7rss_register_search_block');
    add_action('init', 'wp7rss_register_cron_hooks');
    add_action('wp7rss_build_topic_map', 'wp7rss_build_topic_map');
    add_action('wp7rss_cleanup_ai_logs', 'wp7rss_cleanup_ai_logs');
    add_action('save_post', 'wp7rss_mark_topic_map_stale_on_content_change', 10, 3);
    add_action('pre_get_posts', 'wp7rss_prepare_search_expansion_context', 20);
}

function wp7rss_default_settings() {
    return array(
        'semantic_enabled' => 0,
        'ai_integration_enabled' => 1,
        'ai_timeout_ms' => 2500,
        'max_semantic_terms' => 8,
        'cache_duration_hours' => 24,
        'logging_mode' => 'metadata',
        'log_retention_count' => 500,
        'log_retention_days' => 30,
        'delete_data_on_uninstall' => 0,
        'scheduled_topic_refresh' => 0,
        'topic_refresh_frequency' => 'manual',
        'topic_content_change_behaviour' => 'mark_stale',
    );
}

function wp7rss_default_block_settings() {
    return array(
        'placeholder' => __('Search this site...', WP7RSS_TEXT_DOMAIN),
        'button_label' => __('Search', WP7RSS_TEXT_DOMAIN),
        'heading' => '',
        'intro' => '',
        'intent_label' => __('general site search', WP7RSS_TEXT_DOMAIN),
        'css_class' => '',
        'results_url' => '',
    );
}

function wp7rss_default_bot_settings() {
    return array(
        'enabled' => 0,
        'delay_seconds' => 8,
        'image_id' => 0,
        'image_alt' => __('Search assistant', WP7RSS_TEXT_DOMAIN),
        'bubble_text' => __('Can I help you find something?', WP7RSS_TEXT_DOMAIN),
        'placeholder' => __('Search this site...', WP7RSS_TEXT_DOMAIN),
        'button_label' => __('Search', WP7RSS_TEXT_DOMAIN),
        'position' => 'bottom-left',
        'hide_mobile' => 1,
        'mobile_delay_seconds' => 8,
        'remember_dismissal' => 'session',
        'excluded_urls' => '',
    );
}

function wp7rss_get_settings() {
    return wp_parse_args((array) get_option('wp7rss_settings', array()), wp7rss_default_settings());
}

function wp7rss_get_block_defaults() {
    return wp_parse_args((array) get_option('wp7rss_block_defaults', array()), wp7rss_default_block_settings());
}

function wp7rss_get_bot_settings() {
    return wp_parse_args((array) get_option('wp7rss_search_bot_settings', array()), wp7rss_default_bot_settings());
}

function wp7rss_get_topic_status() {
    return wp_parse_args((array) get_option('wp7rss_topic_map_status', array()), array(
        'status' => 'not_started',
        'last_build' => '',
        'last_success' => '',
        'last_attempt' => '',
        'source_items' => 0,
        'source_terms' => 0,
        'topic_count' => 0,
        'protected_terms_count' => 0,
        'version_hash' => '',
        'last_error' => '',
        'plugin_version' => WP7RSS_VERSION,
    ));
}

function wp7rss_is_relevanssi_active() {
    return function_exists('relevanssi_do_query') || function_exists('relevanssi_search') || defined('RELEVANSSI_PREMIUM');
}

function wp7rss_get_ai_connector_status() {
    $default = array(
        'available' => false,
        'configured' => false,
        'callable' => false,
        'name' => '',
        'provider' => '',
        'model' => '',
        'message' => __('AI Connector not available.', WP7RSS_TEXT_DOMAIN),
    );

    $native_status = wp7rss_get_native_wp_ai_connector_status($default);
    if ($native_status['available']) {
        $default = $native_status;
    }

    /**
     * Lets the WP7/AlphaSys AI Connector expose status without hard coupling this plugin to
     * a specific connector class. Native WordPress 7 Connectors are detected before this
     * filter runs, so custom integrations can still override or enrich the status.
     */
    $status = apply_filters('wp7rss_ai_connector_status', $default);
    $status = is_array($status) ? wp_parse_args($status, $default) : $default;
    $status['available'] = (bool) $status['available'];
    $status['configured'] = (bool) $status['configured'];
    $status['callable'] = (bool) $status['callable'];

    return $status;
}

function wp7rss_get_native_wp_ai_connector_status($default) {
    if (!function_exists('wp_get_connectors') || !class_exists('\\WordPress\\AiClient\\AiClient')) {
        return $default;
    }

    try {
        $registry = \WordPress\AiClient\AiClient::defaultRegistry();
        foreach (wp_get_connectors() as $connector_id => $connector_data) {
            if ('ai_provider' !== ($connector_data['type'] ?? '')) {
                continue;
            }

            $has_provider = method_exists($registry, 'hasProvider') && $registry->hasProvider($connector_id);
            $is_configured = $has_provider && method_exists($registry, 'isProviderConfigured') && $registry->isProviderConfigured($connector_id);
            if (!$has_provider) {
                continue;
            }

            $name = sanitize_text_field($connector_data['name'] ?? $connector_id);
            if (!$is_configured) {
                $default = array_merge($default, array(
                    'available' => true,
                    'configured' => false,
                    'callable' => false,
                    'name' => $name,
                    'provider' => sanitize_key($connector_id),
                    'model' => '',
                    'message' => sprintf(
                        /* translators: %s: connector name. */
                        __('%s connector is registered but not configured.', WP7RSS_TEXT_DOMAIN),
                        $name
                    ),
                ));
                continue;
            }

            return array(
                'available' => true,
                'configured' => true,
                'callable' => true,
                'name' => $name,
                'provider' => sanitize_key($connector_id),
                'model' => '',
                'message' => sprintf(
                    /* translators: %s: connector name. */
                    __('%s connector is connected through native WordPress Connectors.', WP7RSS_TEXT_DOMAIN),
                    $name
                ),
            );
        }
    } catch (Throwable $e) {
        return array_merge($default, array(
            'message' => sanitize_text_field($e->getMessage()),
        ));
    }

    return $default;
}

function wp7rss_ai_connector_available() {
    $settings = wp7rss_get_settings();
    $status = wp7rss_get_ai_connector_status();

    return !empty($settings['ai_integration_enabled']) && $status['available'] && $status['configured'] && $status['callable'];
}

function wp7rss_sanitize_text_list($value) {
    $items = is_array($value) ? $value : preg_split('/[\r\n,]+/', (string) $value);
    $items = array_map('sanitize_text_field', $items);
    $items = array_filter(array_map('trim', $items));

    return array_values(array_unique($items));
}

function wp7rss_validate_semantic_terms($terms, $limit = 8) {
    $valid = array();
    foreach ((array) $terms as $term) {
        $term = trim(wp_strip_all_tags((string) $term));
        if ('' === $term || strlen($term) > 80) {
            continue;
        }
        $valid[] = sanitize_text_field($term);
    }

    return array_slice(array_values(array_unique($valid)), 0, max(1, absint($limit)));
}

function wp7rss_get_results_action_url($override = '') {
    $override = trim((string) $override);
    if ('' !== $override) {
        return esc_url_raw($override);
    }

    $defaults = wp7rss_get_block_defaults();
    if (!empty($defaults['results_url'])) {
        return esc_url_raw($defaults['results_url']);
    }

    return home_url('/');
}

function wp7rss_log_ai_call($data) {
    $settings = wp7rss_get_settings();
    if ('off' === $settings['logging_mode']) {
        return 0;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wp7rss_ai_logs';
    $full_debug = 'full' === $settings['logging_mode'];
    $record = wp_parse_args((array) $data, array(
        'call_id' => wp_generate_uuid4(),
        'call_type' => '',
        'trigger_source' => '',
        'user_id' => get_current_user_id(),
        'search_query' => '',
        'source_page_url' => '',
        'source_page_title' => '',
        'block_instance_id' => '',
        'is_search_bot' => 0,
        'connector_name' => '',
        'connector_provider' => '',
        'connector_model' => '',
        'status' => 'failed',
        'prompt' => '',
        'request_packet' => '',
        'raw_response' => '',
        'parsed_response' => '',
        'semantic_terms' => '',
        'corrected_query' => '',
        'intent_summary' => '',
        'error_code' => '',
        'error_message' => '',
        'retry_count' => 0,
        'timeout_ms' => 0,
        'duration_ms' => 0,
        'token_input' => null,
        'token_output' => null,
        'token_total' => null,
        'estimated_cost' => null,
        'cache_status' => '',
        'response_used' => 0,
        'rejection_reason' => '',
        'relevanssi_status' => wp7rss_is_relevanssi_active() ? 'active' : 'missing',
        'ai_connector_status' => wp7rss_ai_connector_available() ? 'available' : 'unavailable',
        'topic_map_version' => wp7rss_get_topic_status()['version_hash'],
    ));

    if (!$full_debug) {
        $record['prompt'] = '';
        $record['request_packet'] = '';
        $record['raw_response'] = '';
        $record['parsed_response'] = '';
    }

    $wpdb->insert($table, array(
        'call_id' => sanitize_text_field($record['call_id']),
        'call_type' => sanitize_key($record['call_type']),
        'created_at' => current_time('mysql'),
        'trigger_source' => sanitize_text_field($record['trigger_source']),
        'user_id' => absint($record['user_id']),
        'search_query' => sanitize_text_field($record['search_query']),
        'source_page_url' => esc_url_raw($record['source_page_url']),
        'source_page_title' => sanitize_text_field($record['source_page_title']),
        'block_instance_id' => sanitize_text_field($record['block_instance_id']),
        'is_search_bot' => empty($record['is_search_bot']) ? 0 : 1,
        'connector_name' => sanitize_text_field($record['connector_name']),
        'connector_provider' => sanitize_text_field($record['connector_provider']),
        'connector_model' => sanitize_text_field($record['connector_model']),
        'status' => sanitize_key($record['status']),
        'prompt' => wp_json_encode($record['prompt']),
        'request_packet' => wp_json_encode($record['request_packet']),
        'raw_response' => wp_json_encode($record['raw_response']),
        'parsed_response' => wp_json_encode($record['parsed_response']),
        'semantic_terms' => wp_json_encode($record['semantic_terms']),
        'corrected_query' => sanitize_text_field($record['corrected_query']),
        'intent_summary' => sanitize_text_field($record['intent_summary']),
        'error_code' => sanitize_key($record['error_code']),
        'error_message' => sanitize_text_field($record['error_message']),
        'retry_count' => absint($record['retry_count']),
        'timeout_ms' => absint($record['timeout_ms']),
        'duration_ms' => absint($record['duration_ms']),
        'token_input' => null === $record['token_input'] ? null : absint($record['token_input']),
        'token_output' => null === $record['token_output'] ? null : absint($record['token_output']),
        'token_total' => null === $record['token_total'] ? null : absint($record['token_total']),
        'estimated_cost' => null === $record['estimated_cost'] ? null : (float) $record['estimated_cost'],
        'cache_status' => sanitize_key($record['cache_status']),
        'response_used' => empty($record['response_used']) ? 0 : 1,
        'rejection_reason' => sanitize_text_field($record['rejection_reason']),
        'plugin_version' => WP7RSS_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'relevanssi_status' => sanitize_text_field($record['relevanssi_status']),
        'ai_connector_status' => sanitize_text_field($record['ai_connector_status']),
        'topic_map_version' => sanitize_text_field($record['topic_map_version']),
    ));

    return (int) $wpdb->insert_id;
}

function wp7rss_prepare_search_expansion_context($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $original_query = (string) $query->get('s');
    if ('' === trim($original_query)) {
        return;
    }

    $settings = wp7rss_get_settings();
    $topic = wp7rss_get_topic_status();
    if (empty($settings['semantic_enabled']) || !wp7rss_is_relevanssi_active() || !wp7rss_ai_connector_available() || 'ready' !== $topic['status']) {
        return;
    }

    $cache_key = 'wp7rss_expansion_' . md5(strtolower($original_query) . '|' . get_locale() . '|' . $topic['version_hash']);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        $GLOBALS['wp7rss_search_context'] = array(
            'original_query' => $original_query,
            'semantic_terms' => wp7rss_validate_semantic_terms($cached, $settings['max_semantic_terms']),
            'cache_hit' => true,
        );
        return;
    }

    $started = microtime(true);
    $connector = wp7rss_get_ai_connector_status();
    $packet = array(
        'call_type' => 'live_search_query_expansion',
        'original_query' => $original_query,
        'site_name' => get_bloginfo('name'),
        'site_url' => home_url('/'),
        'locale' => get_locale(),
        'max_semantic_terms' => absint($settings['max_semantic_terms']),
        'topic_map_version' => $topic['version_hash'],
    );

    $response = apply_filters('wp7rss_ai_expand_search_query', null, $packet);
    if (!is_array($response) || empty($response['semantic_terms'])) {
        wp7rss_log_ai_call(array(
            'call_type' => 'live_search_query_expansion',
            'trigger_source' => 'search',
            'search_query' => $original_query,
            'connector_name' => $connector['name'],
            'connector_provider' => $connector['provider'],
            'connector_model' => $connector['model'],
            'status' => 'invalid_response',
            'request_packet' => $packet,
            'raw_response' => $response,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'timeout_ms' => absint($settings['ai_timeout_ms']),
        ));
        return;
    }

    $terms = wp7rss_validate_semantic_terms($response['semantic_terms'], $settings['max_semantic_terms']);
    if (empty($terms)) {
        return;
    }

    set_transient($cache_key, $terms, HOUR_IN_SECONDS * absint($settings['cache_duration_hours']));
    $GLOBALS['wp7rss_search_context'] = array(
        'original_query' => $original_query,
        'semantic_terms' => $terms,
        'cache_hit' => false,
    );

    wp7rss_log_ai_call(array(
        'call_type' => 'live_search_query_expansion',
        'trigger_source' => 'search',
        'search_query' => $original_query,
        'connector_name' => $connector['name'],
        'connector_provider' => $connector['provider'],
        'connector_model' => $connector['model'],
        'status' => 'success',
        'request_packet' => $packet,
        'raw_response' => $response,
        'parsed_response' => $response,
        'semantic_terms' => $terms,
        'corrected_query' => isset($response['corrected_query']) ? $response['corrected_query'] : '',
        'intent_summary' => isset($response['intent_summary']) ? $response['intent_summary'] : '',
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        'timeout_ms' => absint($settings['ai_timeout_ms']),
        'cache_status' => 'miss',
        'response_used' => 1,
    ));

    /**
     * Integration point for a site-specific Relevanssi adapter. The public query remains
     * untouched; consumers receive internal semantic terms separately.
     */
    do_action('wp7rss_semantic_terms_ready', $terms, $original_query, $query);
}
