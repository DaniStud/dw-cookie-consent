<?php
defined('ABSPATH') || exit;

class DW_Consent_Settings {

    const OPTION_KEY = 'dw_consent_settings';

    public static function get_defaults() {
        return [
            'cookie_domain'    => '',
            'cookie_lifetime'  => 365,
            'cookie_name'      => 'site_consent',
            'consent_version'  => 1,
            'default_language' => 'da',
            'scripts'          => [
                [
                    'id'          => 'ga4',
                    'type'        => 'builtin',
                    'name'        => 'Google Analytics 4',
                    'tracking_id' => '',
                    'tier'        => 'statistics',
                    'enabled'     => false,
                ],
                [
                    'id'          => 'gads',
                    'type'        => 'builtin',
                    'name'        => 'Google Ads',
                    'tracking_id' => '',
                    'tier'        => 'marketing',
                    'enabled'     => false,
                ],
                [
                    'id'          => 'meta',
                    'type'        => 'builtin',
                    'name'        => 'Meta Pixel',
                    'tracking_id' => '',
                    'tier'        => 'marketing',
                    'enabled'     => false,
                ],
                [
                    'id'          => 'linkedin',
                    'type'        => 'builtin',
                    'name'        => 'LinkedIn Insight',
                    'tracking_id' => '',
                    'tier'        => 'marketing',
                    'enabled'     => false,
                ],
                [
                    'id'          => 'clarity',
                    'type'        => 'builtin',
                    'name'        => 'Microsoft Clarity',
                    'tracking_id' => '',
                    'tier'        => 'statistics',
                    'enabled'     => false,
                ],
                [
                    'id'          => 'matomo',
                    'type'        => 'builtin',
                    'name'        => 'Matomo / Connect Matomo',
                    'tracking_id' => '',
                    'tier'        => 'statistics',
                    'enabled'     => false,
                ],
            ],
            'custom_scripts'   => [],
            'banner_texts'     => [
                'da' => [
                    'title'             => 'Denne hjemmeside bruger cookies',
                    'description'       => 'Vi og tredjeparter bruger cookies til at personalisere din brugeroplevelse, til markedsføring og til at se hvordan vores hjemmeside anvendes af besøgende. Du kan vælge eller fravælge de forskellige cookies herunder:',
                    'change_notice'     => 'Du kan til enhver tid ændre eller trække dit samtykke tilbage ved at klikke nederst på hjemmesiden.',
                    'policy_link_text'  => 'om cookies',
                    'cookie_policy_url' => '',
                    'details_toggle'    => 'Vis alle',
                    'reject_btn'        => 'Afvis alle',
                    'statistics_btn'    => 'Kun statistik',
                    'accept_btn'        => 'Accepter alle',
                    'details_reject'    => 'Ingen cookies vil blive sat udover de strengt nødvendige.',
                    'details_statistics'=> 'Vi bruger cookies til at forstå hvordan besøgende bruger vores hjemmeside.',
                    'details_accept'    => 'Vi bruger cookies til statistik og målrettet markedsføring.',
                ],
                'en' => [
                    'title'             => 'This website uses cookies',
                    'description'       => 'We and third parties use cookies to personalise your experience, for marketing and to see how our website is used by visitors. You can select or deselect the different cookies below:',
                    'change_notice'     => 'You can change or withdraw your consent at any time by clicking at the bottom of the website.',
                    'policy_link_text'  => 'about cookies',
                    'cookie_policy_url' => '',
                    'details_toggle'    => 'Show all',
                    'reject_btn'        => 'Reject all',
                    'statistics_btn'    => 'Statistics only',
                    'accept_btn'        => 'Accept all',
                    'details_reject'    => 'No cookies will be set other than strictly necessary ones.',
                    'details_statistics'=> 'We use cookies to understand how visitors use our website.',
                    'details_accept'    => 'We use cookies for statistics and targeted marketing.',
                ],
            ],
        ];
    }

    public static function get_all() {
        $defaults = self::get_defaults();
        $stored   = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return self::merge_deep($defaults, $stored);
    }

    public static function get($key, $default = null) {
        $all = self::get_all();
        return isset($all[$key]) ? $all[$key] : $default;
    }

    public static function save($data) {
        $sanitized = self::sanitize($data);
        return update_option(self::OPTION_KEY, $sanitized);
    }

    public static function sanitize($data) {
        $clean = [];

        if (isset($data['cookie_domain'])) {
            $clean['cookie_domain'] = sanitize_text_field($data['cookie_domain']);
        }
        if (isset($data['cookie_lifetime'])) {
            $clean['cookie_lifetime'] = absint($data['cookie_lifetime']);
            if ($clean['cookie_lifetime'] < 1) {
                $clean['cookie_lifetime'] = 365;
            }
        }
        if (isset($data['cookie_name'])) {
            $clean['cookie_name'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['cookie_name']);
            if (empty($clean['cookie_name'])) {
                $clean['cookie_name'] = 'site_consent';
            }
        }
        if (isset($data['consent_version'])) {
            $clean['consent_version'] = absint($data['consent_version']);
        }
        if (isset($data['default_language'])) {
            $clean['default_language'] = sanitize_text_field($data['default_language']);
        }
        if (isset($data['scripts']) && is_array($data['scripts'])) {
            $clean['scripts'] = self::sanitize_scripts($data['scripts']);
        }
        if (isset($data['custom_scripts']) && is_array($data['custom_scripts'])) {
            $clean['custom_scripts'] = self::sanitize_custom_scripts($data['custom_scripts']);
        }
        if (isset($data['banner_texts']) && is_array($data['banner_texts'])) {
            $clean['banner_texts'] = self::sanitize_banner_texts($data['banner_texts']);
        }

        return $clean;
    }

    private static function sanitize_scripts($scripts) {
        $allowed_ids   = ['ga4', 'gads', 'meta', 'linkedin', 'clarity', 'matomo'];
        $allowed_tiers = ['statistics', 'marketing'];

        // Tracking ID format validation
        $id_patterns = [
            'ga4'      => '/^G-[A-Z0-9]+$/i',
            'gads'     => '/^AW-[A-Z0-9]+$/i',
            'meta'     => '/^\d{10,20}$/',
            'linkedin' => '/^\d{4,15}$/',
            'clarity'  => '/^[a-z0-9]{6,20}$/i',
        ];

        $clean = [];

        foreach ($scripts as $script) {
            if (!isset($script['id']) || !in_array($script['id'], $allowed_ids, true)) {
                continue;
            }

            $tracking_id = sanitize_text_field($script['tracking_id'] ?? '');

            // Validate tracking ID format if non-empty and pattern exists
            if ($tracking_id !== '' && isset($id_patterns[$script['id']])) {
                if (!preg_match($id_patterns[$script['id']], $tracking_id)) {
                    $tracking_id = ''; // Reject malformed IDs
                }
            }

            $clean[] = [
                'id'          => $script['id'],
                'type'        => 'builtin',
                'name'        => sanitize_text_field($script['name'] ?? ''),
                'tracking_id' => $tracking_id,
                'tier'        => in_array($script['tier'] ?? '', $allowed_tiers, true) ? $script['tier'] : 'statistics',
                'enabled'     => !empty($script['enabled']),
            ];
        }

        return $clean;
    }

    private static function sanitize_custom_scripts($scripts) {
        $allowed_tiers     = ['statistics', 'marketing'];
        $allowed_positions = ['head', 'footer'];
        $clean = [];

        foreach ($scripts as $script) {
            if (empty($script['name'])) {
                continue;
            }
            $clean[] = [
                'id'       => sanitize_text_field($script['id'] ?? wp_generate_uuid4()),
                'name'     => sanitize_text_field($script['name']),
                'code'     => $script['code'] ?? '',
                'tier'     => in_array($script['tier'] ?? '', $allowed_tiers, true) ? $script['tier'] : 'statistics',
                'position' => in_array($script['position'] ?? '', $allowed_positions, true) ? $script['position'] : 'head',
                'enabled'  => !empty($script['enabled']),
            ];
        }

        return $clean;
    }

    private static function sanitize_banner_texts($texts) {
        $clean = [];
        $allowed_html = [
            'a'      => ['href' => [], 'target' => [], 'rel' => []],
            'strong' => [],
            'em'     => [],
            'br'     => [],
        ];

        foreach ($texts as $lang => $fields) {
            $lang_key = preg_replace('/[^a-zA-Z_\-]/', '', $lang);
            if (empty($lang_key)) {
                continue;
            }
            $clean[$lang_key] = [
                'title'              => sanitize_text_field($fields['title'] ?? ''),
                'description'        => wp_kses($fields['description'] ?? '', $allowed_html),
                'change_notice'      => wp_kses($fields['change_notice'] ?? '', $allowed_html),
                'policy_link_text'   => sanitize_text_field($fields['policy_link_text'] ?? ''),
                'cookie_policy_url'  => esc_url_raw($fields['cookie_policy_url'] ?? ''),
                'details_toggle'     => sanitize_text_field($fields['details_toggle'] ?? ''),
                'reject_btn'         => sanitize_text_field($fields['reject_btn'] ?? ''),
                'statistics_btn'     => sanitize_text_field($fields['statistics_btn'] ?? ''),
                'accept_btn'         => sanitize_text_field($fields['accept_btn'] ?? ''),
                'details_reject'     => sanitize_text_field($fields['details_reject'] ?? ''),
                'details_statistics' => sanitize_text_field($fields['details_statistics'] ?? ''),
                'details_accept'     => sanitize_text_field($fields['details_accept'] ?? ''),
            ];
        }

        return $clean;
    }

    public static function get_banner_text_for_language($lang = null) {
        $settings  = self::get_all();
        $texts     = $settings['banner_texts'];
        $default   = $settings['default_language'];

        if ($lang === null) {
            $lang = self::detect_language();
        }

        // Try exact match
        if (isset($texts[$lang])) {
            return $texts[$lang];
        }

        // Try base language (e.g., "da" from "da_DK")
        $base = substr($lang, 0, 2);
        if (isset($texts[$base])) {
            return $texts[$base];
        }

        // Fall back to default language
        if (isset($texts[$default])) {
            return $texts[$default];
        }

        // Last resort: first available language
        return reset($texts) ?: self::get_defaults()['banner_texts']['da'];
    }

    public static function detect_language() {
        // TranslatePress
        if (function_exists('trp_get_current_language')) {
            $lang = trp_get_current_language();
            if ($lang) {
                return $lang;
            }
        }

        // WPML
        $wpml_lang = apply_filters('wpml_current_language', null);
        if ($wpml_lang) {
            return $wpml_lang;
        }

        // Polylang
        if (function_exists('pll_current_language')) {
            $lang = pll_current_language('slug');
            if ($lang) {
                return $lang;
            }
        }

        // WordPress locale
        $locale = get_locale();
        return substr($locale, 0, 2);
    }

    private static function merge_deep($defaults, $stored) {
        $merged = $defaults;
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                // For scripts/custom_scripts/banner_texts, use stored value entirely if present
                if (in_array($key, ['scripts', 'custom_scripts', 'banner_texts'], true)) {
                    $merged[$key] = $value;
                } else {
                    $merged[$key] = self::merge_deep($defaults[$key], $value);
                }
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}
