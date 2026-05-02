<?php
/**
 * Cleanup on plugin uninstall.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('kseo_ai_tags_settings');
