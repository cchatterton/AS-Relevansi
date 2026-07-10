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
        if (!config.enabled) {
            return;
        }

        var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-wp7rss-search-block]'));
        var bot = document.querySelector('[data-wp7rss-search-bot]');
        if (!blocks.length || !bot || isDismissed(config.rememberDismissal || 'session')) {
            return;
        }

        if (config.hideMobile && window.matchMedia('(max-width: 640px)').matches) {
            return;
        }

        var visibleBlocks = new Set(blocks);
        var timer = null;
        var toggle = bot.querySelector('[data-wp7rss-bot-toggle]');
        var dismiss = bot.querySelector('[data-wp7rss-bot-dismiss]');

        function hide() {
            bot.hidden = true;
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        function show() {
            if (!visibleBlocks.size) {
                bot.hidden = false;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
        }

        function schedule() {
            window.clearTimeout(timer);
            if (visibleBlocks.size) {
                hide();
                return;
            }
            timer = window.setTimeout(show, config.delay || 8000);
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                bot.hidden = !bot.hidden;
                toggle.setAttribute('aria-expanded', bot.hidden ? 'false' : 'true');
            });
        }

        if (dismiss) {
            dismiss.addEventListener('click', function () {
                setDismissed(config.rememberDismissal || 'session');
                hide();
            });
        }

        if (!('IntersectionObserver' in window)) {
            window.setTimeout(show, config.delay || 8000);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    visibleBlocks.add(entry.target);
                } else {
                    visibleBlocks.delete(entry.target);
                }
            });
            schedule();
        }, { threshold: 0.05 });

        blocks.forEach(function (block) {
            observer.observe(block);
        });
    }

    ready(initSearchBot);
})();
