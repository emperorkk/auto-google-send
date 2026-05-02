=== Yoast SEO Assistant (AI Tags) ===
Contributors: kourentzes
Requires at least: 6.9
Tested up to: 6.9.4
Requires PHP: 8.2
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO helper for Yoast free users: generate tags and Yoast metadata from the WordPress dashboard and post editor.

== Description ==

This plugin helps you recover missed SEO tasks:

* Generate missing tags for posts using OpenAI.
* Optional generation of Yoast SEO title and meta description.
* Dashboard widget listing recent published posts without tags.
* Single-post and bulk actions to auto-complete posts.
* Settings page to store OpenAI API key and model selection.

== Installation ==

1. Upload `yoast-seo-assistant` folder to `/wp-content/plugins/`.
2. Activate plugin in **Plugins**.
3. Go to **Settings → KSEO AI Tags**.
4. Add API key and model (default `gpt-4.1-mini`).

== Changelog ==

= 0.3.0 =
* Add package files for WordPress plugin distribution.
* Add admin styles and JS.
* Add uninstall cleanup routine.
* Add i18n wrapper and constants.
