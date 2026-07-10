<?php
if (!defined('ABSPATH')) {
    exit;
}

function wp7rss_register_search_block() {
    wp_register_style('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    wp_register_script('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
    register_block_type(WP7RSS_PLUGIN_DIR . 'blocks/search-block');
}

add_action('wp_enqueue_scripts', 'wp7rss_enqueue_frontend_assets');
function wp7rss_enqueue_frontend_assets() {
    if (!wp_style_is('wp7rss-frontend', 'registered')) {
        wp_register_style('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    }
    if (!wp_script_is('wp7rss-frontend', 'registered')) {
        wp_register_script('wp7rss-frontend', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
    }

    $bot = wp7rss_get_bot_settings();
    wp_localize_script('wp7rss-frontend', 'WP7RSS', array(
        'bot' => array(
            'enabled' => !empty($bot['enabled']) && !is_search(),
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

    wp_enqueue_style('wp7rss-admin', WP7RSS_PLUGIN_URL . 'styles/as-relevansi.css', array(), WP7RSS_VERSION);
    wp_enqueue_media();
    wp_enqueue_script('wp7rss-admin', WP7RSS_PLUGIN_URL . 'scripts/as-relevansi.js', array(), WP7RSS_VERSION, true);
}

function wp7rss_render_search_bot() {
    if (is_search()) {
        return;
    }

    $bot = wp7rss_get_bot_settings();
    if (empty($bot['enabled'])) {
        return;
    }

    $image_url = '';
    if (!empty($bot['image_id'])) {
        $image_url = wp_get_attachment_image_url(absint($bot['image_id']), 'thumbnail');
    }

    wp_enqueue_style('wp7rss-frontend');
    wp_enqueue_script('wp7rss-frontend');
    ?>
    <div class="wp7rss-search-bot" data-wp7rss-search-bot hidden>
        <button type="button" class="wp7rss-search-bot__avatar" data-wp7rss-bot-toggle aria-expanded="false" aria-label="<?php echo esc_attr($bot['image_alt']); ?>">
            <?php if ($image_url) : ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($bot['image_alt']); ?>">
            <?php else : ?>
                <span aria-hidden="true">?</span>
            <?php endif; ?>
        </button>
        <div class="wp7rss-search-bot__bubble" data-wp7rss-bot-bubble>
            <button type="button" class="wp7rss-search-bot__dismiss" data-wp7rss-bot-dismiss aria-label="<?php esc_attr_e('Dismiss search assistant', WP7RSS_TEXT_DOMAIN); ?>">×</button>
            <p><?php echo esc_html($bot['bubble_text']); ?></p>
            <form class="wp7rss-search-bot__form" action="<?php echo esc_url(wp7rss_get_results_action_url()); ?>" method="get" role="search">
                <label class="screen-reader-text" for="wp7rss-search-bot-input"><?php esc_html_e('Search query', WP7RSS_TEXT_DOMAIN); ?></label>
                <input id="wp7rss-search-bot-input" type="search" name="s" required placeholder="<?php echo esc_attr($bot['placeholder']); ?>">
                <button type="submit"><?php echo esc_html($bot['button_label']); ?></button>
            </form>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'wp7rss_render_search_bot');
