<?php
defined('ABSPATH') || exit;

class DW_Consent_Scripts {

    public static function init() {
        add_action('wp_head', [__CLASS__, 'output_consent_defaults'], 0);
        add_action('wp_head', [__CLASS__, 'output_early_scripts'], 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);
        add_action('wp_footer', [__CLASS__, 'output_banner'], 10);
        add_action('wp_footer', [__CLASS__, 'output_footer_scripts'], 21);
        add_action('wp_ajax_dw_log_consent', [__CLASS__, 'ajax_log_consent']);
        add_action('wp_ajax_nopriv_dw_log_consent', [__CLASS__, 'ajax_log_consent']);
    }

    /**
     * Always output Consent Mode v2 defaults (denied) before anything else.
     * Also blocks Matomo tracking until consent is given.
     */
    public static function output_consent_defaults() {
        $settings = DW_Consent_Settings::get_all();
        $matomo_enabled = self::is_script_enabled($settings, 'matomo');

        ?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  analytics_storage: 'denied',
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied'
});
</script>
<?php if ($matomo_enabled) : ?>
<script>
var _paq = window._paq = window._paq || [];
_paq.unshift(['requireCookieConsent']);
_paq.unshift(['requireConsent']);
</script>
<?php endif; ?>
        <?php
    }

    /**
     * If consent cookie exists, inject head scripts immediately (no JS delay).
     */
    public static function output_early_scripts() {
        $consent = self::read_consent_cookie();
        if (!$consent) {
            return;
        }

        $settings = DW_Consent_Settings::get_all();

        // Update consent mode immediately
        ?>
<script>
gtag('consent', 'update', {
  analytics_storage: <?php echo $consent['analytics'] ? "'granted'" : "'denied'"; ?>,
  ad_storage: <?php echo $consent['marketing'] ? "'granted'" : "'denied'"; ?>,
  ad_user_data: <?php echo $consent['marketing'] ? "'granted'" : "'denied'"; ?>,
  ad_personalization: <?php echo $consent['marketing'] ? "'granted'" : "'denied'"; ?>
});
</script>
<?php if ($consent['analytics'] && self::is_script_enabled($settings, 'matomo')) : ?>
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['setConsentGiven']);
_paq.push(['setCookieConsentGiven']);
</script>
<?php endif; ?>
        <?php

        // Inject head scripts for consented tiers
        self::inject_scripts($settings, $consent, 'head');
    }

    /**
     * Inject footer scripts for consented tiers.
     */
    public static function output_footer_scripts() {
        $consent = self::read_consent_cookie();
        if (!$consent) {
            return;
        }

        $settings = DW_Consent_Settings::get_all();
        self::inject_scripts($settings, $consent, 'footer');
    }

    /**
     * Enqueue frontend consent banner JS and CSS.
     */
    public static function enqueue_frontend() {
        $settings = DW_Consent_Settings::get_all();
        $banner_text = DW_Consent_Settings::get_banner_text_for_language();

        wp_enqueue_style(
            'dw-consent',
            plugin_dir_url(DW_CONSENT_FILE) . 'assets/consent.css',
            [],
            DW_CONSENT_VERSION
        );

        wp_enqueue_script(
            'dw-consent',
            plugin_dir_url(DW_CONSENT_FILE) . 'assets/consent.js',
            [],
            DW_CONSENT_VERSION,
            true
        );

        // Build scripts config for JS (so it can inject scripts dynamically on consent change)
        $scripts_config = self::build_scripts_config($settings);

        $js_config = [
            'cookieName'     => $settings['cookie_name'],
            'cookieDomain'   => $settings['cookie_domain'],
            'cookieLifetime' => (int) $settings['cookie_lifetime'],
            'consentVersion' => (int) $settings['consent_version'],
            'scripts'        => $scripts_config,
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('dw_log_consent'),
        ];

        wp_add_inline_script(
            'dw-consent',
            'window.dwConsent=' . wp_json_encode($js_config) . ';',
            'before'
        );
    }

    /**
     * Output the consent banner HTML.
     */
    public static function output_banner() {
        $settings    = DW_Consent_Settings::get_all();
        $text        = DW_Consent_Settings::get_banner_text_for_language();
        $cookie_icon = plugin_dir_url(DW_CONSENT_FILE) . 'assets/cookie-icon.svg';

        ?>
<div id="consent-overlay" hidden></div>

<div id="consent-banner" hidden role="dialog" aria-modal="true" aria-labelledby="consent-title">
  <section class="consent-content">
    <h2 id="consent-title"><?php echo esc_html($text['title']); ?></h2>
    <p><?php echo wp_kses_post($text['description']); ?></p>
    <p><?php echo esc_html($text['change_notice']); ?></p>
    <?php if (!empty($text['cookie_policy_url'])) : ?>
    <p><a href="<?php echo esc_url($text['cookie_policy_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($text['policy_link_text']); ?></a></p>
    <?php endif; ?>

    <details class="consent-details">
      <summary><?php echo esc_html($text['details_toggle']); ?></summary>
      <div class="consent-options-explanation">
        <div class="option-explain">
          <strong><?php echo esc_html($text['reject_btn']); ?>:</strong> <?php echo esc_html($text['details_reject']); ?>
        </div>
        <div class="option-explain">
          <strong><?php echo esc_html($text['statistics_btn']); ?>:</strong> <?php echo esc_html($text['details_statistics']); ?>
        </div>
        <div class="option-explain">
          <strong><?php echo esc_html($text['accept_btn']); ?>:</strong> <?php echo esc_html($text['details_accept']); ?>
        </div>
      </div>
    </details>
  </section>

  <section class="consent-buttons">
    <button data-consent="none"><?php echo esc_html($text['reject_btn']); ?></button>
    <button data-consent="statistics"><?php echo esc_html($text['statistics_btn']); ?></button>
    <button data-consent="all"><?php echo esc_html($text['accept_btn']); ?></button>
  </section>
</div>

<button id="consent-cookie-icon" hidden aria-label="<?php esc_attr_e('Manage cookie preferences', 'dw-consent'); ?>">
  <img src="<?php echo esc_url($cookie_icon); ?>" alt="<?php esc_attr_e('Cookie settings', 'dw-consent'); ?>">
</button>
        <?php
    }

    /**
     * AJAX handler for logging consent.
     */
    public static function ajax_log_consent() {
        check_ajax_referer('dw_log_consent', 'nonce');

        $choices = isset($_POST['choices']) ? json_decode(wp_unslash($_POST['choices']), true) : null;
        $version = isset($_POST['consent_version']) ? absint($_POST['consent_version']) : 1;

        if (!is_array($choices)) {
            wp_send_json_error('Invalid choices', 400);
        }

        $sanitized_choices = [
            'analytics' => !empty($choices['analytics']),
            'marketing' => !empty($choices['marketing']),
        ];

        $result = DW_Consent_Log::log_consent($sanitized_choices, $version);
        wp_send_json_success(['logged' => (bool) $result]);
    }

    /**
     * Read consent from cookie (server-side).
     */
    private static function read_consent_cookie() {
        $settings    = DW_Consent_Settings::get_all();
        $cookie_name = $settings['cookie_name'];

        if (!isset($_COOKIE[$cookie_name])) {
            return null;
        }

        $raw = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return null;
        }

        // Check consent version
        $stored_version = isset($data['v']) ? (int) $data['v'] : 0;
        if ($stored_version < (int) $settings['consent_version']) {
            return null; // Outdated consent, re-prompt
        }

        return [
            'analytics' => !empty($data['analytics']),
            'marketing' => !empty($data['marketing']),
        ];
    }

    /**
     * Check if a built-in script is enabled.
     */
    private static function is_script_enabled($settings, $id) {
        foreach ($settings['scripts'] as $script) {
            if ($script['id'] === $id && !empty($script['enabled'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build scripts config array for the frontend JS.
     */
    private static function build_scripts_config($settings) {
        $config = [
            'statistics' => [],
            'marketing'  => [],
        ];

        foreach ($settings['scripts'] as $script) {
            // Matomo doesn't need a tracking_id — it's managed by Connect Matomo
            if ($script['id'] === 'matomo') {
                if (!empty($script['enabled'])) {
                    $tier = $script['tier'] === 'marketing' ? 'marketing' : 'statistics';
                    $config[$tier][] = [
                        'id'   => 'matomo',
                        'type' => 'builtin',
                    ];
                }
                continue;
            }
            if (empty($script['enabled']) || empty($script['tracking_id'])) {
                continue;
            }
            $tier = $script['tier'] === 'marketing' ? 'marketing' : 'statistics';
            $config[$tier][] = [
                'id'          => $script['id'],
                'type'        => 'builtin',
                'tracking_id' => $script['tracking_id'],
            ];
        }

        foreach ($settings['custom_scripts'] as $script) {
            if (empty($script['enabled'])) {
                continue;
            }
            $tier = $script['tier'] === 'marketing' ? 'marketing' : 'statistics';
            $config[$tier][] = [
                'id'       => $script['id'],
                'type'     => 'custom',
                'code'     => $script['code'],
                'position' => $script['position'] ?? 'head',
            ];
        }

        return $config;
    }

    /**
     * Inject tracking scripts based on consent and position.
     */
    private static function inject_scripts($settings, $consent, $position) {
        $tiers_granted = [];
        if (!empty($consent['analytics'])) {
            $tiers_granted[] = 'statistics';
        }
        if (!empty($consent['marketing'])) {
            $tiers_granted[] = 'marketing';
        }

        if (empty($tiers_granted)) {
            return;
        }

        // Built-in scripts (always head)
        if ($position === 'head') {
            self::inject_builtin_scripts($settings, $tiers_granted);
        }

        // Custom scripts by position
        foreach ($settings['custom_scripts'] as $script) {
            if (empty($script['enabled'])) {
                continue;
            }
            $script_pos = $script['position'] ?? 'head';
            $tier       = $script['tier'] ?? 'statistics';

            if ($script_pos === $position && in_array($tier, $tiers_granted, true)) {
                echo "\n<!-- Custom: " . esc_html($script['name']) . " -->\n";
                echo $script['code'] . "\n";
            }
        }
    }

    /**
     * Inject built-in tracking scripts.
     */
    private static function inject_builtin_scripts($settings, $tiers_granted) {
        $ga4_id   = '';
        $gads_id  = '';
        $meta_id  = '';
        $li_id    = '';
        $clarity_id = '';

        foreach ($settings['scripts'] as $script) {
            if (empty($script['enabled']) || empty($script['tracking_id'])) {
                continue;
            }
            if (!in_array($script['tier'], $tiers_granted, true)) {
                continue;
            }
            switch ($script['id']) {
                case 'ga4':
                    $ga4_id = $script['tracking_id'];
                    break;
                case 'gads':
                    $gads_id = $script['tracking_id'];
                    break;
                case 'meta':
                    $meta_id = $script['tracking_id'];
                    break;
                case 'linkedin':
                    $li_id = $script['tracking_id'];
                    break;
                case 'clarity':
                    $clarity_id = $script['tracking_id'];
                    break;
            }
        }

        // Google Analytics 4 / Google Ads (shared gtag.js)
        $gtag_id = $ga4_id ?: $gads_id;
        if ($gtag_id) {
            ?>
<script id="dw-gtag-js" async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($gtag_id); ?>"></script>
<script id="dw-script-<?php echo $ga4_id ? 'ga4' : 'gads'; ?>">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
<?php if ($ga4_id) : ?>
gtag('config', '<?php echo esc_js($ga4_id); ?>');
<?php endif; ?>
<?php if ($gads_id) : ?>
gtag('config', '<?php echo esc_js($gads_id); ?>');
<?php endif; ?>
</script>
            <?php
        }

        // Meta Pixel
        if ($meta_id) {
            ?>
<script id="dw-script-meta">
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?php echo esc_js($meta_id); ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr($meta_id); ?>&ev=PageView&noscript=1" /></noscript>
            <?php
        }

        // LinkedIn Insight Tag
        if ($li_id) {
            ?>
<script id="dw-script-linkedin" type="text/javascript">
_linkedin_partner_id = "<?php echo esc_js($li_id); ?>";
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
window._linkedin_data_partner_ids.push(_linkedin_partner_id);
</script>
<script type="text/javascript">
(function(l) {
if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
window.lintrk.q=[]}
var s = document.getElementsByTagName("script")[0];
var b = document.createElement("script");
b.type = "text/javascript";b.async = true;
b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
s.parentNode.insertBefore(b, s);})(window.lintrk);
</script>
<noscript><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=<?php echo esc_attr($li_id); ?>&fmt=gif" /></noscript>
            <?php
        }

        // Microsoft Clarity
        if ($clarity_id) {
            ?>
<script id="dw-script-clarity" type="text/javascript">
(function(c,l,a,r,i,t,y){
    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
})(window, document, "clarity", "script", "<?php echo esc_js($clarity_id); ?>");
</script>
            <?php
        }
    }
}
