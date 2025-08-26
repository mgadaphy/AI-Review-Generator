<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * OpenAI API Handler
 * 
 * Communicates with the OpenAI API to generate reviews.
 */
class AI_Review_Generator_Model_OpenAI_Api {

    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Generate a review using the OpenAI API.
     *
     * @param string $prompt The prompt for the AI.
     * @param array $model_config Configuration for the selected model.
     * @param string $api_key The API key.
     * @return string|WP_Error The generated review text or an error object.
     */
    public function generate_review($prompt, $model_config, $api_key) {
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ];

        $body = [
            'model' => $model_config['model_id'],
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
            return new WP_Error('api_connection_error', 'Failed to connect to OpenAI API: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($response_code >= 400) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown API error.';
            return new WP_Error('api_error', "OpenAI API Error ({$response_code}): {$error_message}");
        }

        if (empty($result['choices'][0]['message']['content'])) {
            return new WP_Error('api_response_error', 'Invalid response from OpenAI API. No content found.');
        }

        return trim($result['choices'][0]['message']['content']);
    }
}
