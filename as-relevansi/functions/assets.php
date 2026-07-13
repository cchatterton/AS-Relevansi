<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp7rss_register_search_block() {
    if (wp7rss_use_plugin_css()) {
        wp_register_style('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    }
    wp_register_script('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
    register_block_type(WP7RSS_PLUGIN_DIR . 'blocks/search-block');
}

add_action('wp_enqueue_scripts', 'wp7rss_enqueue_frontend_assets');
function wp7rss_enqueue_frontend_assets() {
    if (wp7rss_use_plugin_css() && !wp_style_is('wp7rss-frontend', 'registered')) {
        wp_register_style('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    }
    if (!wp_script_is('wp7rss-frontend', 'registered')) {
        wp_register_script('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
    }

    $bot = wp7rss_get_bot_settings();
    wp_localize_script('wp7rss-frontend', 'WP7RSS', array(
        'bot' => array(
            'enabled' => !empty($bot['enabled']),
            'delay' => absint($bot['delay_seconds']) * 1000,
            'mobileDelay' => absint($bot['mobile_delay_seconds']) * 1000,
            'hideMobile' => !empty($bot['hide_mobile']),
            'rememberDismissal' => $bot['remember_dismissal'],
        ),
    ));
}

add_action('admin_enqueue_scripts', 'wp7rss_enqueue_admin_assets');
function wp7rss_enqueue_admin_assets($hook) {
    if ('settings_page_wp7rss-semantic-search' !== $hook) {
        return;
    }

    if (wp7rss_use_plugin_css()) {
        wp_enqueue_style('wp7rss-admin', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    }
    wp_enqueue_media();
    wp_enqueue_script('wp7rss-admin', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
}

function wp7rss_use_plugin_css() {
    $settings = wp7rss_get_settings();

    return !empty($settings['use_plugin_css']);
}

function wp7rss_render_search_bot() {
    $bot = wp7rss_get_bot_settings();
    if (empty($bot['enabled'])) {
        return;
    }

    $is_search_page = is_search();
    $suggested_searches = $is_search_page && function_exists('wp7rss_get_suggested_search_links') ? wp7rss_get_suggested_search_links() : array();
    if ($is_search_page && empty($suggested_searches)) {
        return;
    }

    $image_url = '';
    if (!empty($bot['image_id'])) {
        $image_url = wp_get_attachment_image_url(absint($bot['image_id']), 'thumbnail');
    }

    if (wp7rss_use_plugin_css()) {
        wp_enqueue_style('wp7rss-frontend');
    }
    wp_enqueue_script('wp7rss-frontend');
    ?>
    <div class="wp7rss-search-bot wp7rss-search-bot--<?php echo esc_attr($bot['position']); ?>" data-wp7rss-search-bot hidden>
        <div class="wp7rss-search-bot__panel" data-wp7rss-bot-panel>
            <button type="button" class="wp7rss-search-bot__dismiss" data-wp7rss-bot-dismiss aria-label="<?php esc_attr_e('Dismiss search assistant', WP7RSS_TEXT_DOMAIN); ?>">×</button>
            <?php if ($is_search_page) : ?>
                <div class="wp7rss-search-bot__message"><?php esc_html_e('Maybe one of these suggested terms works:', WP7RSS_TEXT_DOMAIN); ?></div>
                <ul class="wp7rss-search-bot__suggestions" aria-label="<?php esc_attr_e('Suggested search terms', WP7RSS_TEXT_DOMAIN); ?>">
                    <?php foreach ($suggested_searches as $suggested_search) : ?>
                        <li>
                            <a href="<?php echo esc_url($suggested_search['url']); ?>"><?php echo esc_html($suggested_search['term']); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <div class="wp7rss-search-bot__message"><?php echo esc_html($bot['bubble_text']); ?></div>
                <form class="wp7rss-search-bot__composer" action="<?php echo esc_url(wp7rss_get_results_action_url()); ?>" method="get" role="search">
                    <label class="screen-reader-text" for="wp7rss-search-bot-input"><?php esc_html_e('Search query', WP7RSS_TEXT_DOMAIN); ?></label>
                    <input id="wp7rss-search-bot-input" type="search" name="s" required placeholder="<?php echo esc_attr($bot['placeholder']); ?>">
                    <button type="submit"><?php echo esc_html($bot['button_label']); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <button type="button" class="wp7rss-search-bot__launcher" data-wp7rss-bot-toggle aria-expanded="false" aria-label="<?php echo esc_attr($bot['image_alt']); ?>">
            <?php if ($image_url) : ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($bot['image_alt']); ?>">
            <?php else : ?>
                <span aria-hidden="true">Ask</span>
            <?php endif; ?>
        </button>
    </div>
    <?php
}
add_action('wp_footer', 'wp7rss_render_search_bot');
