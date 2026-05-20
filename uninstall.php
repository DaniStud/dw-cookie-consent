<?php
/**
 * Uninstall DW Cookie Consent.
 * Runs when the plugin is deleted via WP admin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove settings
delete_option('dw_consent_settings');

// Drop custom table
global $wpdb;
$table = $wpdb->prefix . 'consent_log';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
