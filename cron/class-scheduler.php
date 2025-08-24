<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Cron Handler Class
 * 
 * Provides the callback function for the WP-Cron event.
 */
class AI_Review_Generator_Cron_Handler {

    /**
     * The main function that runs when the cron event is triggered.
     * It instantiates the review generator and starts the process.
     */
    public static function run_generation_event() {
        // Ensure all dependencies are loaded.
        if (!did_action('plugins_loaded')) {
            return;
        }

        // Get the review generator instance from our main plugin class
        $review_generator = ai_review_generator()->review_generator;

        if ($review_generator) {
            $review_generator->generate_scheduled_reviews();
        }
    }
}
