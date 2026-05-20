<?php
/**
 * Plugin Name: DW Cookie Consent
 * Description: Lightweight consent banner with tracking script management, Google Consent Mode v2, multi-language support, and consent logging.
 * Version: 2.1.0
 * Author: Daniel Wirenfeldt
 * Text Domain: dw-consent
 */

defined('ABSPATH') || exit;

define('DW_CONSENT_VERSION', '2.1.0');
define('DW_CONSENT_FILE', __FILE__);
define('DW_CONSENT_PATH', plugin_dir_path(__FILE__));

// Load classes
require_once DW_CONSENT_PATH . 'includes/class-consent-settings.php';
require_once DW_CONSENT_PATH . 'includes/class-consent-log.php';
require_once DW_CONSENT_PATH . 'includes/class-consent-admin.php';
require_once DW_CONSENT_PATH . 'includes/class-consent-scripts.php';

// Activation: create DB table
register_activation_hook(__FILE__, function () {
    DW_Consent_Log::create_table();

    // Set defaults if not already set
    if (!get_option(DW_Consent_Settings::OPTION_KEY)) {
        update_option(DW_Consent_Settings::OPTION_KEY, DW_Consent_Settings::get_defaults());
    }
});

// Add settings link on Plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = admin_url('options-general.php?page=dw-consent');
    array_unshift($links, '<a href="' . esc_url($url) . '">Indstillinger</a>');
    return $links;
});

// Initialize
add_action('init', function () {
    if (is_admin()) {
        DW_Consent_Admin::init();
    }
    DW_Consent_Scripts::init();
});
