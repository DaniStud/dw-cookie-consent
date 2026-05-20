<?php
defined('ABSPATH') || exit;

class DW_Consent_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'handle_form_submissions']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_dw_consent_save_scripts', [__CLASS__, 'ajax_save_scripts']);
    }

    public static function add_menu_page() {
        add_options_page(
            'Consent Banner',
            'Consent Banner',
            'manage_options',
            'dw-consent',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'settings_page_dw-consent') {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_style(
            'dw-consent-admin',
            plugin_dir_url(DW_CONSENT_FILE) . 'admin/admin.css',
            [],
            DW_CONSENT_VERSION
        );

        wp_enqueue_script(
            'dw-consent-admin',
            plugin_dir_url(DW_CONSENT_FILE) . 'admin/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            DW_CONSENT_VERSION,
            true
        );

        wp_localize_script('dw-consent-admin', 'dwConsentAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dw_consent_admin'),
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'scripts';
        $tabs = [
            'scripts'  => __('Tracking Scripts', 'dw-consent'),
            'banner'   => __('Banner Text', 'dw-consent'),
            'settings' => __('Settings', 'dw-consent'),
            'log'      => __('Consent Log', 'dw-consent'),
        ];

        // Whitelist tab slugs to prevent path traversal
        if (!array_key_exists($active_tab, $tabs)) {
            $active_tab = 'scripts';
        }

        ?>
        <div class="wrap dw-consent-wrap">
            <h1>Consent Banner</h1>
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'dw-consent', 'tab' => $slug], admin_url('options-general.php'))); ?>"
                       class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="dw-consent-content">
                <?php
                $view_file = plugin_dir_path(DW_CONSENT_FILE) . "admin/views/page-{$active_tab}.php";
                if (file_exists($view_file)) {
                    include $view_file;
                }
                ?>
            </div>
        </div>
        <?php
    }

    public static function handle_form_submissions() {
        if (!isset($_POST['dw_consent_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $action = sanitize_text_field(wp_unslash($_POST['dw_consent_action']));

        switch ($action) {
            case 'save_scripts':
                check_admin_referer('dw_consent_save_scripts');
                self::process_save_scripts();
                break;

            case 'save_banner':
                check_admin_referer('dw_consent_save_banner');
                self::process_save_banner();
                break;

            case 'save_settings':
                check_admin_referer('dw_consent_save_settings');
                self::process_save_settings();
                break;

            case 'export_csv':
                check_admin_referer('dw_consent_export_csv');
                DW_Consent_Log::export_csv();
                break;

            case 'purge_log':
                check_admin_referer('dw_consent_purge_log');
                self::process_purge_log();
                break;
        }
    }

    private static function process_save_scripts() {
        $current  = DW_Consent_Settings::get_all();
        $scripts  = [];

        if (isset($_POST['scripts']) && is_array($_POST['scripts'])) {
            foreach ($_POST['scripts'] as $script_data) {
                $scripts[] = [
                    'id'          => sanitize_text_field($script_data['id'] ?? ''),
                    'type'        => 'builtin',
                    'name'        => sanitize_text_field($script_data['name'] ?? ''),
                    'tracking_id' => sanitize_text_field($script_data['tracking_id'] ?? ''),
                    'tier'        => sanitize_text_field($script_data['tier'] ?? 'statistics'),
                    'enabled'     => !empty($script_data['enabled']),
                ];
            }
        }

        $custom_scripts = [];
        if (isset($_POST['custom_scripts']) && is_array($_POST['custom_scripts'])) {
            if (!current_user_can('unfiltered_html')) {
                wp_die(esc_html__('You do not have permission to save unfiltered script code.', 'dw-consent'));
            }
            foreach ($_POST['custom_scripts'] as $cs) {
                if (empty($cs['name'])) {
                    continue;
                }
                $custom_scripts[] = [
                    'id'       => sanitize_text_field($cs['id'] ?? wp_generate_uuid4()),
                    'name'     => sanitize_text_field($cs['name'] ?? ''),
                    'code'     => $cs['code'] ?? '',
                    'tier'     => sanitize_text_field($cs['tier'] ?? 'statistics'),
                    'position' => sanitize_text_field($cs['position'] ?? 'head'),
                    'enabled'  => !empty($cs['enabled']),
                ];
            }
        }

        $current['scripts']        = $scripts;
        $current['custom_scripts'] = $custom_scripts;
        DW_Consent_Settings::save($current);

        wp_safe_redirect(add_query_arg([
            'page'    => 'dw-consent',
            'tab'     => 'scripts',
            'updated' => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    private static function process_save_banner() {
        $current = DW_Consent_Settings::get_all();
        $texts   = [];

        if (isset($_POST['banner_texts']) && is_array($_POST['banner_texts'])) {
            foreach ($_POST['banner_texts'] as $lang => $fields) {
                $texts[sanitize_text_field($lang)] = $fields;
            }
        }

        if (isset($_POST['default_language'])) {
            $current['default_language'] = sanitize_text_field(wp_unslash($_POST['default_language']));
        }

        $current['banner_texts'] = $texts;
        DW_Consent_Settings::save($current);

        wp_safe_redirect(add_query_arg([
            'page'    => 'dw-consent',
            'tab'     => 'banner',
            'updated' => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    private static function process_save_settings() {
        $current = DW_Consent_Settings::get_all();

        $current['cookie_domain']   = isset($_POST['cookie_domain']) ? sanitize_text_field(wp_unslash($_POST['cookie_domain'])) : '';
        $current['cookie_lifetime'] = isset($_POST['cookie_lifetime']) ? absint($_POST['cookie_lifetime']) : 365;
        $current['cookie_name']     = isset($_POST['cookie_name']) ? sanitize_text_field(wp_unslash($_POST['cookie_name'])) : 'site_consent';

        if (!empty($_POST['increment_version'])) {
            $current['consent_version'] = ($current['consent_version'] ?? 1) + 1;
        }

        DW_Consent_Settings::save($current);

        wp_safe_redirect(add_query_arg([
            'page'    => 'dw-consent',
            'tab'     => 'settings',
            'updated' => '1',
        ], admin_url('options-general.php')));
        exit;
    }

    private static function process_purge_log() {
        $days = isset($_POST['purge_days']) ? absint($_POST['purge_days']) : 0;
        if ($days > 0) {
            $deleted = DW_Consent_Log::purge_older_than($days);
            wp_safe_redirect(add_query_arg([
                'page'    => 'dw-consent',
                'tab'     => 'log',
                'purged'  => $deleted,
            ], admin_url('options-general.php')));
        } else {
            wp_safe_redirect(add_query_arg([
                'page' => 'dw-consent',
                'tab'  => 'log',
            ], admin_url('options-general.php')));
        }
        exit;
    }

    public static function ajax_save_scripts() {
        check_ajax_referer('dw_consent_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $current = DW_Consent_Settings::get_all();

        if (isset($_POST['scripts'])) {
            $scripts_data = json_decode(wp_unslash($_POST['scripts']), true);
            if (is_array($scripts_data)) {
                $current['scripts'] = $scripts_data;
            }
        }

        if (isset($_POST['custom_scripts'])) {
            $custom_data = json_decode(wp_unslash($_POST['custom_scripts']), true);
            if (is_array($custom_data)) {
                $current['custom_scripts'] = $custom_data;
            }
        }

        DW_Consent_Settings::save($current);
        wp_send_json_success();
    }
}
