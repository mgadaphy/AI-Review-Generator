<?php
/**
 * Admin Products View
 */

if (!defined('ABSPATH')) {
    exit;
}

$db_manager = ai_review_generator()->database;

$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$products = $db_manager->get_all_products_for_admin($per_page, $offset, $search);
$total_products = $db_manager->get_total_products_count($search);
$total_pages = ceil($total_products / $per_page);

?>
<div class="wrap ai-review-generator-wrap">
    <h1><?php esc_html_e('Manage Products', 'ai-review-generator'); ?></h1>

    <p><?php esc_html_e('Here you can include or exclude products from the automatic review generation schedule.', 'ai-review-generator'); ?></p>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input"><?php esc_html_e('Search Products:', 'ai-review-generator'); ?></label>
            <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>">
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search Products', 'ai-review-generator'); ?>">
        </p>
    </form>

    <form method="post">
        <?php wp_nonce_field('product_bulk_actions', 'product_nonce'); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-primary"><?php esc_html_e('Product', 'ai-review-generator'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'ai-review-generator'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Reviews Generated', 'ai-review-generator'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Last Review Date', 'ai-review-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)) : ?>
                    <?php foreach ($products as $product) : ?>
                        <tr>
                            <td class="column-primary">
                                <strong><a href="<?php echo get_edit_post_link($product->ID); ?>"><?php echo esc_html($product->post_title); ?></a></strong>
                                <div class="row-actions">
                                    <?php
                                    $is_excluded = (bool) $product->is_excluded;
                                    $action_url = wp_nonce_url(admin_url('admin.php?page=ai-review-generator-products&action=toggle_exclusion&product_id=' . $product->ID), 'toggle_exclusion_' . $product->ID);
                                    if ($is_excluded) {
                                        echo '<span class="edit"><a href="' . esc_url($action_url) . '">' . esc_html__('Include', 'ai-review-generator') . '</a></span>';
                                    } else {
                                        echo '<span class="trash"><a href="' . esc_url($action_url) . '" class="submitdelete">' . esc_html__('Exclude', 'ai-review-generator') . '</a></span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td><?php echo (bool)$product->is_excluded ? '<span class="dashicons dashicons-no-alt"></span> Excluded' : '<span class="dashicons dashicons-yes"></span> Included'; ?></td>
                            <td><?php echo (int) $product->total_reviews_generated; ?></td>
                            <td><?php echo $product->last_review_date ? esc_html(get_date_from_gmt($product->last_review_date, 'Y/m/d')) : esc_html__('N/A', 'ai-review-generator'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No products found.', 'ai-review-generator'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(esc_html__('%s items', 'ai-review-generator'), number_format_i18n($total_products)); ?></span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

</div>
