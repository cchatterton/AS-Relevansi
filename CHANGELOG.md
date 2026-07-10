# Changelog

All notable changes to Relevanssi Extended are recorded here.

## 0.1.7 - 2026-07-10

- Redesigned Search Bot as a chat-style launcher and panel.
- Added Search Bot left/right placement setting.
- Replaced avatar attachment ID entry with a WordPress media-library picker.
- Removed content-owned Search Block defaults from plugin settings.
- Removed manual AI feature toggles from General settings; AI behavior now follows native WP7 connector availability.

## 0.1.6 - 2026-07-10

- Changed Search Bot behavior to show on all frontend pages when enabled, except search results pages.
- Removed dependency on detecting a Search Block in the page DOM.

## 0.1.5 - 2026-07-10

- Changed Search Bot detection to watch the live DOM for Search Blocks injected after header UX interactions.
- Prevents the bot script from exiting early when the Search Block is loaded by JavaScript instead of being present in initial page source.

## 0.1.4 - 2026-07-10

- Added Search Bot fallback detection for rendered header search menu links, including `/row/search/`.
- Prevents the bot from silently exiting when the site header exposes search as a menu/row link instead of a rendered Search Block.

## 0.1.3 - 2026-07-10

- Added Search Bot support for Search Blocks placed in sticky headers or banner template parts.
- Clarified that Search Bot detection uses rendered frontend DOM, not post-content scanning.

## 0.1.2 - 2026-07-10

- Fixed Search Bot delay handling so `0` seconds shows immediately after the Search Block leaves view.
- Restored default Search Bot text when settings fields are left blank.
- Added Search Bot dismissal mode control and admin preview.

## 0.1.1 - 2026-07-10

- Added native WordPress 7 Connectors detection for configured AI providers.
- Recognises connected providers through the WP AI Client registry, including OpenAI.

## 0.1.0 - 2026-07-10

- Added initial AlphaSys plugin scaffold.
- Added server-rendered Search Block preserving the original WordPress `s` query.
- Added optional AI Connector filter integration points.
- Added Site Topic Map background build flow and status metadata.
- Added AI telemetry tables and bounded retention cleanup.
- Added optional Search Bot companion.
- Added GitHub release updater support and build script.
