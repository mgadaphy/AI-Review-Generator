<?php
/**
 * Product Manager Class
 * Handles all product-related functionalities, including syncing with WooCommerce
 * and managing the intelligent review rotation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Review_Generator_Product_Manager {

    public function __construct() {
        // Actions are now handled by the Core class
    }

    /**
     * Sync product data to our custom table on product update/create.
     */
    public function add_product_to_rotation($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || $post->post_type !== 'product') {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $this->sync_single_product($product);
    }

    /**
     * Sync all WooCommerce products to our custom table.
     */
    public function sync_all_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $products = get_posts($args);

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $this->sync_single_product($product);
            }
        }

        return count($products);
    }

    /**
     * Helper to sync a single product's data with our rotation table.
     */
    private function sync_single_product($product) {
        global $wpdb;
        $db_manager = ai_review_generator()->database;
        $history_table = $db_manager->get_table_name('review_history');

        // Get review stats from history table
        $review_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as review_count, MAX(created_at) as last_review FROM {$history_table} WHERE product_id = %d",
            $product->get_id()
        ));

        $category_ids = $product->get_category_ids();

        $data = array(
            'total_reviews_generated' => $review_stats->review_count ?? 0,
            'last_review_date' => $review_stats->last_review,
            'category_ids' => implode(',', $category_ids),
            'product_price' => $product->get_price(),
            'product_status' => $product->get_status(),
        );

        // Calculate priority score
        $data['priority_score'] = $this->calculate_priority_score($data);

        $db_manager->update_product_rotation($product->get_id(), $data);
    }

    /**
     * Get the next product that should receive a review.
     */
    public function get_next_product_for_review() {
        $this->recalculate_all_priority_scores();
        $eligible_products = $this->get_eligible_products(1);
        return !empty($eligible_products) ? $eligible_products[0] : null;
    }

    /**
     * Get a list of products eligible for review generation.
     */
    public function get_eligible_products($limit = 5) {
        $db_manager = ai_review_generator()->database;
        return $db_manager->get_product_rotation($limit, true);
    }

    /**
     * Recalculate priority scores for all products in the rotation.
     */
    public function recalculate_all_priority_scores() {
        $db_manager = ai_review_generator()->database;
        $all_products = $db_manager->get_product_rotation(null, false); // Get all products

        foreach ($all_products as $product_data) {
            $new_score = $this->calculate_priority_score((array) $product_data);
            $db_manager->update_product_rotation($product_data->product_id, ['priority_score' => $new_score]);
        }
    }

    /**
     * Calculate the priority score for a product.
     * Higher score means higher priority for getting a review.
     */
    /**
     * Toggle the exclusion status of a product.
     */
    public function toggle_product_exclusion($product_id, $is_excluded) {
        $db_manager = ai_review_generator()->database;
        return $db_manager->update_product_rotation($product_id, ['is_excluded' => $is_excluded ? 1 : 0]);
    }

    /**
     * Calculate the priority score for a product.
     * Higher score means higher priority for getting a review.
     */
    private function calculate_priority_score($product_data) {
        $score = 50; // Base score

        // Factor 1: Time since last review (more recent = lower score)
        if (!empty($product_data['last_review_date'])) {
            $days_since_last_review = (time() - strtotime($product_data['last_review_date'])) / DAY_IN_SECONDS;
            $score += min(30, $days_since_last_review); // Add up to 30 points
        } else {
            $score += 30; // Max points if no reviews yet
        }

        // Factor 2: Total reviews generated (more reviews = lower score)
        $reviews_generated = (int) $product_data['total_reviews_generated'];
        $score -= ($reviews_generated * 5); // Subtract 5 points per review

        // Factor 3: Product price (higher price might be slightly more important)
        $price = (float) $product_data['product_price'];
        if ($price > 100) {
            $score += 10;
        } elseif ($price > 50) {
            $score += 5;
        }

        return max(0, $score); // Ensure score is not negative
    }
}

