<?php
defined('ABSPATH') || exit;

$settings = DW_Consent_Settings::get_all();

if (!empty($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field('dw_consent_save_settings'); ?>
    <input type="hidden" name="dw_consent_action" value="save_settings">

    <table class="form-table">
        <tr>
            <th scope="row"><label for="cookie_domain">Cookie Domain</label></th>
            <td>
                <input type="text" id="cookie_domain" name="cookie_domain"
                       value="<?php echo esc_attr($settings['cookie_domain']); ?>"
                       class="regular-text"
                       placeholder="Leave empty for auto-detect">
                <p class="description">
                    Set to <code>.yourdomain.com</code> to share across subdomains. Leave empty to use the current domain.
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="cookie_lifetime">Cookie Lifetime (days)</label></th>
            <td>
                <input type="number" id="cookie_lifetime" name="cookie_lifetime"
                       value="<?php echo esc_attr($settings['cookie_lifetime']); ?>"
                       min="1" max="730" class="small-text">
                <p class="description">How long the consent cookie persists. Default: 365 days.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="cookie_name">Cookie Name</label></th>
            <td>
                <input type="text" id="cookie_name" name="cookie_name"
                       value="<?php echo esc_attr($settings['cookie_name']); ?>"
                       class="regular-text">
                <p class="description">Name of the consent cookie. Only alphanumeric, hyphens, and underscores. Default: <code>site_consent</code>.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Consent Version</th>
            <td>
                <p>Current version: <strong><?php echo esc_html($settings['consent_version']); ?></strong></p>
                <label>
                    <input type="checkbox" name="increment_version" value="1">
                    Increment consent version on save
                </label>
                <p class="description">
                    When the version is incremented, the consent banner will re-appear for all visitors who previously consented under an older version. Use this after significant changes to your cookie policy or tracking setup.
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button('Save Settings'); ?>
</form>

<hr>

<h2>Plugin Info</h2>
<table class="form-table">
    <tr>
        <th>Plugin Version</th>
        <td><code><?php echo esc_html(DW_CONSENT_VERSION); ?></code></td>
    </tr>
    <tr>
        <th>Active Scripts</th>
        <td>
            <?php
            $active = array_filter($settings['scripts'], function ($s) {
                return !empty($s['enabled']) && !empty($s['tracking_id']);
            });
            $active_custom = array_filter($settings['custom_scripts'], function ($s) {
                return !empty($s['enabled']);
            });
            $total = count($active) + count($active_custom);
            echo esc_html($total) . ' script(s) active';
            ?>
        </td>
    </tr>
    <tr>
        <th>Consent Log Entries</th>
        <td><?php echo esc_html(number_format_i18n(DW_Consent_Log::get_total_count())); ?></td>
    </tr>
    <tr>
        <th>Languages Configured</th>
        <td><?php echo esc_html(implode(', ', array_map('strtoupper', array_keys($settings['banner_texts'])))); ?></td>
    </tr>
</table>
