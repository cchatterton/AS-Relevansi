# Relevanssi Extended

Author: AlphaSys  
Version: 0.1.20  
Status: Alpha / MVP  

## Purpose

Relevanssi Extended adds a reusable WordPress search block, optional AI-assisted semantic query expansion, a cached Site Topic Map, a floating Search Bot companion, and admin-visible AI telemetry while preserving the visitor's original WordPress search query.

## Key Features

- Server-rendered Search Block that submits the canonical `s` query parameter.
- Native WordPress 7 AI Connector integration for topic maps and semantic keyword expansion.
- Site Topic Map background build and status tracking.
- AI call logging in custom database tables with bounded retention.
- Optional floating Search Bot for frontend search assistance.
- GitHub release updater metadata and native WordPress update integration.

## Folder Structure

- `as-relevansi/` - installable WordPress plugin folder.
- `as-relevansi/functions/` - setup, admin, assets, REST, helpers, and updater code.
- `as-relevansi/blocks/search-block/` - block metadata, editor script, and PHP render template.
- `as-relevansi/scripts/` - frontend/admin JavaScript.
- `as-relevansi/styles/` - scoped plugin CSS.
- `scripts/build-plugin-zip.sh` - release ZIP builder.

## Important Notes

- The plugin does not replace Relevanssi.
- The public URL, `$_GET['s']`, `get_search_query()`, and Relevanssi reports remain the original human-entered query.
- Native WordPress 7 AI Connector support is built in. Adapter filters remain available for site-specific overrides:
  - `wp7rss_ai_connector_status`
  - `wp7rss_ai_build_topic_map`
  - `wp7rss_ai_expand_search_query`
- Relevanssi semantic-term application is exposed through `wp7rss_semantic_terms_ready` so the site-specific safest Relevanssi hook can be wired once the installed Relevanssi version is known.

## Future Considerations

- Add a site-tested Relevanssi result merge/weighting adapter.
- Expand AI Logs with detail views, filters, and export.
- Add richer media-library controls for Search Bot image selection.
