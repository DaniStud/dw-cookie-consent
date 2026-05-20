<?php
defined('ABSPATH') || exit;

$per_page   = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$log_data   = DW_Consent_Log::get_log([
    'per_page' => $per_page,
    'page'     => $current_page,
]);

$items      = $log_data['items'];
$total      = $log_data['total'];
$total_pages = $log_data['pages'];

if (isset($_GET['purged'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php echo esc_html(absint($_GET['purged'])); ?> log entries purged.</p>
    </div>
<?php endif; ?>

<div class="dw-log-actions">
    <form method="post" action="" style="display: inline-block;">
        <?php wp_nonce_field('dw_consent_export_csv'); ?>
        <input type="hidden" name="dw_consent_action" value="export_csv">
        <?php submit_button('Export CSV', 'secondary', 'submit', false); ?>
    </form>

    <form method="post" action="" style="display: inline-block; margin-left: 20px;">
        <?php wp_nonce_field('dw_consent_purge_log'); ?>
        <input type="hidden" name="dw_consent_action" value="purge_log">
        <label>
            Purge entries older than
            <input type="number" name="purge_days" value="365" min="1" max="3650" class="small-text">
            days
        </label>
        <?php submit_button('Purge', 'delete', 'submit', false, ['onclick' => "return confirm('Are you sure you want to purge old consent log entries?');"]); ?>
    </form>
</div>

<p class="description">Total entries: <strong><?php echo esc_html(number_format_i18n($total)); ?></strong></p>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" style="width: 60px;">ID</th>
            <th scope="col">Consent Hash</th>
            <th scope="col">Choices</th>
            <th scope="col" style="width: 80px;">Version</th>
            <th scope="col">User Agent</th>
            <th scope="col" style="width: 160px;">Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)) : ?>
            <tr>
                <td colspan="6">No consent log entries yet.</td>
            </tr>
        <?php else : ?>
            <?php foreach ($items as $item) :
                $choices = json_decode($item->consent_choices, true);
                $choice_labels = [];
                if (!empty($choices['analytics'])) $choice_labels[] = 'Analytics';
                if (!empty($choices['marketing'])) $choice_labels[] = 'Marketing';
                if (empty($choice_labels)) $choice_labels[] = 'Rejected';
            ?>
            <tr>
                <td><?php echo esc_html($item->id); ?></td>
                <td><code title="<?php echo esc_attr($item->consent_hash); ?>"><?php echo esc_html(substr($item->consent_hash, 0, 16) . '…'); ?></code></td>
                <td>
                    <?php foreach ($choice_labels as $label) : ?>
                        <span class="dw-choice-badge dw-choice-<?php echo esc_attr(strtolower($label)); ?>">
                            <?php echo esc_html($label); ?>
                        </span>
                    <?php endforeach; ?>
                </td>
                <td><?php echo esc_html($item->consent_version); ?></td>
                <td><span class="dw-ua-text" title="<?php echo esc_attr($item->user_agent); ?>"><?php echo esc_html(wp_trim_words($item->user_agent, 10, '…')); ?></span></td>
                <td><?php echo esc_html($item->created_at); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1) : ?>
<div class="tablenav">
    <div class="tablenav-pages">
        <?php
        $page_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current_page,
        ]);
        echo wp_kses_post($page_links);
        ?>
    </div>
</div>
<?php endif; ?>
