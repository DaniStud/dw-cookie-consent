<?php
defined('ABSPATH') || exit;

if (!function_exists('dw_get_id_placeholder')) {
    function dw_get_id_placeholder($id) {
        $placeholders = [
            'ga4'      => 'G-XXXXXXXXXX',
            'gads'     => 'AW-XXXXXXXXX',
            'meta'     => '123456789012345',
            'linkedin' => '1234567',
            'clarity'  => 'abcdefghij',
            'matomo'   => '',
        ];
        return $placeholders[$id] ?? '';
    }
}

$settings = DW_Consent_Settings::get_all();
$scripts  = $settings['scripts'];
$custom   = $settings['custom_scripts'];

$stat_scripts   = [];
$market_scripts = [];

foreach ($scripts as $s) {
    if ($s['tier'] === 'marketing') {
        $market_scripts[] = $s;
    } else {
        $stat_scripts[] = $s;
    }
}

$stat_custom   = array_filter($custom, function ($s) { return ($s['tier'] ?? '') !== 'marketing'; });
$market_custom = array_filter($custom, function ($s) { return ($s['tier'] ?? '') === 'marketing'; });

if (!empty($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p>Scripts saved successfully.</p></div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field('dw_consent_save_scripts'); ?>
    <input type="hidden" name="dw_consent_action" value="save_scripts">

    <div class="dw-scripts-columns">
        <!-- Statistics Column -->
        <div class="dw-scripts-column" data-tier="statistics">
            <h2>Statistics</h2>
            <p class="description">Scripts in this tier load when the user accepts statistics cookies.</p>
            <div class="dw-sortable-list" id="sortable-statistics">
                <?php
                $stat_index = 0;
                foreach ($scripts as $i => $script) :
                    if ($script['tier'] !== 'statistics') continue;
                ?>
                <div class="dw-script-card" data-script-id="<?php echo esc_attr($script['id']); ?>">
                    <div class="dw-script-card-header">
                        <span class="dw-drag-handle dashicons dashicons-menu"></span>
                        <strong><?php echo esc_html($script['name']); ?></strong>
                        <label class="dw-toggle">
                            <input type="checkbox"
                                   name="scripts[<?php echo $i; ?>][enabled]"
                                   value="1"
                                   <?php checked($script['enabled']); ?>>
                            <span class="dw-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="dw-script-card-body">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][id]" value="<?php echo esc_attr($script['id']); ?>">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][type]" value="builtin">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][name]" value="<?php echo esc_attr($script['name']); ?>">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][tier]" value="statistics" class="dw-tier-input">
                        <?php if ($script['id'] === 'matomo') : ?>
                        <p class="description">Integrates with the Connect Matomo plugin. No tracking ID needed — just enable the toggle.</p>
                        <input type="hidden" name="scripts[<?php echo $i; ?>][tracking_id]" value="">
                        <?php else : ?>
                        <label>Tracking ID:
                            <input type="text" name="scripts[<?php echo $i; ?>][tracking_id]"
                                   value="<?php echo esc_attr($script['tracking_id']); ?>"
                                   placeholder="<?php echo esc_attr(dw_get_id_placeholder($script['id'])); ?>"
                                   class="regular-text">
                        </label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Marketing Column -->
        <div class="dw-scripts-column" data-tier="marketing">
            <h2>Marketing</h2>
            <p class="description">Scripts in this tier load when the user accepts all cookies (statistics + marketing).</p>
            <div class="dw-sortable-list" id="sortable-marketing">
                <?php
                foreach ($scripts as $i => $script) :
                    if ($script['tier'] !== 'marketing') continue;
                ?>
                <div class="dw-script-card" data-script-id="<?php echo esc_attr($script['id']); ?>">
                    <div class="dw-script-card-header">
                        <span class="dw-drag-handle dashicons dashicons-menu"></span>
                        <strong><?php echo esc_html($script['name']); ?></strong>
                        <label class="dw-toggle">
                            <input type="checkbox"
                                   name="scripts[<?php echo $i; ?>][enabled]"
                                   value="1"
                                   <?php checked($script['enabled']); ?>>
                            <span class="dw-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="dw-script-card-body">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][id]" value="<?php echo esc_attr($script['id']); ?>">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][type]" value="builtin">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][name]" value="<?php echo esc_attr($script['name']); ?>">
                        <input type="hidden" name="scripts[<?php echo $i; ?>][tier]" value="marketing" class="dw-tier-input">
                        <label>Tracking ID:
                            <input type="text" name="scripts[<?php echo $i; ?>][tracking_id]"
                                   value="<?php echo esc_attr($script['tracking_id']); ?>"
                                   placeholder="<?php echo esc_attr(dw_get_id_placeholder($script['id'])); ?>"
                                   class="regular-text">
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <hr>

    <h2>Custom Scripts</h2>
    <p class="description">Add custom tracking scripts. Assign each to a consent tier.</p>

    <div id="dw-custom-scripts">
        <?php foreach ($custom as $ci => $cs) : ?>
        <div class="dw-custom-script-row">
            <input type="hidden" name="custom_scripts[<?php echo $ci; ?>][id]" value="<?php echo esc_attr($cs['id']); ?>">
            <div class="dw-custom-script-fields">
                <label>Name:
                    <input type="text" name="custom_scripts[<?php echo $ci; ?>][name]" value="<?php echo esc_attr($cs['name']); ?>" class="regular-text">
                </label>
                <label>Tier:
                    <select name="custom_scripts[<?php echo $ci; ?>][tier]">
                        <option value="statistics" <?php selected($cs['tier'], 'statistics'); ?>>Statistics</option>
                        <option value="marketing" <?php selected($cs['tier'], 'marketing'); ?>>Marketing</option>
                    </select>
                </label>
                <label>Position:
                    <select name="custom_scripts[<?php echo $ci; ?>][position]">
                        <option value="head" <?php selected($cs['position'] ?? 'head', 'head'); ?>>Head</option>
                        <option value="footer" <?php selected($cs['position'] ?? 'head', 'footer'); ?>>Footer</option>
                    </select>
                </label>
                <label class="dw-toggle-inline">
                    <input type="checkbox" name="custom_scripts[<?php echo $ci; ?>][enabled]" value="1" <?php checked($cs['enabled'] ?? false); ?>>
                    Enabled
                </label>
                <button type="button" class="button dw-remove-custom-script">Remove</button>
            </div>
            <label>Script code:
                <textarea name="custom_scripts[<?php echo $ci; ?>][code]" rows="4" class="large-text code"><?php echo esc_textarea($cs['code'] ?? ''); ?></textarea>
            </label>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="button" id="dw-add-custom-script">+ Add Custom Script</button>

    <template id="dw-custom-script-template">
        <div class="dw-custom-script-row">
            <input type="hidden" name="custom_scripts[__INDEX__][id]" value="">
            <div class="dw-custom-script-fields">
                <label>Name:
                    <input type="text" name="custom_scripts[__INDEX__][name]" value="" class="regular-text">
                </label>
                <label>Tier:
                    <select name="custom_scripts[__INDEX__][tier]">
                        <option value="statistics">Statistics</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </label>
                <label>Position:
                    <select name="custom_scripts[__INDEX__][position]">
                        <option value="head">Head</option>
                        <option value="footer">Footer</option>
                    </select>
                </label>
                <label class="dw-toggle-inline">
                    <input type="checkbox" name="custom_scripts[__INDEX__][enabled]" value="1" checked>
                    Enabled
                </label>
                <button type="button" class="button dw-remove-custom-script">Remove</button>
            </div>
            <label>Script code:
                <textarea name="custom_scripts[__INDEX__][code]" rows="4" class="large-text code"></textarea>
            </label>
        </div>
    </template>

    <?php submit_button('Save Scripts'); ?>
</form>
