<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'wp7rss_register_admin_page');
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
        $settings = wp7rss_get_settings();
        $settings['semantic_enabled'] = empty($_POST['semantic_enabled']) ? 0 : 1;
        $settings['ai_integration_enabled'] = empty($_POST['ai_integration_enabled']) ? 0 : 1;
        $settings['ai_timeout_ms'] = max(500, absint($_POST['ai_timeout_ms'] ?? 2500));
        $settings['max_semantic_terms'] = max(1, min(20, absint($_POST['max_semantic_terms'] ?? 8)));
        $settings['cache_duration_hours'] = max(1, absint($_POST['cache_duration_hours'] ?? 24));
        $settings['logging_mode'] = in_array($_POST['logging_mode'] ?? 'metadata', array('off', 'metadata', 'full'), true) ? sanitize_key($_POST['logging_mode']) : 'metadata';
        $settings['log_retention_count'] = max(1, absint($_POST['log_retention_count'] ?? 500));
        $settings['log_retention_days'] = max(1, absint($_POST['log_retention_days'] ?? 30));
        $settings['delete_data_on_uninstall'] = empty($_POST['delete_data_on_uninstall']) ? 0 : 1;
        update_option('wp7rss_settings', $settings);

        $block = wp7rss_get_block_defaults();
        $block['placeholder'] = sanitize_text_field(wp_unslash($_POST['block_placeholder'] ?? ''));
        $block['button_label'] = sanitize_text_field(wp_unslash($_POST['block_button_label'] ?? ''));
        $block['heading'] = sanitize_text_field(wp_unslash($_POST['block_heading'] ?? ''));
        $block['intro'] = sanitize_textarea_field(wp_unslash($_POST['block_intro'] ?? ''));
        $block['intent_label'] = sanitize_text_field(wp_unslash($_POST['block_intent_label'] ?? ''));
        $block['css_class'] = sanitize_html_class(wp_unslash($_POST['block_css_class'] ?? ''));
        $block['results_url'] = esc_url_raw(wp_unslash($_POST['block_results_url'] ?? ''));
        update_option('wp7rss_block_defaults', $block);

        $bot = wp7rss_get_bot_settings();
        $bot['enabled'] = empty($_POST['bot_enabled']) ? 0 : 1;
        $bot['delay_seconds'] = max(0, absint($_POST['bot_delay_seconds'] ?? 8));
        $bot['image_id'] = absint($_POST['bot_image_id'] ?? 0);
        $bot['image_alt'] = sanitize_text_field(wp_unslash($_POST['bot_image_alt'] ?? ''));
        $bot['bubble_text'] = sanitize_text_field(wp_unslash($_POST['bot_bubble_text'] ?? ''));
        $bot['placeholder'] = sanitize_text_field(wp_unslash($_POST['bot_placeholder'] ?? ''));
        $bot['button_label'] = sanitize_text_field(wp_unslash($_POST['bot_button_label'] ?? ''));
        $bot['hide_mobile'] = empty($_POST['bot_hide_mobile']) ? 0 : 1;
        $bot['remember_dismissal'] = in_array($_POST['bot_remember_dismissal'] ?? 'session', array('never', 'page', 'session', 'persistent'), true) ? sanitize_key($_POST['bot_remember_dismissal']) : 'session';
        $bot['excluded_urls'] = sanitize_textarea_field(wp_unslash($_POST['bot_excluded_urls'] ?? ''));
        update_option('wp7rss_search_bot_settings', $bot);

        wp_safe_redirect(add_query_arg(array('page' => 'wp7rss-semantic-search', 'updated' => '1'), admin_url('options-general.php')));
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

function wp7rss_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $tab = sanitize_key($_GET['tab'] ?? 'general');
    $tabs = array(
        'general' => __('General', WP7RSS_TEXT_DOMAIN),
        'search-block' => __('Search Block', WP7RSS_TEXT_DOMAIN),
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
    $block = wp7rss_get_block_defaults();
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

        <?php if ('general' === $tab) : ?>
            <table class="widefat striped wp7rss-status-table">
                <tbody>
                    <tr><th><?php esc_html_e('Relevanssi detected', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo wp7rss_is_relevanssi_active() ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('AI Connector detected', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo $ai['available'] ? esc_html__('Yes', WP7RSS_TEXT_DOMAIN) : esc_html__('No', WP7RSS_TEXT_DOMAIN); ?></td></tr>
                    <tr><th><?php esc_html_e('Topic Map status', WP7RSS_TEXT_DOMAIN); ?></th><td><?php echo esc_html($topic['status']); ?></td></tr>
                </tbody>
            </table>
            <h2><?php esc_html_e('General Settings', WP7RSS_TEXT_DOMAIN); ?></h2>
            <p><label><input type="checkbox" name="semantic_enabled" value="1" <?php checked($settings['semantic_enabled']); ?>> <?php esc_html_e('Enable semantic expansion globally', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <p><label><input type="checkbox" name="ai_integration_enabled" value="1" <?php checked($settings['ai_integration_enabled']); ?>> <?php esc_html_e('Enable AI Connector integration', WP7RSS_TEXT_DOMAIN); ?></label></p>
        <?php elseif ('search-block' === $tab) : ?>
            <h2><?php esc_html_e('Search Block Defaults', WP7RSS_TEXT_DOMAIN); ?></h2>
            <?php wp7rss_text_input('block_placeholder', __('Placeholder', WP7RSS_TEXT_DOMAIN), $block['placeholder']); ?>
            <?php wp7rss_text_input('block_button_label', __('Button label', WP7RSS_TEXT_DOMAIN), $block['button_label']); ?>
            <?php wp7rss_text_input('block_heading', __('Heading', WP7RSS_TEXT_DOMAIN), $block['heading']); ?>
            <?php wp7rss_textarea('block_intro', __('Intro text', WP7RSS_TEXT_DOMAIN), $block['intro']); ?>
            <?php wp7rss_text_input('block_intent_label', __('Search intent label', WP7RSS_TEXT_DOMAIN), $block['intent_label']); ?>
            <?php wp7rss_text_input('block_css_class', __('CSS class', WP7RSS_TEXT_DOMAIN), $block['css_class']); ?>
            <?php wp7rss_text_input('block_results_url', __('Results URL override', WP7RSS_TEXT_DOMAIN), $block['results_url']); ?>
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
            <?php wp7rss_number_input('ai_timeout_ms', __('Timeout milliseconds', WP7RSS_TEXT_DOMAIN), $settings['ai_timeout_ms']); ?>
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
        <?php elseif ('search-bot' === $tab) : ?>
            <h2><?php esc_html_e('Search Bot', WP7RSS_TEXT_DOMAIN); ?></h2>
            <p><label><input type="checkbox" name="bot_enabled" value="1" <?php checked($bot['enabled']); ?>> <?php esc_html_e('Enable Search Bot', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <?php wp7rss_number_input('bot_delay_seconds', __('Delay seconds', WP7RSS_TEXT_DOMAIN), $bot['delay_seconds']); ?>
            <?php wp7rss_text_input('bot_image_id', __('Bot image attachment ID', WP7RSS_TEXT_DOMAIN), $bot['image_id']); ?>
            <?php wp7rss_text_input('bot_image_alt', __('Bot image alt text', WP7RSS_TEXT_DOMAIN), $bot['image_alt']); ?>
            <?php wp7rss_text_input('bot_bubble_text', __('Bubble text', WP7RSS_TEXT_DOMAIN), $bot['bubble_text']); ?>
            <?php wp7rss_text_input('bot_placeholder', __('Placeholder', WP7RSS_TEXT_DOMAIN), $bot['placeholder']); ?>
            <?php wp7rss_text_input('bot_button_label', __('Button label', WP7RSS_TEXT_DOMAIN), $bot['button_label']); ?>
            <p><label><input type="checkbox" name="bot_hide_mobile" value="1" <?php checked($bot['hide_mobile']); ?>> <?php esc_html_e('Hide on mobile', WP7RSS_TEXT_DOMAIN); ?></label></p>
            <?php wp7rss_textarea('bot_excluded_urls', __('Excluded URLs/templates', WP7RSS_TEXT_DOMAIN), $bot['excluded_urls']); ?>
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
            <thead><tr><th><?php esc_html_e('Date', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Type', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Status', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Query', WP7RSS_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Duration', WP7RSS_TEXT_DOMAIN); ?></th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td><?php echo esc_html($log->call_type); ?></td>
                    <td><?php echo esc_html($log->status); ?></td>
                    <td><?php echo esc_html($log->search_query); ?></td>
                    <td><?php echo esc_html($log->duration_ms); ?>ms</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
