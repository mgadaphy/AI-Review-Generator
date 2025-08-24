<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI Manager Class
 * Acts as a factory and handler for various AI model APIs, builds prompts, 
 * and manages the review generation process.
 */
class AI_Review_Generator_AI_Manager {

    private $settings;

    public function __construct() {
        $this->settings = ai_review_generator()->settings;
    }

    /**
     * Generate a review for a given product.
     *
     * @param WC_Product $product The product to review.
     * @param array $params Generation parameters (rating, style, etc.).
     * @return string|WP_Error The generated review text or an error.
     */
    public function generate_review_content($product, $params) {
        $model_config = $this->settings->get_ai_model_config();
        if (!$model_config) {
            return new WP_Error('ai_error', 'AI model is not configured.');
        }

        $api_key = $this->settings->get_current_api_key();
        if (empty($api_key)) {
            return new WP_Error('ai_error', 'API key is missing for the selected model.');
        }

        $api_handler = $this->get_api_handler($model_config['provider']);
        if (is_wp_error($api_handler)) {
            return $api_handler;
        }

        $prompt = $this->build_prompt($product, $params);
        
        $start_time = microtime(true);

        $response = $api_handler->generate_review($prompt, $model_config, $api_key);

        $end_time = microtime(true);
        $generation_time = $end_time - $start_time;

        // Log API usage
        ai_review_generator()->database->log_api_usage([
            'ai_model' => $model_config['model_id'],
            'api_endpoint' => $model_config['endpoint'],
            'status' => is_wp_error($response) ? 'error' : 'success',
            'error_message' => is_wp_error($response) ? $response->get_error_message() : null,
            'response_time' => $generation_time,
        ]);

        return $response;
    }

    /**
     * Get the appropriate API handler for the selected AI provider.
     *
     * @param string $provider The AI provider (e.g., 'OpenRouter', 'OpenAI').
     * @return object|WP_Error The API handler instance or an error.
     */
    private function get_api_handler($provider) {
        switch ($provider) {
            case 'OpenRouter':
                return new AI_Review_Generator_OpenRouter_API();
            case 'OpenAI':
                return new AI_Review_Generator_OpenAI_API();
            case 'Anthropic':
                return new AI_Review_Generator_Claude_API();
            default:
                return new WP_Error('api_error', 'Unsupported AI provider specified.');
        }
    }

    /**
     * Build the prompt to send to the AI model.
     *
     * @param WC_Product $product The product to review.
     * @param array $params Generation parameters.
     * @return string The fully constructed prompt.
     */
    private function build_prompt($product, $params) {
        $style_config = $this->settings->get_review_style_config($params['style']);
        $style_modifier = $style_config['prompt_modifier'] ?? 'Write a balanced review.';

        $prompt = "You are a customer who purchased the product '{$product->get_name()}'. "
                . "Write a product review with a rating of {$params['rating']} out of 5 stars. "
                . "The review should be between {$this->settings->get('review_length_min')} and {$this->settings->get('review_length_max')} words. "
                . "{$style_modifier}. Do not include the star rating in the text. Just provide the review content.";

        $product_description = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
        if (!empty($product_description)) {
            $prompt .= "\n\nHere is some information about the product to help you:\n" . strip_tags($product_description);
        }

        return $prompt;
    }
}
