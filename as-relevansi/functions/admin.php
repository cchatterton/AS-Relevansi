<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'wp7rss_register_admin_page');
add_action('admin_init', 'wp7rss_reconcile_admin_status');
add_action('admin_init', 'wp7rss_handle_admin_actions');

function wp7rss_register_admin_page() {
    add_options_page(
        __('Relevanssi Extended', WP7RSS_TEXT_DOMAIN),
        __('Semantic Search', WP7RSS_TEXT_DOMAIN),
        'manage_options',
        'wp7rss-semantic-search',
        'wp7rss_render_admin_page'
    );
}

function wp7rss_handle_admin_actions() {
    if (empty($_POST['wp7rss_action']) || !current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('wp7rss_admin_action');
    $action = sanitize_key(wp_unslash($_POST['wp7rss_action_override'] ?? $_POST['wp7rss_action']));

    if ('save_settings' === $action) {
        $settings_tab = sanitize_key(wp_unslash($_POST['wp7rss_settings_tab'] ?? 'general'));
        $settings = wp7rss_get_settings();

        if ('general' === $settings_tab) {
            $settings['use_plugin_css'] = empty($_POST['use_plugin_css']) ? 0 : 1;
            update_option('wp7rss_settings', $settings);
        } elseif ('ai-connector' === $settings_tab) {
            $settings['ai_timeout_ms'] = max(500, absint($_POST['ai_timeout_ms'] ?? 2500));
            $settings['topic_map_timeout_ms'] = max(5000, absint($_POST['topic_map_timeout_ms'] ?? 30000));
            $settings['max_semantic_terms'] = max(1, min(20, absint($_POST['max_semantic_terms'] ?? 8)));
            $settings['cache_duration_hours'] = max(1, absint($_POST['cache_duration_hours'] ?? 24));
            update_option('wp7rss_settings', $settings);
        } elseif ('advanced' === $settings_tab) {
            $settings['logging_mode'] = in_array($_POST['logging_mode'] ?? 'metadata', array('off', 'metadata', 'full'), true) ? sanitize_key($_POST['logging_mode']) : 'metadata';
            $settings['log_retention_count'] = max(1, absint($_POST['log_retention_count'] ?? 500));
            $settings['log_retention_days'] = max(1, absint($_POST['log_retention_days'] ?? 30));
            $settings['delete_data_on_uninstall'] = empty($_POST['delete_data_on_uninstall']) ? 0 : 1;
            update_option('wp7rss_settings', $settings);
        } elseif ('search-bot' === $settings_tab) {
            $bot = wp7rss_get_bot_settings();
            $bot['enabled'] = empty($_POST['bot_enabled']) ? 0 : 1;
            $bot['delay_seconds'] = max(0, absint($_POST['bot_delay_seconds'] ?? 8));
            $bot['image_id'] = absint($_POST['bot_image_id'] ?? 0);
            $bot_defaults = wp7rss_default_bot_settings();
            $bot['image_alt'] = sanitize_text_field(wp_unslash($_POST['bot_image_alt'] ?? '')) ?: $bot_defaults['image_alt'];
            $bot['bubble_text'] = sanitize_text_field(wp_unslash($_POST['bot_bubble_text'] ?? '')) ?: $bot_defaults['bubble_text'];
            $bot['placeholder'] = sanitize_text_field(wp_unslash($_POST['bot_placeholder'] ?? '')) ?: $bot_defaults['placeholder'];
            $bot['button_label'] = sanitize_text_field(wp_unslash($_POST['bot_button_label'] ?? '')) ?: $bot_defaults['button_label'];
            $bot['position'] = in_array($_POST['bot_position'] ?? 'bottom-right', array('bottom-left', 'bottom-right'), true) ? sanitize_key($_POST['bot_position']) : 'bottom-right';
            $bot['hide_mobile'] = empty($_POST['bot_hide_mobile']) ? 0 : 1;
            $bot['excluded_urls'] = sanitize_textarea_field(wp_unslash($_POST['bot_excluded_urls'] ?? ''));
            update_option('wp7rss_search_bot_settings', $bot);
        }

        wp_safe_redirect(add_query_arg(array('page' => 'wp7rss-semantic-search', 'tab' => $settings_tab, 'updated' => '1'), admin_url('options-general.php')));
        exit;
    }

    if ('rebuild_topic_map' === $action) {
        if (wp7rss_ai_connector_available()) {
            wp_schedule_single_event(time() + 5, 'wp7rss_build_topic_map', array('manual'));
            $status = wp7rss_get_topic_status();
            $status['status'] = 'pending_initial_build';
            update_option('wp7rss_topic_map_status', $status);
        }
        wp_safe_redirect(add_query_arg(array('page' => 'wp7rss-semantic-search', 'tab' => 'topic-map'), admin_url('options-general.php')));
        exit;
    }

    if ('clear_logs' === $action) {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wp7rss_ai_logs");
        wp_safe_redirect(add_query_arg(array('page' => 'wp7rss-semantic-search', 'tab' => 'ai-logs'), admin_url('options-general.php')));
        exit;
    }
}

function wp7rss_reconcile_admin_status() {
    if (!current_user_can('manage_options')) {
        return;
    }

    update_option('wp7rss_ai_status', wp7rss_get_ai_connector_status());

    $topic = wp7rss_get_topic_status();
    if ('disabled_no_ai_connector' === $topic['status'] && wp7rss_ai_connector_available()) {
        $topic['status'] = 'not_started';
        $topic['last_error'] = '';
        update_option('wp7rss_topic_map_status', $topic);
    }
}

function wp7rss_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $tab = sanitize_key($_GET['tab'] ?? 'general');
    $tabs = array(
        'general' => __('General', WP7RSS_TEXT_DOMAIN),
        'ai-connector' => __('AI Connector', WP7RSS_TEXT_DOMAIN),
        'topic-map' => __('Site Topic Map', WP7RSS_TEXT_DOMAIN),
        'search-bot' => __('Search Bot', WP7RSS_TEXT_DOMAIN),
        'ai-logs' => __('AI Logs', WP7RSS_TEXT_DOMAIN),
        'advanced' => __('Advanced', WP7RSS_TEXT_DOMAIN),
    );
    ?>
    <div class="wrap wp7rss-admin">
        <h1><?php esc_html_e('Relevanssi Extended', WP7RSS_TEXT_DOMAIN); ?></h1>
        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $key => $label) : ?>
                <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('page' => 'wp7rss-semantic-search', 'tab' => $key), admin_url('options-general.php'))); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php wp7rss_render_admin_tab($tab); ?>
    </div>
    <?php
}

function wp7rss_render_admin_tab($tab) {
    $settings = wp7rss_get_settings();
    $bot = wp7rss_get_bot_settings();
    $topic = wp7rss_get_topic_status();
    $ai = wp7rss_get_ai_connector_status();

    if ('ai-logs' === $tab) {
        wp7rss_render_ai_logs_tab();
        return;
    }
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('wp7rss_admin_action'); ?>
        <input type="hidden" name="wp7rss_action" value="save_settings">
        <input type="hidden" name="wp7rss_settings_tab" value="<?php echo esc_attr($tab); ?>">

        <?php if ('general' === $tab) : ?>
            <table class="widefat striped wp7rss-status-table">
                <tbody>
                    <tr><th><?php esc_html_e('Relevanssi detected', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo wp7rss_is_relevanssi_active() ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('AI Connector detected', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo $ai['available'] ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('Topic Map status', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo esc_html($topic['status']); ?></td></tr>
                </tbody>
            </table>
            <h2><?php esc_html_e('General Status', WP7RSS_TEXT_DOMAIN); ?></h2>
            <p><?php esc_html_e('Semantic expansion is automatic when Relevanssi, a configured WordPress 7 AI Connector, and a ready Site Topic Map are available.', WP7RSS_TEXT_DOMAIN); ?></p>
            <h2><?php esc_html_e('General Settings', WP7RSS_TEXT_DOMAIN); ?></h2>
            <p><label><input type="checkbox" name="use_plugin_css" value="1" <?php checked($settings['use_plugin_css']); ?>> <?php esc_html_e('Use plugin CSS', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <p class="description"><?php esc_html_e('When disabled, Relevanssi Extended will not enqueue its CSS file. Theme or custom CSS must style the Search Bot and block output.', WP7RSS_TEXT_DOMAIN); ?></p>
        <?php elseif ('ai-connector' === $tab) : ?>
            <h2><?php esc_html_e('AI Connector', WP7RSS_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped wp7rss-status-table">
                <tbody>
                    <tr><th><?php esc_html_e('Available', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo $ai['available'] ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('Configured', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo $ai['configured'] ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('Callable', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo $ai['callable'] ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('Provider / model', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo esc_html(trim($ai['provider'] . ' ' . $ai['model'])); ?></td></tr>
                </tbody>
            </table>
            <?php wp7rss_number_input('ai_timeout_ms', __('Live search timeout milliseconds', WP7RSS_TEXT_DOMAIN), $settings['ai_timeout_ms']); ?>
            <?php wp7rss_number_input('topic_map_timeout_ms', __('Topic Map timeout milliseconds', WP7RSS_TEXT_DOMAIN), $settings['topic_map_timeout_ms']); ?>
            <?php wp7rss_number_input('max_semantic_terms', __('Max semantic terms', WP7RSS_TEXT_DOMAIN), $settings['max_semantic_terms']); ?>
            <?php wp7rss_number_input('cache_duration_hours', __('Expansion cache hours', WP7RSS_TEXT_DOMAIN), $settings['cache_duration_hours']); ?>
        <?php elseif ('topic-map' === $tab) : ?>
            <h2><?php esc_html_e('Site Topic Map', WP7RSS_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped wp7rss-status-table">
                <tbody>
                    <?php foreach ($topic as $key => $value) : ?>
                        <tr><th><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th><td><?php echo esc_html(is_scalar($value) ? (string) $value : wp_json_encode($value)); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="submit" class="button button-secondary" name="wp7rss_action_override" value="rebuild_topic_map"><?php esc_html_e('Rebuild topic map', WP7RSS_TEXT_DOMAIN); ?></button>
            </p>
            <?php wp7rss_render_topic_map_response(); ?>
        <?php elseif ('search-bot' === $tab) : ?>
            <h2><?php esc_html_e('Search Bot', WP7RSS_TEXT_DOMAIN); ?></h2>
            <div class="notice notice-info inline">
                <p><?php esc_html_e('The Search Bot appears on all frontend pages when enabled, except WordPress search results pages and admin screens.', WP7RSS_TEXT_DOMAIN); ?></p>
            </div>
            <p><label><input type="checkbox" name="bot_enabled" value="1" <?php checked($bot['enabled']); ?>> <?php esc_html_e('Enable Search Bot', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <?php wp7rss_number_input('bot_delay_seconds', __('Delay seconds', WP7RSS_TEXT_DOMAIN), $bot['delay_seconds']); ?>
            <div class="wp7rss-media-field">
                <label><?php esc_html_e('Avatar image', WP7RSS_TEXT_DOMAIN); ?></label>
                <input type="hidden" id="bot_image_id" name="bot_image_id" value="<?php echo esc_attr($bot['image_id']); ?>">
                <div class="wp7rss-media-field__preview" data-wp7rss-media-preview>
                    <?php if (!empty($bot['image_id'])) : ?>
                        <?php echo wp_get_attachment_image(absint($bot['image_id']), 'thumbnail'); ?>
                    <?php else : ?>
                        <span><?php esc_html_e('No image selected', WP7RSS_TEXT_DOMAIN); ?></span>
                    <?php endif; ?>
                </div>
                <p>
                    <button type="button" class="button" data-wp7rss-media-select><?php esc_html_e('Choose image', WP7RSS_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button" data-wp7rss-media-clear><?php esc_html_e('Remove image', WP7RSS_TEXT_DOMAIN); ?></button>
                </p>
            </div>
            <?php wp7rss_text_input('bot_image_alt', __('Bot image alt text', WP7RSS_TEXT_DOMAIN), $bot['image_alt']); ?>
            <?php wp7rss_text_input('bot_bubble_text', __('Bubble text', WP7RSS_TEXT_DOMAIN), $bot['bubble_text']); ?>
            <?php wp7rss_text_input('bot_placeholder', __('Placeholder', WP7RSS_TEXT_DOMAIN), $bot['placeholder']); ?>
            <?php wp7rss_text_input('bot_button_label', __('Send button label', WP7RSS_TEXT_DOMAIN), $bot['button_label']); ?>
            <p>
                <label for="bot_position"><?php esc_html_e('Position', WP7RSS_TEXT_DOMAIN); ?></label><br>
                <select name="bot_position" id="bot_position">
                    <option value="bottom-right" <?php selected($bot['position'], 'bottom-right'); ?>><?php esc_html_e('Bottom right', WP7RSS_TEXT_DOMAIN); ?></option>
                    <option value="bottom-left" <?php selected($bot['position'], 'bottom-left'); ?>><?php esc_html_e('Bottom left', WP7RSS_TEXT_DOMAIN); ?></option>
                </select>
            </p>
            <p><label><input type="checkbox" name="bot_hide_mobile" value="1" <?php checked($bot['hide_mobile']); ?>> <?php esc_html_e('Hide on mobile', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <?php wp7rss_textarea('bot_excluded_urls', __('Excluded URLs/templates', WP7RSS_TEXT_DOMAIN), $bot['excluded_urls']); ?>
            <div class="wp7rss-search-bot-preview" aria-label="<?php esc_attr_e('Search Bot preview', WP7RSS_TEXT_DOMAIN); ?>">
                <strong><?php esc_html_e('Preview', WP7RSS_TEXT_DOMAIN); ?></strong>
                <div class="wp7rss-search-bot__panel">
                    <div class="wp7rss-search-bot__message"><?php echo esc_html($bot['bubble_text']); ?></div>
                    <div class="wp7rss-search-bot__composer">
                        <input type="search" disabled placeholder="<?php echo esc_attr($bot['placeholder']); ?>">
                        <button type="button" disabled><?php echo esc_html($bot['button_label']); ?></button>
                    </div>
                </div>
            </div>
        <?php elseif ('advanced' === $tab) : ?>
            <h2><?php esc_html_e('Advanced', WP7RSS_TEXT_DOMAIN); ?></h2>
            <p>
                <label for="logging_mode"><?php esc_html_e('Logging mode', WP7RSS_TEXT_DOMAIN); ?></label><br>
                <select name="logging_mode" id="logging_mode">
                    <option value="off" <?php selected($settings['logging_mode'], 'off'); ?>><?php esc_html_e('Off', WP7RSS_TEXT_DOMAIN); ?></option>
                    <option value="metadata" <?php selected($settings['logging_mode'], 'metadata'); ?>><?php esc_html_e('Metadata only', WP7RSS_TEXT_DOMAIN); ?></option>
                    <option value="full" <?php selected($settings['logging_mode'], 'full'); ?>><?php esc_html_e('Full debug logging', WP7RSS_TEXT_DOMAIN); ?></option>
                </select>
            </p>
            <?php wp7rss_number_input('log_retention_count', __('Log retention count', WP7RSS_TEXT_DOMAIN), $settings['log_retention_count']); ?>
            <?php wp7rss_number_input('log_retention_days', __('Log retention days', WP7RSS_TEXT_DOMAIN), $settings['log_retention_days']); ?>
            <p><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($settings['delete_data_on_uninstall']); ?>> <?php esc_html_e('Delete plugin data on uninstall', WP7RSS_TEXT_DOMAIN); ?></label></p>
        <?php endif; ?>

        <?php if (!in_array($tab, array('topic-map'), true)) : ?>
            <?php submit_button(__('Save settings', WP7RSS_TEXT_DOMAIN)); ?>
        <?php endif; ?>
    </form>
    <?php
}

function wp7rss_render_ai_logs_tab() {
    global $wpdb;
    $table = $wpdb->prefix . 'wp7rss_ai_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 50");
    ?>
    <h2><?php esc_html_e('AI Logs', WP7RSS_TEXT_DOMAIN); ?></h2>
    <form method="post">
        <?php wp_nonce_field('wp7rss_admin_action'); ?>
        <button type="submit" class="button" name="wp7rss_action" value="clear_logs"><?php esc_html_e('Clear logs', WP7RSS_TEXT_DOMAIN); ?></button>
    </form>
    <?php if (empty($logs)) : ?>
        <p><?php esc_html_e('No AI calls have been made. AI telemetry will appear here once an AI Connector is configured and semantic search expansion is used.', WP7RSS_TEXT_DOMAIN); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e('Date', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Type', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Status', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Query', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Details', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Duration', WP7RSS_TEXT_DOMAIN); ?></th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td><?php echo esc_html($log->call_type); ?></td>
                    <td><?php echo esc_html($log->status); ?></td>
                    <td><?php echo esc_html($log->search_query); ?></td>
                    <td><?php echo esc_html(wp7rss_get_ai_log_details($log)); ?></td>
                    <td><?php echo esc_html($log->duration_ms); ?>ms</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
}

function wp7rss_get_ai_log_details($log) {
    if (!empty($log->error_message)) {
        return $log->error_message;
    }

    if (!empty($log->rejection_reason)) {
        return ucwords(str_replace('_', ' ', $log->rejection_reason));
    }

    if (!empty($log->semantic_terms)) {
        $terms = json_decode((string) $log->semantic_terms, true);
        if (is_array($terms) && !empty($terms)) {
            return implode(', ', array_slice(array_map('sanitize_text_field', $terms), 0, 8));
        }
    }

    if (!empty($log->cache_status)) {
        return sprintf(
            /* translators: %s: cache status. */
            __('Cache %s', WP7RSS_TEXT_DOMAIN),
            sanitize_text_field($log->cache_status)
        );
    }

    return '';
}

function wp7rss_get_latest_topic_map_record() {
    global $wpdb;

    $table = $wpdb->prefix . 'wp7rss_topic_map';
    $record = $wpdb->get_row("SELECT * FROM $table WHERE status = 'ready' ORDER BY updated_at DESC, id DESC LIMIT 1");

    if (!$record || empty($record->topic_map)) {
        return null;
    }

    $topic_map = json_decode($record->topic_map, true);
    if (!is_array($topic_map)) {
        return null;
    }

    return array(
        'record' => $record,
        'topic_map' => $topic_map,
    );
}

function wp7rss_render_topic_map_response() {
    $latest = wp7rss_get_latest_topic_map_record();
    ?>
    <section class="wp7rss-topic-response">
        <h2><?php esc_html_e('Prepared Topics Response', WP7RSS_TEXT_DOMAIN); ?></h2>
        <?php if (!$latest) : ?>
            <p><?php esc_html_e('No prepared topic map response is available yet. Build the Site Topic Map to generate and store the topics response.', WP7RSS_TEXT_DOMAIN); ?></p>
        <?php else : ?>
            <?php
            $record = $latest['record'];
            $topic_map = $latest['topic_map'];
            $topics = isset($topic_map['topics']) && is_array($topic_map['topics']) ? $topic_map['topics'] : array();
            $protected_terms = isset($topic_map['protected_terms']) && is_array($topic_map['protected_terms']) ? $topic_map['protected_terms'] : array();
            $warnings = isset($topic_map['warnings']) && is_array($topic_map['warnings']) ? $topic_map['warnings'] : array();
            ?>
            <p class="description">
                <?php
                printf(
                    esc_html__('Version %1$s prepared on %2$s.', WP7RSS_TEXT_DOMAIN),
                    esc_html($record->version_hash),
                    esc_html($record->updated_at)
                );
                ?>
            </p>

            <?php if (!empty($topics)) : ?>
                <div class="wp7rss-topic-grid">
                    <?php foreach ($topics as $topic) : ?>
                        <?php wp7rss_render_topic_card(is_array($topic) ? $topic : array()); ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('The prepared response does not contain any topics.', WP7RSS_TEXT_DOMAIN); ?></p>
            <?php endif; ?>

            <?php if (!empty($protected_terms)) : ?>
                <h3><?php esc_html_e('Protected Terms', WP7RSS_TEXT_DOMAIN); ?></h3>
                <p class="wp7rss-term-list"><?php echo esc_html(implode(', ', array_map('sanitize_text_field', $protected_terms))); ?></p>
            <?php endif; ?>

            <?php if (!empty($warnings)) : ?>
                <h3><?php esc_html_e('Warnings', WP7RSS_TEXT_DOMAIN); ?></h3>
                <ul class="wp7rss-warning-list">
                    <?php foreach ($warnings as $warning) : ?>
                        <li><?php echo esc_html(sanitize_text_field($warning)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <details class="wp7rss-topic-json">
                <summary><?php esc_html_e('View raw prepared response JSON', WP7RSS_TEXT_DOMAIN); ?></summary>
                <pre><?php echo esc_html(wp_json_encode($topic_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
            </details>
        <?php endif; ?>
    </section>
    <?php
}

function wp7rss_render_topic_card($topic) {
    $title = sanitize_text_field($topic['topic'] ?? __('Untitled topic', WP7RSS_TEXT_DOMAIN));
    $confidence = sanitize_text_field($topic['confidence'] ?? '');
    ?>
    <article class="wp7rss-topic-card">
        <header class="wp7rss-topic-card__header">
            <h3><?php echo esc_html($title); ?></h3>
            <?php if ($confidence) : ?>
                <span><?php echo esc_html($confidence); ?></span>
            <?php endif; ?>
        </header>
        <?php wp7rss_render_topic_terms(__('Canonical terms', WP7RSS_TEXT_DOMAIN), $topic['canonical_terms'] ?? array()); ?>
        <?php wp7rss_render_topic_terms(__('Related terms', WP7RSS_TEXT_DOMAIN), $topic['related_terms'] ?? array()); ?>
        <?php wp7rss_render_topic_terms(__('Likely user phrases', WP7RSS_TEXT_DOMAIN), $topic['likely_user_phrases'] ?? array()); ?>
        <?php wp7rss_render_topic_terms(__('Mapped post types', WP7RSS_TEXT_DOMAIN), $topic['mapped_post_types'] ?? array()); ?>
        <?php wp7rss_render_topic_terms(__('Mapped taxonomies', WP7RSS_TEXT_DOMAIN), $topic['mapped_taxonomies'] ?? array()); ?>
    </article>
    <?php
}

function wp7rss_render_topic_terms($label, $terms) {
    $terms = is_array($terms) ? array_filter(array_map('sanitize_text_field', $terms)) : array();
    if (empty($terms)) {
        return;
    }
    ?>
    <div class="wp7rss-topic-card__terms">
        <strong><?php echo esc_html($label); ?></strong>
        <ul>
            <?php foreach ($terms as $term) : ?>
                <li><?php echo esc_html($term); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function wp7rss_text_input($name, $label, $value) {
    ?>
    <p><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label><br><input class="regular-text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"></p>
    <?php
}

function wp7rss_number_input($name, $label, $value) {
    ?>
    <p><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label><br><input type="number" class="small-text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>"></p>
    <?php
}

function wp7rss_textarea($name, $label, $value) {
    ?>
    <p><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label><br><textarea class="large-text" rows="4" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>"><?php echo esc_textarea($value); ?></textarea></p>
    <?php
}
