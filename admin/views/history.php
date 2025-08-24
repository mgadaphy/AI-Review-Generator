<?php
/**
 * Admin History View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(__('Review Generation History', 'ai-review-generator')); ?></h1>
    <?php
    $db_manager = ai_review_generator()->get_database_manager();

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_items = $db_manager->get_review_history_count();
    $total_pages = ceil($total_items / $per_page);

    $history_items = $db_manager->get_review_history($per_page, $offset);
    ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-primary"><?php esc_html_e('Product', 'ai-review-generator'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Rating', 'ai-review-generator'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Review', 'ai-review-generator'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('Generated On', 'ai-review-generator'); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e('AI Model', 'ai-review-generator'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($history_items)) : ?>
                <?php foreach ($history_items as $item) : ?>
                    <tr>
                        <td class="column-primary">
                            <strong><a href="<?php echo get_edit_post_link($item->product_id); ?>"><?php echo esc_html($item->post_title); ?></a></strong>
                        </td>
                        <td><?php echo esc_html($item->rating); ?>/5</td>
                        <td><?php echo esc_html(wp_trim_words($item->review_text, 20, '...')); ?></td>
                        <td><?php echo esc_html(get_date_from_gmt($item->created_at)); ?></td>
                        <td><?php echo esc_html($item->ai_model); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No review history found.', 'ai-review-generator'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(esc_html__('%s items', 'ai-review-generator'), number_format_i18n($total_items)); ?></span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
