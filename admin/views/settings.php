<?php
/**
 * Admin Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(__('Settings', 'ai-review-generator')); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('ai_review_generator_settings');
        do_settings_sections('ai-review-generator-settings');
        submit_button();
        ?>
    </form>
</div>
