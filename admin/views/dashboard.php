<?php
/**
 * Admin Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get database instance
$db_manager = ai_review_generator()->database;

// Get stats
$stats = $db_manager->get_dashboard_stats();
$queue_items = $db_manager->get_queue_reviews(5);

?>

<div class="wrap ai-review-generator-wrap">
    <h1><?php echo esc_html(__('AI Review Generator', 'ai-review-generator')); ?></h1>

    <?php settings_errors(); ?>

    <div class="postbox-container" id="poststuff">
        <div class="meta-box-sortables ui-sortable">
            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Quick Actions', 'ai-review-generator'); ?></span></h2>
                <div class="inside">
                    <div class="main">
                        <p>
                            <a href="#" id="sync-products" class="button button-primary"><?php esc_html_e('Sync Products', 'ai-review-generator'); ?></a>
                            <a href="#" id="generate-preview" class="button"><?php esc_html_e('Generate Preview', 'ai-review-generator'); ?></a>
                        </p>
                        <p class="description"><?php esc_html_e('Sync your WooCommerce products to make them available for review generation.', 'ai-review-generator'); ?></p>
                    </div>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Statistics', 'ai-review-generator'); ?></span></h2>
                <div class="inside">
                    <div class="main">
                        <ul class="ai-stats-list">
                            <li>
                                <span class="stat-value"><?php echo esc_html($stats['products_in_rotation']); ?></span>
                                <span class="stat-label"><?php esc_html_e('Products in Rotation', 'ai-review-generator'); ?></span>
                            </li>
                            <li>
                                <span class="stat-value"><?php echo esc_html($stats['pending_reviews']); ?></span>
                                <span class="stat-label"><?php esc_html_e('Pending Reviews', 'ai-review-generator'); ?></span>
                            </li>
                            <li>
                                <span class="stat-value"><?php echo esc_html($stats['reviews_generated']); ?></span>
                                <span class="stat-label"><?php esc_html_e('Total Reviews Generated', 'ai-review-generator'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span><?php esc_html_e('Upcoming Reviews Queue', 'ai-review-generator'); ?></span></h2>
                <div class="inside">
                    <form method="post">
                        <?php wp_nonce_field('queue_bulk_actions', 'queue_nonce'); ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column column-primary"><?php esc_html_e('Product', 'ai-review-generator'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Scheduled Date', 'ai-review-generator'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Rating', 'ai-review-generator'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($queue_items)) : ?>
                                    <?php foreach ($queue_items as $item) : ?>
                                        <?php $product = wc_get_product($item->product_id); ?>
                                        <tr>
                                            <td class="column-primary">
                                                <?php if ($product) : ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>">
                                                        <?php echo esc_html($product->get_name()); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <?php esc_html_e('Product not found', 'ai-review-generator'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html(get_date_from_gmt($item->scheduled_date, 'Y/m/d g:i a')); ?></td>
                                            <td><?php echo esc_html($item->rating); ?> &#9733;</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="3"><?php esc_html_e('The review queue is empty.', 'ai-review-generator'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-review-generator-history')); ?>" class="button"><?php esc_html_e('View Full Queue & History', 'ai-review-generator'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
