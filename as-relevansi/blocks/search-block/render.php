<?php
if (!defined('ABSPATH')) {
    exit;
}

$defaults = wp7rss_get_block_defaults();
$placeholder = !empty($attributes['placeholder']) ? $attributes['placeholder'] : $defaults['placeholder'];
$button = !empty($attributes['buttonLabel']) ? $attributes['buttonLabel'] : $defaults['button_label'];
$heading = !empty($attributes['heading']) ? $attributes['heading'] : $defaults['heading'];
$intro = !empty($attributes['intro']) ? $attributes['intro'] : $defaults['intro'];
$css_class = !empty($attributes['cssClass']) ? sanitize_html_class($attributes['cssClass']) : $defaults['css_class'];
$results_url = !empty($attributes['resultsUrl']) ? $attributes['resultsUrl'] : $defaults['results_url'];
$intent = !empty($attributes['intentLabel']) ? $attributes['intentLabel'] : $defaults['intent_label'];
$instance_id = !empty($attributes['blockInstanceId']) ? sanitize_text_field($attributes['blockInstanceId']) : wp_unique_id('wp7rss-search-');

wp_enqueue_style('wp7rss-frontend');
wp_enqueue_script('wp7rss-frontend');
?>
<div class="wp7rss-search-block <?php echo esc_attr($css_class); ?>" data-wp7rss-search-block data-wp7rss-intent="<?php echo esc_attr($intent); ?>" data-wp7rss-instance="<?php echo esc_attr($instance_id); ?>">
    <?php if ($heading) : ?>
        <h2 class="wp7rss-search-block__heading"><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>
    <?php if ($intro) : ?>
        <p class="wp7rss-search-block__intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>
    <form class="wp7rss-search-block__form" action="<?php echo esc_url(wp7rss_get_results_action_url($results_url)); ?>" method="get" role="search">
        <label class="screen-reader-text" for="<?php echo esc_attr($instance_id); ?>-input"><?php esc_html_e('Search query', WP7RSS_TEXT_DOMAIN); ?></label>
        <input id="<?php echo esc_attr($instance_id); ?>-input" type="search" name="s" required value="<?php echo esc_attr(get_search_query(false)); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
        <button type="submit"><?php echo esc_html($button); ?></button>
    </form>
</div>
