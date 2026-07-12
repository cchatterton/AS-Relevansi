# Changelog

All notable changes to Relevanssi Extended are recorded here.

## 0.1.14 - 2026-07-12

- Removed the AI temperature request option so native connectors using models that do not support temperature can build Topic Maps.

## 0.1.13 - 2026-07-12

- Fixed native AI JSON schemas to satisfy strict OpenAI response schema requirements.
- Updated failed Topic Map status writes to record current plugin version and source packet counts.

## 0.1.12 - 2026-07-12

- Added native WordPress 7 AI Connector calls for Site Topic Map generation and live semantic keyword expansion.
- Added skipped-search AI log rows when semantic expansion is blocked by missing Relevanssi, unavailable AI, or a non-ready Topic Map.
- Added AI Logs details output for skip reasons, errors, cache hits, and returned semantic terms.
- Preserved adapter filters as overrides while making native WordPress AI the default path.

## 0.1.11 - 2026-07-12

- Renamed the editor block to "Search" so clients can find it without knowing the plugin name.
- Removed Search Block inspector controls for content and implementation metadata.

## 0.1.10 - 2026-07-11

- Fixed tabbed settings saves so General, AI Connector, Search Bot, and Advanced tabs no longer overwrite each other.
- Saving the CSS toggle no longer clears Search Bot settings, and saving Search Bot settings no longer changes the CSS toggle.

## 0.1.9 - 2026-07-11

- Changed Search Bot dismiss behaviour so the close button closes only the speech bubble and leaves the launcher visible.
- Removed dismissal persistence from the Search Bot so the launcher remains available across page views while enabled.
- Added a General setting to enable or disable enqueueing the plugin CSS file.

## 0.1.8 - 2026-07-11

- Added a prepared topics response view to the Site Topic Map settings tab.
- Shows stored topic cards, protected terms, warnings, and escaped raw JSON for the latest ready topic map.

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
