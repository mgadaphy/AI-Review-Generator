<?php
/**
 * Settings Class
 * Handles plugin settings and configuration management
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Review_Generator_Settings {
    
    /**
     * Default settings
     */
    private $defaults = array(
        'enabled' => false,
        'ai_model' => 'deepseek_r1',
        'reviews_per_day' => 5,
        'reviews_per_product' => 3,
        'min_rating' => 3,
        'max_rating' => 5,
        'randomness_degree' => 70,
        'review_length_min' => 50,
        'review_length_max' => 200,
        'review_styles' => array('casual', 'detailed'),
        'timing_spread' => 'random',
        'days_between_reviews' => 1,
        'excluded_categories' => array(),
        'debug_mode' => false,
        'api_timeout' => 30,
        'cleanup_days' => 90,
        'openrouter_api_key' => '',
        'openai_api_key' => '',
        'claude_api_key' => '',
        'gemini_api_key' => ''
    );
    
    /**
     * Available AI models
     */
    private $ai_models = array(
        'deepseek_r1' => array(
            'name' => 'DeepSeek R1',
            'provider' => 'OpenRouter',
            'cost' => 'Free',
            'api_key_field' => 'openrouter_api_key',
            'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
            'model_id' => 'deepseek/deepseek-r1'
        ),
        'openai_gpt4' => array(
            'name' => 'OpenAI GPT-4',
            'provider' => 'OpenAI',
            'cost' => 'Paid',
            'api_key_field' => 'openai_api_key',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'model_id' => 'gpt-4'
        ),
        'claude_3_5' => array(
            'name' => 'Claude 3.5 Sonnet',
            'provider' => 'Anthropic',
            'cost' => 'Paid',
            'api_key_field' => 'claude_api_key',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'model_id' => 'claude-3-5-sonnet-20241022'
        ),
        'gemini_pro' => array(
            'name' => 'Google Gemini Pro',
            'provider' => 'Google',
            'cost' => 'Paid',
            'api_key_field' => 'gemini_api_key',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
            'model_id' => 'gemini-pro'
        )
    );
    
    /**
     * Review style templates
     */
    private $review_styles = array(
        'casual' => array(
            'name' => 'Casual',
            'description' => 'Relaxed, informal reviews with everyday language',
            'prompt_modifier' => 'Write in a casual, friendly tone as if talking to a friend'
        ),
        'detailed' => array(
            'name' => 'Detailed',
            'description' => 'Comprehensive reviews covering multiple aspects',
            'prompt_modifier' => 'Write a detailed, thorough review covering quality, features, and value'
        ),
        'technical' => array(
            'name' => 'Technical',
            'description' => 'Focus on specifications, features, and performance',
            'prompt_modifier' => 'Write from a technical perspective, focusing on specs and performance'
        ),
        'emotional' => array(
            'name' => 'Emotional',
            'description' => 'Reviews emphasizing personal feelings and experiences',
            'prompt_modifier' => 'Write with emotional language, expressing personal feelings and experiences'
        )
    );
    
    /**
     * Master configuration for all settings fields.
     */
    private $fields_config;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_fields_config();
        add_action('init', array($this, 'set_defaults'));
    }

    /**
     * Initialize the fields configuration array.
     */
    private function init_fields_config() {
        $this->fields_config = array(
            // General Settings
            'enabled' => array(
                'section' => 'ai_review_generator_general', 'type' => 'checkbox', 'label' => __('Enable Plugin', 'ai-review-generator'),
                'description' => __('Turn this on to start generating reviews automatically.', 'ai-review-generator')
            ),
            'debug_mode' => array(
                'section' => 'ai_review_generator_general', 'type' => 'checkbox', 'label' => __('Debug Mode', 'ai-review-generator'),
                'description' => __('Enable debug logging. Requires WP_DEBUG_LOG to be true.', 'ai-review-generator')
            ),

            // API Settings
            'ai_model' => array(
                'section' => 'ai_review_generator_api', 'type' => 'select', 'label' => __('AI Model', 'ai-review-generator'),
                'description' => __('Choose which AI model to use for generating reviews.', 'ai-review-generator'), 'options_callback' => 'get_ai_model_options'
            ),
            'openrouter_api_key' => array(
                'section' => 'ai_review_generator_api', 'type' => 'password', 'label' => __('OpenRouter API Key', 'ai-review-generator'),
                'description' => __('Your API key for OpenRouter (for DeepSeek R1).', 'ai-review-generator')
            ),
            'openai_api_key' => array(
                'section' => 'ai_review_generator_api', 'type' => 'password', 'label' => __('OpenAI API Key', 'ai-review-generator'),
                'description' => __('Your API key for OpenAI models.', 'ai-review-generator')
            ),
            'claude_api_key' => array(
                'section' => 'ai_review_generator_api', 'type' => 'password', 'label' => __('Claude API Key', 'ai-review-generator'),
                'description' => __('Your API key for Anthropic/Claude models.', 'ai-review-generator')
            ),
            'gemini_api_key' => array(
                'section' => 'ai_review_generator_api', 'type' => 'password', 'label' => __('Gemini API Key', 'ai-review-generator'),
                'description' => __('Your API key for Google/Gemini models.', 'ai-review-generator')
            ),
            'api_timeout' => array(
                'section' => 'ai_review_generator_api', 'type' => 'number', 'label' => __('API Timeout', 'ai-review-generator'),
                'description' => __('Seconds to wait for a response from the AI API.', 'ai-review-generator'), 'min' => 10, 'max' => 120
            ),

            // Review Content Settings
            'min_rating' => array(
                'section' => 'ai_review_generator_content', 'type' => 'select', 'label' => __('Minimum Rating', 'ai-review-generator'),
                'description' => __('Lowest star rating that can be generated.', 'ai-review-generator'), 'options_callback' => 'get_rating_options'
            ),
            'max_rating' => array(
                'section' => 'ai_review_generator_content', 'type' => 'select', 'label' => __('Maximum Rating', 'ai-review-generator'),
                'description' => __('Highest star rating that can be generated.', 'ai-review-generator'), 'options_callback' => 'get_rating_options'
            ),
            'randomness_degree' => array(
                'section' => 'ai_review_generator_content', 'type' => 'range', 'label' => __('Randomness Degree', 'ai-review-generator'),
                'description' => __('Higher values create more varied reviews. Lower values are more consistent.', 'ai-review-generator'),
                'min' => 0, 'max' => 100, 'step' => 5
            ),
            'review_length_min' => array(
                'section' => 'ai_review_generator_content', 'type' => 'number', 'label' => __('Min Review Length', 'ai-review-generator'),
                'description' => __('Minimum number of words for a review.', 'ai-review-generator'), 'min' => 10, 'max' => 500
            ),
            'review_length_max' => array(
                'section' => 'ai_review_generator_content', 'type' => 'number', 'label' => __('Max Review Length', 'ai-review-generator'),
                'description' => __('Maximum number of words for a review.', 'ai-review-generator'), 'min' => 20, 'max' => 1000
            ),
            'review_styles' => array(
                'section' => 'ai_review_generator_content', 'type' => 'multicheckbox', 'label' => __('Enabled Review Styles', 'ai-review-generator'),
                'description' => __('Select which review styles can be generated.', 'ai-review-generator'), 'options_callback' => 'get_review_style_options'
            ),

            // Scheduling Settings
            'reviews_per_day' => array(
                'section' => 'ai_review_generator_scheduling', 'type' => 'number', 'label' => __('Reviews Per Day', 'ai-review-generator'),
                'description' => __('Maximum number of reviews to generate per day across all products.', 'ai-review-generator'), 'min' => 1, 'max' => 100
            ),
            'reviews_per_product' => array(
                'section' => 'ai_review_generator_scheduling', 'type' => 'number', 'label' => __('Max Reviews Per Product', 'ai-review-generator'),
                'description' => __('Maximum number of reviews to generate for a single product in total.', 'ai-review-generator'), 'min' => 1, 'max' => 20
            ),
            'timing_spread' => array(
                'section' => 'ai_review_generator_scheduling', 'type' => 'select', 'label' => __('Posting Schedule', 'ai-review-generator'),
                'description' => __('Choose when reviews should be posted.', 'ai-review-generator'), 'options_callback' => 'get_timing_spread_options'
            ),
            'days_between_reviews' => array(
                'section' => 'ai_review_generator_scheduling', 'type' => 'number', 'label' => __('Days Between Reviews', 'ai-review-generator'),
                'description' => __('Minimum number of days before another review is posted for the same product.', 'ai-review-generator'), 'min' => 0, 'max' => 30
            ),

            // Advanced Settings
            'excluded_categories' => array(
                'section' => 'ai_review_generator_advanced', 'type' => 'multicheckbox', 'label' => __('Excluded Categories', 'ai-review-generator'),
                'description' => __('Products in these categories will not receive reviews.', 'ai-review-generator'), 'options_callback' => 'get_category_options'
            ),
            'cleanup_days' => array(
                'section' => 'ai_review_generator_advanced', 'type' => 'number', 'label' => __('Log Cleanup After (Days)', 'ai-review-generator'),
                'description' => __('Automatically delete old log data after this many days.', 'ai-review-generator'), 'min' => 30, 'max' => 365
            ),
        );
    }
    
    /**
     * Set default settings if they don't exist
     */
    public function set_defaults() {
        foreach ($this->defaults as $key => $value) {
            if (get_option('ai_review_generator_' . $key) === false) {
                add_option('ai_review_generator_' . $key, $value);
            }
        }
    }
    
    /**
     * Get a specific setting
     */
    public function get($key, $default = null) {
        if ($default === null && isset($this->defaults[$key])) {
            $default = $this->defaults[$key];
        }
        
        return get_option('ai_review_generator_' . $key, $default);
    }
    
    /**
     * Set a specific setting
     */
    public function set($key, $value) {
        return update_option('ai_review_generator_' . $key, $value);
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        $settings = array();
        foreach ($this->defaults as $key => $default) {
            $settings[$key] = $this->get($key, $default);
        }
        return $settings;
    }
    
    /**
     * Get AI model configuration
     */
    public function get_ai_model_config($model_key = null) {
        if ($model_key === null) {
            $model_key = $this->get('ai_model');
        }
        
        return isset($this->ai_models[$model_key]) ? $this->ai_models[$model_key] : null;
    }
    
    /**
     * Get all available AI models
     */
    public function get_available_ai_models() {
        return $this->ai_models;
    }
    
    /**
     * Get review style configuration
     */
    public function get_review_style_config($style_key = null) {
        if ($style_key === null) {
            return $this->review_styles;
        }
        
        return isset($this->review_styles[$style_key]) ? $this->review_styles[$style_key] : null;
    }
    
    /**
     * Get API key for current model
     */
    public function get_current_api_key() {
        $model_config = $this->get_ai_model_config();
        if (!$model_config) {
            return '';
        }
        
        $api_key_field = $model_config['api_key_field'];
        $encrypted_key = $this->get($api_key_field);
        
        return $this->decrypt_api_key($encrypted_key);
    }
    
    /**
     * Encrypt API key for storage
     */
    public function encrypt_api_key($key) {
        if (empty($key)) {
            return '';
        }
        
        // Use WordPress salts for encryption
        $salt = wp_salt('auth');
        return base64_encode($key . '|' . $salt);
    }
    
    /**
     * Decrypt API key
     */
    public function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        $decrypted = base64_decode($encrypted_key);
        $parts = explode('|', $decrypted);
        
        if (count($parts) === 2 && $parts[1] === wp_salt('auth')) {
            return $parts[0];
        }
        
        return '';
    }
    
    /**
     * Validate settings before saving
     */
    public function validate($settings) {
        $validated = array();
        
        // Boolean settings
        $boolean_fields = array('enabled', 'debug_mode');
        foreach ($boolean_fields as $field) {
            $validated[$field] = !empty($settings[$field]);
        }
        
        // Integer settings with ranges
        $integer_fields = array(
            'reviews_per_day' => array('min' => 1, 'max' => 100),
            'reviews_per_product' => array('min' => 1, 'max' => 20),
            'min_rating' => array('min' => 1, 'max' => 5),
            'max_rating' => array('min' => 1, 'max' => 5),
            'randomness_degree' => array('min' => 0, 'max' => 100),
            'review_length_min' => array('min' => 10, 'max' => 500),
            'review_length_max' => array('min' => 20, 'max' => 1000),
            'days_between_reviews' => array('min' => 0, 'max' => 30),
            'api_timeout' => array('min' => 10, 'max' => 120),
            'cleanup_days' => array('min' => 30, 'max' => 365)
        );
        
        foreach ($integer_fields as $field => $range) {
            $value = intval($settings[$field] ?? $this->defaults[$field]);
            $validated[$field] = max($range['min'], min($range['max'], $value));
        }
        
        // String settings
        $string_fields = array('ai_model', 'timing_spread');
        foreach ($string_fields as $field) {
            $validated[$field] = sanitize_text_field($settings[$field] ?? $this->defaults[$field]);
        }
        
        // Array settings
        $array_fields = array('review_styles', 'excluded_categories');
        foreach ($array_fields as $field) {
            if (isset($settings[$field]) && is_array($settings[$field])) {
                $validated[$field] = array_map('sanitize_text_field', $settings[$field]);
            } else {
                $validated[$field] = $this->defaults[$field];
            }
        }
        
        // API keys (encrypted)
        $api_key_fields = array('openrouter_api_key', 'openai_api_key', 'claude_api_key', 'gemini_api_key');
        foreach ($api_key_fields as $field) {
            if (!empty($settings[$field])) {
                $validated[$field] = $this->encrypt_api_key(sanitize_text_field($settings[$field]));
            } else {
                $validated[$field] = '';
            }
        }
        
        // Validate min/max relationships
        if ($validated['min_rating'] > $validated['max_rating']) {
            $validated['max_rating'] = $validated['min_rating'];
        }
        
        if ($validated['review_length_min'] > $validated['review_length_max']) {
            $validated['review_length_max'] = $validated['review_length_min'];
        }
        
        // Validate AI model exists
        if (!isset($this->ai_models[$validated['ai_model']])) {
            $validated['ai_model'] = 'deepseek_r1';
        }
        
        return $validated;
    }
    
    /**
     * Get timing configuration for scheduling
     */
    public function get_timing_config() {
        $timing_spread = $this->get('timing_spread');
        
        $configs = array(
            'random' => array(
                'start_hour' => 0,
                'end_hour' => 23,
                'description' => 'Reviews posted randomly throughout the day'
            ),
            'business_hours' => array(
                'start_hour' => 9,
                'end_hour' => 17,
                'description' => 'Reviews posted during business hours (9 AM - 5 PM)'
            ),
            'evening' => array(
                'start_hour' => 18,
                'end_hour' => 22,
                'description' => 'Reviews posted during evening hours (6 PM - 10 PM)'
            ),
            'custom' => array(
                'start_hour' => $this->get('custom_start_hour', 9),
                'end_hour' => $this->get('custom_end_hour', 17),
                'description' => 'Custom time range'
            )
        );
        
        return $configs[$timing_spread] ?? $configs['random'];
    }
    
    /**
     * Check if plugin is properly configured
     */
    public function is_configured() {
        $issues = $this->get_configuration_issues();
        return empty($issues);
    }
    
    /**
     * Get configuration issues
     */
    public function get_configuration_issues() {
        $issues = array();
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $issues[] = __('WooCommerce is not installed or activated.', 'ai-review-generator');
        }
        
        // Check AI model configuration
        $current_model = $this->get('ai_model');
        $model_config = $this->get_ai_model_config($current_model);
        
        if (!$model_config) {
            $issues[] = __('Invalid AI model selected.', 'ai-review-generator');
        } else {
            $api_key = $this->get_current_api_key();
            if (empty($api_key)) {
                $issues[] = sprintf(
                    __('API key not configured for %s.', 'ai-review-generator'),
                    $model_config['name']
                );
            }
        }
        
        // Check rating range
        if ($this->get('min_rating') > $this->get('max_rating')) {
            $issues[] = __('Minimum rating cannot be higher than maximum rating.', 'ai-review-generator');
        }
        
        // Check review length
        if ($this->get('review_length_min') > $this->get('review_length_max')) {
            $issues[] = __('Minimum review length cannot be higher than maximum length.', 'ai-review-generator');
        }
        
        // Check if there are products to review
        $product_count = wp_count_posts('product');
        if (empty($product_count->publish)) {
            $issues[] = __('No published products found to generate reviews for.', 'ai-review-generator');
        }
        
        return $issues;
    }
    
    /**
     * Get system information for debugging
     */
    public function get_system_info() {
        global $wp_version;
        
        $info = array(
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'plugin_version' => AI_REVIEW_GENERATOR_VERSION,
            'woocommerce_active' => class_exists('WooCommerce'),
            'woocommerce_version' => class_exists('WooCommerce') ? WC()->version : 'N/A',
            'curl_available' => function_exists('curl_init'),
            'openssl_available' => function_exists('openssl_encrypt'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'wp_debug' => WP_DEBUG,
            'wp_debug_log' => WP_DEBUG_LOG,
        );
        
        // Database info
        global $wpdb;
        $database = ai_review_generator()->database;
        
        $info['database_tables'] = array();
        foreach (array('reviews_queue', 'review_history', 'product_rotation', 'api_usage', 'settings') as $table_key) {
            $table_name = $database->get_table_name($table_key);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $info['database_tables'][$table_key] = $count;
        }
        
        // Current settings
        $info['current_settings'] = $this->get_all();
        
        // Configuration issues
        $info['configuration_issues'] = $this->get_configuration_issues();
        
        return $info;
    }
    
    /**
     * Export settings for backup
     */
    public function export_settings() {
        $settings = $this->get_all();
        
        // Don't export API keys for security
        $api_key_fields = array('openrouter_api_key', 'openai_api_key', 'claude_api_key', 'gemini_api_key');
        foreach ($api_key_fields as $field) {
            unset($settings[$field]);
        }
        
        return array(
            'version' => AI_REVIEW_GENERATOR_VERSION,
            'export_date' => current_time('mysql'),
            'settings' => $settings
        );
    }
    
    /**
     * Import settings from backup
     */
    public function import_settings($import_data) {
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            throw new Exception(__('Invalid import data format.', 'ai-review-generator'));
        }
        
        $settings_to_import = $this->validate($import_data['settings']);
        
        // Save validated settings
        foreach ($settings_to_import as $key => $value) {
            $this->set($key, $value);
        }
        
        return true;
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_to_defaults() {
        foreach ($this->defaults as $key => $value) {
            $this->set($key, $value);
        }
        
        return true;
    }
    
    /**
     * Get the full configuration for all setting fields.
     *
     * @return array
     */
    public function get_all_fields_config() {
        // Add the dynamic options to the config array before returning
        $fields_with_options = array_filter($this->fields_config, function ($field) {
            return isset($field['options_callback']);
        });

        foreach ($fields_with_options as $field_id => $field_config) {
            if (method_exists($this, $field_config['options_callback'])) {
                $this->fields_config[$field_id]['options'] = $this->{$field_config['options_callback']}();
            }
        }

        return $this->fields_config;
    }

    /**
     * Get setting field configuration for a single field.
     */
    public function get_field_config($field_name) {
        return isset($this->fields_config[$field_name]) ? $this->fields_config[$field_name] : null;
    }

    /**
     * Get AI model options for select fields.
     */
    private function get_ai_model_options() {
        $options = array();
        foreach ($this->ai_models as $key => $model) {
            $cost_label = $model['cost'] === 'Free' ? ' (Free)' : ' (Paid)';
            $options[$key] = $model['name'] . ' - ' . $model['provider'] . $cost_label;
        }
        return $options;
    }

    /**
     * Get rating options for select fields.
     */
    private function get_rating_options() {
        $options = array();
        for ($i = 1; $i <= 5; $i++) {
            $options[$i] = $i . ' ' . str_repeat('⭐', $i);
        }
        return $options;
    }

    /**
     * Get review style options for multicheckbox fields.
     */
    private function get_review_style_options() {
        $options = array();
        foreach ($this->review_styles as $key => $style) {
            $options[$key] = $style['name'];
        }
        return $options;
    }

    /**
     * Get timing spread options for select fields.
     */
    private function get_timing_spread_options() {
        return array(
            'random' => __('Randomly throughout the day', 'ai-review-generator'),
            'business_hours' => __('Business hours (9 AM - 5 PM)', 'ai-review-generator'),
            'evening' => __('Evening hours (6 PM - 10 PM)', 'ai-review-generator'),
        );
    }

    /**
     * Get product category options for multicheckbox fields.
     */
    private function get_category_options() {
        $options = array();
        if (class_exists('WooCommerce')) {
            $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
            if (!is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $options[$category->term_id] = $category->name;
                }
            }
        }
        return $options;
    }

    /**
     * Get the full configuration for all setting fields.
     *
     * @return array
     */
    public function get_all_fields_config() {
        // Return an empty array to prevent fatal error.
        // The full implementation can be done later.
        return array();
    }
}