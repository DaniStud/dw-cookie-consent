<?php
defined('ABSPATH') || exit;

$settings  = DW_Consent_Settings::get_all();
$texts     = $settings['banner_texts'];
$default   = $settings['default_language'];
$languages = array_keys($texts);

if (!empty($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p>Banner text saved successfully.</p></div>
<?php endif; ?>

<form method="post" action="">
    <?php wp_nonce_field('dw_consent_save_banner'); ?>
    <input type="hidden" name="dw_consent_action" value="save_banner">

    <table class="form-table">
        <tr>
            <th scope="row"><label for="default_language">Default Language</label></th>
            <td>
                <select name="default_language" id="default_language">
                    <?php foreach ($languages as $lang) : ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($default, $lang); ?>>
                            <?php echo esc_html(strtoupper($lang)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <div class="dw-language-tabs">
        <div class="dw-language-tab-nav">
            <?php foreach ($languages as $i => $lang) : ?>
                <button type="button" class="button dw-lang-tab <?php echo $i === 0 ? 'dw-lang-tab-active' : ''; ?>"
                        data-lang="<?php echo esc_attr($lang); ?>">
                    <?php echo esc_html(strtoupper($lang)); ?>
                </button>
            <?php endforeach; ?>
            <button type="button" class="button" id="dw-add-language">+ Add Language</button>
        </div>

        <?php foreach ($languages as $i => $lang) :
            $t = $texts[$lang];
        ?>
        <div class="dw-lang-panel <?php echo $i === 0 ? 'dw-lang-panel-active' : ''; ?>"
             data-lang="<?php echo esc_attr($lang); ?>">

            <div class="dw-lang-panel-header">
                <h3><?php echo esc_html(strtoupper($lang)); ?> — Banner Text</h3>
                <?php if ($lang !== $default) : ?>
                    <button type="button" class="button button-link-delete dw-remove-language"
                            data-lang="<?php echo esc_attr($lang); ?>">Remove Language</button>
                <?php endif; ?>
            </div>

            <table class="form-table">
                <tr>
                    <th><label>Title</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][title]"
                               value="<?php echo esc_attr($t['title'] ?? ''); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Description</label></th>
                    <td><textarea name="banner_texts[<?php echo esc_attr($lang); ?>][description]"
                                  rows="3" class="large-text"><?php echo esc_textarea($t['description'] ?? ''); ?></textarea>
                        <p class="description">Allowed HTML: &lt;a&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Change Notice</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][change_notice]"
                               value="<?php echo esc_attr($t['change_notice'] ?? ''); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Cookie Policy URL</label></th>
                    <td><input type="url" name="banner_texts[<?php echo esc_attr($lang); ?>][cookie_policy_url]"
                               value="<?php echo esc_attr($t['cookie_policy_url'] ?? ''); ?>" class="regular-text"
                               placeholder="https://example.com/cookie-policy/"></td>
                </tr>
                <tr>
                    <th><label>Policy Link Text</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][policy_link_text]"
                               value="<?php echo esc_attr($t['policy_link_text'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Details Toggle Text</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][details_toggle]"
                               value="<?php echo esc_attr($t['details_toggle'] ?? ''); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h4>Button Labels &amp; Descriptions</h4>
            <table class="form-table">
                <tr>
                    <th><label>Reject Button</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][reject_btn]"
                               value="<?php echo esc_attr($t['reject_btn'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Reject Description</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][details_reject]"
                               value="<?php echo esc_attr($t['details_reject'] ?? ''); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Statistics Button</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][statistics_btn]"
                               value="<?php echo esc_attr($t['statistics_btn'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Statistics Description</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][details_statistics]"
                               value="<?php echo esc_attr($t['details_statistics'] ?? ''); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label>Accept Button</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][accept_btn]"
                               value="<?php echo esc_attr($t['accept_btn'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Accept Description</label></th>
                    <td><input type="text" name="banner_texts[<?php echo esc_attr($lang); ?>][details_accept]"
                               value="<?php echo esc_attr($t['details_accept'] ?? ''); ?>" class="large-text"></td>
                </tr>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <template id="dw-language-template">
        <div class="dw-lang-panel" data-lang="__LANG__">
            <div class="dw-lang-panel-header">
                <h3>__LANG_UPPER__ — Banner Text</h3>
                <button type="button" class="button button-link-delete dw-remove-language" data-lang="__LANG__">Remove Language</button>
            </div>
            <table class="form-table">
                <tr><th><label>Title</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][title]" value="" class="large-text"></td></tr>
                <tr><th><label>Description</label></th>
                    <td><textarea name="banner_texts[__LANG__][description]" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Change Notice</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][change_notice]" value="" class="large-text"></td></tr>
                <tr><th><label>Cookie Policy URL</label></th>
                    <td><input type="url" name="banner_texts[__LANG__][cookie_policy_url]" value="" class="regular-text"></td></tr>
                <tr><th><label>Policy Link Text</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][policy_link_text]" value="" class="regular-text"></td></tr>
                <tr><th><label>Details Toggle Text</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][details_toggle]" value="" class="regular-text"></td></tr>
            </table>
            <h4>Button Labels &amp; Descriptions</h4>
            <table class="form-table">
                <tr><th><label>Reject Button</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][reject_btn]" value="" class="regular-text"></td></tr>
                <tr><th><label>Reject Description</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][details_reject]" value="" class="large-text"></td></tr>
                <tr><th><label>Statistics Button</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][statistics_btn]" value="" class="regular-text"></td></tr>
                <tr><th><label>Statistics Description</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][details_statistics]" value="" class="large-text"></td></tr>
                <tr><th><label>Accept Button</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][accept_btn]" value="" class="regular-text"></td></tr>
                <tr><th><label>Accept Description</label></th>
                    <td><input type="text" name="banner_texts[__LANG__][details_accept]" value="" class="large-text"></td></tr>
            </table>
        </div>
    </template>

    <?php submit_button('Save Banner Text'); ?>
</form>
