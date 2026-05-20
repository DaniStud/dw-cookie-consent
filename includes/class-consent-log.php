<?php
defined('ABSPATH') || exit;

class DW_Consent_Log {

    const TABLE_SUFFIX = 'consent_log';

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function create_table() {
        global $wpdb;
        $table   = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            consent_hash CHAR(64) NOT NULL,
            consent_choices TEXT NOT NULL,
            consent_version INT UNSIGNED NOT NULL DEFAULT 1,
            user_agent VARCHAR(500) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_consent_hash (consent_hash),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    public static function log_consent($choices, $consent_version) {
        global $wpdb;

        $ip   = self::get_client_ip();
        $ua   = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500) : '';
        $salt = wp_salt('auth');
        $hash = hash('sha256', $ip . $ua . $salt);

        // Rate limit: max 1 log per hash per 5 seconds
        $table  = self::get_table_name();
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE consent_hash = %s AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)",
            $hash
        ));

        if ($recent > 0) {
            return false;
        }

        return $wpdb->insert($table, [
            'consent_hash'    => $hash,
            'consent_choices' => wp_json_encode($choices),
            'consent_version' => absint($consent_version),
            'user_agent'      => $ua,
            'created_at'      => current_time('mysql'),
        ], ['%s', '%s', '%d', '%s', '%s']);
    }

    public static function get_log($args = []) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        $allowed_orderby = ['id', 'created_at', 'consent_version'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $offset  = max(0, ($args['page'] - 1) * $args['per_page']);
        $limit   = absint($args['per_page']);

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ];
    }

    public static function export_csv() {
        global $wpdb;
        $table = self::get_table_name();

        $results = $wpdb->get_results(
            "SELECT id, consent_hash, consent_choices, consent_version, user_agent, created_at FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );

        $filename = 'consent-log-' . gmdate('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Consent Hash', 'Choices', 'Version', 'User Agent', 'Date']);

        foreach ($results as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    public static function purge_older_than($days) {
        global $wpdb;
        $table = self::get_table_name();
        $days  = absint($days);

        if ($days < 1) {
            return 0;
        }

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    public static function get_total_count() {
        global $wpdb;
        $table = self::get_table_name();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    private static function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        // Note: HTTP_X_FORWARDED_FOR is excluded because it is trivially spoofable
        // and should not be trusted without a known proxy whitelist.
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
