(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
            return;
        }
        document.addEventListener('DOMContentLoaded', fn);
    }

    function dismissalKey() {
        return 'wp7rss_search_bot_dismissed:' + window.location.pathname;
    }

    function isDismissed(mode) {
        if (mode === 'never') {
            return false;
        }
        if (mode === 'persistent') {
            return window.localStorage.getItem(dismissalKey()) === '1';
        }
        if (mode === 'session') {
            return window.sessionStorage.getItem('wp7rss_search_bot_dismissed') === '1';
        }
        return window.sessionStorage.getItem(dismissalKey()) === '1';
    }

    function setDismissed(mode) {
        if (mode === 'persistent') {
            window.localStorage.setItem(dismissalKey(), '1');
        } else if (mode === 'session') {
            window.sessionStorage.setItem('wp7rss_search_bot_dismissed', '1');
        } else if (mode === 'page') {
            window.sessionStorage.setItem(dismissalKey(), '1');
        }
    }

    function initSearchBot() {
        var config = window.WP7RSS && window.WP7RSS.bot ? window.WP7RSS.bot : {};
        var bot = document.querySelector('[data-wp7rss-search-bot]');
        if (!config.enabled || !bot || isDismissed(config.rememberDismissal || 'session')) {
            return;
        }

        if (config.hideMobile && window.matchMedia('(max-width: 640px)').matches) {
            return;
        }

        var delay = Number.isFinite(config.delay) ? config.delay : 8000;
        var toggle = bot.querySelector('[data-wp7rss-bot-toggle]');
        var dismiss = bot.querySelector('[data-wp7rss-bot-dismiss]');

        function show() {
            bot.hidden = false;
            bot.classList.add('is-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }

        function closePanel() {
            bot.classList.remove('is-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                bot.hidden = false;
                bot.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', bot.classList.contains('is-open') ? 'true' : 'false');
            });
        }

        if (dismiss) {
            dismiss.addEventListener('click', function () {
                setDismissed(config.rememberDismissal || 'session');
                bot.hidden = true;
                closePanel();
            });
        }

        window.setTimeout(show, delay);
    }

    function initAdminMediaPicker() {
        var select = document.querySelector('[data-wp7rss-media-select]');
        var clear = document.querySelector('[data-wp7rss-media-clear]');
        var input = document.getElementById('bot_image_id');
        var preview = document.querySelector('[data-wp7rss-media-preview]');
        if (!select || !input || !preview || !window.wp || !window.wp.media) {
            return;
        }

        select.addEventListener('click', function () {
            var frame = window.wp.media({
                title: 'Choose Search Bot image',
                button: { text: 'Use this image' },
                multiple: false
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id || '';
                preview.innerHTML = attachment.sizes && attachment.sizes.thumbnail
                    ? '<img src="' + attachment.sizes.thumbnail.url + '" alt="">'
                    : '<img src="' + attachment.url + '" alt="">';
            });
            frame.open();
        });

        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '0';
                preview.innerHTML = '<span>No image selected</span>';
            });
        }
    }

    ready(initSearchBot);
    ready(initAdminMediaPicker);
})();
