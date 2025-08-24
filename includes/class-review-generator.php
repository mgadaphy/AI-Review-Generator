<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Review Generator Class
 * 
 * Orchestrates the entire process of selecting products, generating reviews,
 * and saving them to the database.
 */
class AI_Review_Generator_Review_Generator {

    private $settings;
    private $product_manager;
    private $ai_manager;
    private $db_manager;

    public function __construct() {
        $this->settings = ai_review_generator()->settings;
        $this->product_manager = ai_review_generator()->product_manager;
        $this->ai_manager = ai_review_generator()->ai_manager;
        $this->db_manager = ai_review_generator()->database;
    }

    /**
     * Main entry point for scheduled review generation.
     */
    public function generate_scheduled_reviews() {
        $reviews_per_day = $this->settings->get('reviews_per_day');
        if ($reviews_per_day == 0) {
            return; // Generation is disabled
        }

        // Logic to respect daily/hourly limits can be added here

        for ($i = 0; $i < $reviews_per_day; $i++) {
            $product_data = $this->product_manager->get_next_product_for_review();
            if (!$product_data) {
                // No more eligible products
                break;
            }

            $product = wc_get_product($product_data->product_id);
            if (!$product) {
                continue;
            }

            $this->process_review_generation_for_product($product);
        }
    }

    /**
     * Process the full review generation and saving cycle for a product.
     *
     * @param WC_Product $product
     * @return int|WP_Error The new comment ID or an error.
     */
    private function process_review_generation_for_product($product) {
        $params = $this->determine_review_parameters();

        // Add to queue
        $queue_id = $this->db_manager->add_to_review_queue([
            'product_id' => $product->get_id(),
            'status' => 'processing',
            'generation_params' => json_encode($params),
        ]);

        $review_content = $this->ai_manager->generate_review_content($product, $params);

        if (is_wp_error($review_content)) {
            $this->db_manager->update_review_queue($queue_id, ['status' => 'failed']);
            $this->db_manager->add_to_review_history([
                'product_id' => $product->get_id(),
                'status' => 'error',
                'error_message' => $review_content->get_error_message(),
                'rating' => $params['rating'],
                'review_style' => $params['style'],
            ]);
            return $review_content;
        }

        $review_data = [
            'review_author' => 'Verified Customer',
            'review_content' => $review_content,
            'review_rating' => $params['rating'],
        ];

        $comment_id = $this->create_wp_review($product->get_id(), $review_data);

        if (is_wp_error($comment_id)) {
            // Handle review creation error
            return $comment_id;
        }

        // Update queue and history
        $this->db_manager->update_review_queue($queue_id, ['status' => 'completed']);
        $this->db_manager->add_to_review_history([
            'product_id' => $product->get_id(),
            'review_id' => $comment_id,
            'status' => 'success',
            'rating' => $params['rating'],
            'review_style' => $params['style'],
            'review_content' => $review_content,
        ]);

        // Resync product data to update stats
        $this->product_manager->add_product_to_rotation($product->get_id(), get_post($product->get_id()), true);

        return $comment_id;
    }

    /**
     * Determine parameters for the next review based on settings.
     */
    private function determine_review_parameters() {
        $rating_probabilities = $this->settings->get('rating_probabilities');
        $style_probabilities = $this->settings->get('review_style_probabilities');

        return [
            'rating' => $this->get_weighted_random($rating_probabilities),
            'style'  => $this->get_weighted_random($style_probabilities),
        ];
    }

    /**
     * Create the review (comment) in WordPress.
     */
    private function create_wp_review($product_id, $review_data) {
        $commentdata = [
            'comment_post_ID' => $product_id,
            'comment_author' => $review_data['review_author'],
            'comment_author_email' => 'customer@example.com', // Placeholder
            'comment_content' => $review_data['review_content'],
            'comment_type' => 'review',
            'comment_parent' => 0,
            'user_id' => 0, // Guest review
            'comment_approved' => 1,
        ];

        $comment_id = wp_insert_comment($commentdata);

        if ($comment_id) {
            update_comment_meta($comment_id, 'rating', $review_data['review_rating']);
            update_comment_meta($comment_id, 'verified', 1);
        }

        return $comment_id;
    }

    /**
     * Helper function to get a weighted random value.
     */
    private function get_weighted_random($weights) {
        $total_weight = array_sum($weights);
        $random_num = mt_rand(1, $total_weight);
        $cumulative_weight = 0;

        foreach ($weights as $key => $weight) {
            $cumulative_weight += $weight;
            if ($random_num <= $cumulative_weight) {
                return $key;
            }
        }
        return array_key_last($weights); // Fallback
    }
}
