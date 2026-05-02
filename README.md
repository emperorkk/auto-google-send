# Yoast SEO Assistant (AI Tags)

A WordPress plugin package that extends Yoast SEO free workflows with AI-assisted SEO completion tools.

## What it does

- Finds recent published posts that are missing tags.
- Lets you generate tags per post or in bulk from the dashboard.
- Adds a manual "Generate tags + meta with AI" button in the post editor.
- Optionally generates Yoast SEO meta title and meta description.
- Uses OpenAI API with configurable API key and model.

## Plugin location

- Main plugin: `yoast-seo-assistant/yoast-seo-assistant.php`
- WordPress readme: `yoast-seo-assistant/readme.txt`
- Uninstall cleanup: `yoast-seo-assistant/uninstall.php`

## Requirements

- WordPress 6.9+
- PHP 8.2+
- OpenAI API key

## Quick start (local/dev)

1. Copy the `yoast-seo-assistant` folder to your WordPress `/wp-content/plugins/` directory.
2. Activate **Yoast SEO Assistant (AI Tags)** from **Plugins**.
3. Open **Settings → KSEO AI Tags**.
4. Add your OpenAI API key.
5. Keep model as `gpt-4.1-mini` (default), or choose another supported model.
6. Visit **Dashboard** and use the widget to auto-complete untagged posts.

## Settings

The plugin stores one option in `wp_options`:

- `kseo_ai_tags_settings`
  - `api_key`
  - `model`
  - `enable_meta` (boolean)

## Security notes

- Uses WordPress capability checks (`manage_options`, `edit_posts`).
- Uses nonce verification for single and bulk generation actions.
- Sanitizes settings and generated fields before writing to the database.

## Current status

This repository currently contains implementation-focused plugin code and basic package files.
A future iteration can add:

- Automated tests
- Better request retries/error telemetry
- More advanced prompt templates per category/post type
- i18n string extraction and translation templates
