<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI_Review_Generator_Core class.
 *
 * Main class for the plugin, responsible for initializing and running the plugin components.
 */
class AI_Review_Generator_Core {

    /**
     * The single instance of the class.
     *
     * @var AI_Review_Generator_Core
     */
    protected static $instance = null;

    /**
     * Main AI_Review_Generator_Core Instance.
     *
     * Ensures only one instance of AI_Review_Generator_Core is loaded or can be loaded.
     *
     * @static
     * @return AI_Review_Generator_Core - Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * AI_Review_Generator_Core Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        // Hook into product save action to add product to our rotation table
        add_action('save_post_product', array($this, 'product_saved'), 10, 3);
    }

    /**
     * Handle product save/update.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated or not.
     */
    public function product_saved($post_id, $post, $update) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_product', $post_id)) {
            return;
        }

        // Check if product is publishable
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status !== 'publish') {
            return;
        }
        
        $product_manager = ai_review_generator()->product_manager;
        $product_manager->add_product_to_rotation($post_id, $post, $update);
    }
}
