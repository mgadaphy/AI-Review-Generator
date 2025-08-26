<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Anthropic (Claude) API Handler
 * 
 * Communicates with the Anthropic API to generate reviews.
 */
class AI_Review_Generator_Model_Claude_Api {

    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * Generate a review using the Claude API.
     *
     * @param string $prompt The prompt for the AI.
     * @param array $model_config Configuration for the selected model.
     * @param string $api_key The API key.
     * @return string|WP_Error The generated review text or an error object.
     */
    public function generate_review($prompt, $model_config, $api_key) {
        $headers = [
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'  => 'application/json',
        ];

        $body = [
            'model' => $model_config['model_id'],
            'max_tokens' => 1024, // A reasonable max for a review
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ];

        $args = [
            'body'    => json_encode($body),
            'headers' => $headers,
            'timeout' => 60, // 60 seconds timeout
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            return new WP_Error('api_connection_error', 'Failed to connect to Anthropic API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($response_code >= 400) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown API error.';
            return new WP_Error('api_error', "Anthropic API Error ({$response_code}): {$error_message}");
        }

        if (empty($result['content'][0]['text'])) {
            return new WP_Error('api_response_error', 'Invalid response from Anthropic API. No content found.');
        }

        return trim($result['content'][0]['text']);
    }
}
