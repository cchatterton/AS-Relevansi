<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp7rss_ai_build_topic_map_response($packet) {
    $filtered = apply_filters('wp7rss_ai_build_topic_map', null, $packet);
    if (null !== $filtered) {
        return $filtered;
    }

    return wp7rss_ai_generate_json(
        wp7rss_ai_topic_map_prompt($packet),
        wp7rss_ai_topic_map_schema(),
        'topic_map_build'
    );
}

function wp7rss_ai_expand_search_query_response($packet) {
    $filtered = apply_filters('wp7rss_ai_expand_search_query', null, $packet);
    if (null !== $filtered) {
        return $filtered;
    }

    return wp7rss_ai_generate_json(
        wp7rss_ai_search_expansion_prompt($packet),
        wp7rss_ai_search_expansion_schema(),
        'live_search_query_expansion'
    );
}

function wp7rss_ai_generate_json($prompt, $schema, $call_type) {
    if (!function_exists('wp_ai_client_prompt')) {
        return new WP_Error('wp_ai_client_missing', __('The native WordPress AI Client is not available.', WP7RSS_TEXT_DOMAIN));
    }

    $status = wp7rss_get_ai_connector_status();
    if (empty($status['provider'])) {
        return new WP_Error('wp_ai_provider_missing', __('No configured AI provider was found.', WP7RSS_TEXT_DOMAIN));
    }

    $settings = wp7rss_get_settings();
    $timeout = max(1, absint($settings['ai_timeout_ms']) / 1000);
    $timeout_filter = static function () use ($timeout) {
        return $timeout;
    };

    add_filter('wp_ai_client_default_request_timeout', $timeout_filter);
    try {
        $result = wp_ai_client_prompt($prompt)
            ->using_provider($status['provider'])
            ->using_temperature(0.1)
            ->using_max_tokens('topic_map_build' === $call_type ? 1400 : 500)
            ->as_json_response($schema)
            ->generate_text();
    } finally {
        remove_filter('wp_ai_client_default_request_timeout', $timeout_filter);
    }

    if (is_wp_error($result)) {
        return $result;
    }

    $parsed = wp7rss_ai_parse_json_response($result);
    if (is_wp_error($parsed)) {
        return $parsed;
    }

    return $parsed;
}

function wp7rss_ai_parse_json_response($response) {
    $text = trim((string) $response);
    if ('' === $text) {
        return new WP_Error('empty_ai_response', __('The AI Connector returned an empty response.', WP7RSS_TEXT_DOMAIN));
    }

    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches)) {
        $text = trim($matches[1]);
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if (false !== $start && false !== $end && $end > $start) {
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return new WP_Error('invalid_ai_json', __('The AI Connector did not return valid JSON.', WP7RSS_TEXT_DOMAIN), array('raw_response' => $response));
}

function wp7rss_ai_topic_map_prompt($packet) {
    $prompt_packet = $packet;
    $prompt_packet['items'] = array_slice((array) ($packet['items'] ?? array()), 0, 120);
    $prompt_packet['terms'] = array_slice((array) ($packet['terms'] ?? array()), 0, 300);

    return "You are preparing a semantic topic map for a WordPress site's internal search.\n"
        . "Return JSON only. Do not include Markdown.\n"
        . "Create up to 12 site topics from the provided searchable content. Keep terms useful for search expansion and preserve important product, service, place, brand, and entity names.\n\n"
        . "Required JSON shape:\n"
        . "{\n"
        . "  \"topics\": [{\"name\": \"\", \"summary\": \"\", \"terms\": [\"\"]}],\n"
        . "  \"protected_terms\": [\"\"],\n"
        . "  \"warnings\": [\"\"]\n"
        . "}\n\n"
        . "Site vocabulary packet:\n"
        . wp_json_encode($prompt_packet);
}

function wp7rss_ai_search_expansion_prompt($packet) {
    $topic_map = wp7rss_get_latest_topic_map();
    $topic_summary = is_array($topic_map) ? array(
        'topics' => array_slice((array) ($topic_map['topics'] ?? array()), 0, 12),
        'protected_terms' => array_slice((array) ($topic_map['protected_terms'] ?? array()), 0, 80),
    ) : array();

    return "You are expanding a WordPress site search query for internal semantic search.\n"
        . "Return JSON only. Do not include Markdown.\n"
        . "Preserve the user's original intent. Generate concise semantic keywords and phrases that are likely to improve recall on this specific site. Do not invent unrelated topics.\n\n"
        . "Required JSON shape:\n"
        . "{\n"
        . "  \"semantic_terms\": [\"\"],\n"
        . "  \"corrected_query\": \"\",\n"
        . "  \"intent_summary\": \"\"\n"
        . "}\n\n"
        . "Search packet:\n"
        . wp_json_encode($packet)
        . "\n\nPrepared site topic map:\n"
        . wp_json_encode($topic_summary);
}

function wp7rss_ai_topic_map_schema() {
    return array(
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => array(
            'topics' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'name' => array('type' => 'string'),
                        'summary' => array('type' => 'string'),
                        'terms' => array('type' => 'array', 'items' => array('type' => 'string')),
                    ),
                    'required' => array('name', 'summary', 'terms'),
                ),
            ),
            'protected_terms' => array('type' => 'array', 'items' => array('type' => 'string')),
            'warnings' => array('type' => 'array', 'items' => array('type' => 'string')),
        ),
        'required' => array('topics', 'protected_terms', 'warnings'),
    );
}

function wp7rss_ai_search_expansion_schema() {
    return array(
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => array(
            'semantic_terms' => array('type' => 'array', 'items' => array('type' => 'string')),
            'corrected_query' => array('type' => 'string'),
            'intent_summary' => array('type' => 'string'),
        ),
        'required' => array('semantic_terms', 'corrected_query', 'intent_summary'),
    );
}

function wp7rss_get_latest_topic_map() {
    global $wpdb;
    $table = $wpdb->prefix . 'wp7rss_topic_map';
    $raw = $wpdb->get_var("SELECT topic_map FROM $table WHERE status = 'ready' ORDER BY updated_at DESC, id DESC LIMIT 1");
    if (empty($raw)) {
        return array();
    }

    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : array();
}
