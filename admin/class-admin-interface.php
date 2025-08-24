<?php
/**
 * Admin Interface Class
 * Handles all admin interface functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Review_Generator_Admin_Interface {

    private $notices = [];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_bulk_actions'));
        add_action('admin_notices', array($this, 'display_notices'));
        add_action('admin_init', array($this, 'handle_product_actions'));

        add_action('wp_ajax_ai_review_generator_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_ai_review_generator_generate_preview', array($this, 'generate_preview_review'));
        add_action('wp_ajax_ai_review_generator_sync_products', array($this, 'sync_products'));

        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Reviews', 'ai-review-generator'),
            __('AI Reviews', 'ai-review-generator'),
            'manage_options',
            'ai-review-generator',
            array($this, 'display_main_page'),
            'dashicons-star-filled',
            58
        );

        add_submenu_page(
            'ai-review-generator',
            __('Dashboard', 'ai-review-generator'),
            __('Dashboard', 'ai-review-generator'),
            'manage_options',
            'ai-review-generator',
            array($this, 'display_main_page')
        );

        add_submenu_page(
            'ai-review-generator',
            __('Product Management', 'ai-review-generator'),
            __('Products', 'ai-review-generator'),
            'manage_options',
            'ai-review-generator-products',
            array($this, 'display_products_page')
        );

        add_submenu_page(
            'ai-review-generator',
            __('Review History', 'ai-review-generator'),
            __('History', 'ai-review-generator'),
            'manage_options',
            'ai-review-generator-history',
            array($this, 'display_history_page')
        );

        add_submenu_page(
            'ai-review-generator',
            __('Settings', 'ai-review-generator'),
            __('Settings', 'ai-review-generator'),
            'manage_options',
            'ai-review-generator-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Display main admin page (Dashboard)
     */
    public function display_main_page() {
        $this->handle_bulk_actions();
        require_once plugin_dir_path(__FILE__) . 'views/dashboard.php';
    }

    /**
     * Display products page
     */
    public function display_products_page() {
        require_once plugin_dir_path(__FILE__) . 'views/products.php';
    }

    /**
     * Display history page
     */
    public function display_history_page() {
        $this->handle_bulk_actions();
        require_once plugin_dir_path(__FILE__) . 'views/history.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'views/settings.php';
    }

    /**
     * Register settings, sections, and fields
     */
    public function api_section_callback() {
        echo '<p>' . esc_html__('Configure the API settings for the AI models you want to use.', 'ai-review-generator') . '</p>';
    }

    public function handle_settings_save($input) {
        if (empty($_POST['option_page']) || $_POST['option_page'] !== 'ai-review-generator-settings') {
            return [];
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ai_review_generator_settings-options')) {
            return [];
        }

        $settings_class = ai_review_generator()->get_settings();
        $all_defaults = $settings_class->get_all();
        $submitted_settings = [];

        foreach (array_keys($all_defaults) as $key) {
            if (isset($_POST[$key])) {
                $submitted_settings[$key] = wp_unslash($_POST[$key]);
            } else {
                if (is_bool($all_defaults[$key])) {
                    $submitted_settings[$key] = false;
                }
            }
        }

        $validated_settings = $settings_class->validate($submitted_settings);

        foreach ($validated_settings as $key => $value) {
            // The validate method already encrypts API keys
            $settings_class->set($key, $value);
        }

        add_settings_error('general', 'settings_updated', __('Settings saved.', 'ai-review-generator'), 'success');

        // We return an empty array because we are saving the options manually.
        return [];
    }

    public function render_field_callback($args) {
        $settings = ai_review_generator()->get_settings();
        $option_name = $args['id'];
        $value = $settings->get($option_name);
        $config = $settings->get_field_config($option_name);

        if (!$config) return;

        switch ($config['type']) {
            case 'checkbox':
                echo "<input type='checkbox' id='{$option_name}' name='{$option_name}' value='1' " . checked(1, $value, false) . ">";
                break;
            case 'number':
            case 'text':
                echo "<input type='{$config['type']}' id='{$option_name}' name='{$option_name}' value='" . esc_attr($value) . "' class='regular-text'>";
                break;
            case 'password':
                 echo "<input type='password' id='{$option_name}' name='{$option_name}' value='" . esc_attr($value) . "' class='regular-text' placeholder='" . esc_attr__('Enter API Key', 'ai-review-generator') . "'>";
                break;
            case 'select':
                echo "<select id='{$option_name}' name='{$option_name}'>";
                foreach ($config['options'] as $key => $label) {
                    echo "<option value='" . esc_attr($key) . "' " . selected($value, $key, false) . ">" . esc_html($label) . "</option>";
                }
                echo "</select>";
                break;
            case 'range':
                echo "<input type='range' id='{$option_name}' name='{$option_name}' value='" . esc_attr($value) . "' min='{$config['min']}' max='{$config['max']}' step='{$config['step']}'>";
                echo " <span id='{$option_name}-value'>" . esc_html($value) . "%</span>";
                echo "<script>document.getElementById('{$option_name}').addEventListener('input', function() { document.getElementById('{$option_name}-value').textContent = this.value + '%'; });</script>";
                break;
            case 'multicheckbox':
                echo "<fieldset>";
                foreach ($config['options'] as $key => $label) {
                    $checked = is_array($value) && in_array($key, $value) ? 'checked' : '';
                    echo "<label><input type='checkbox' name='{$option_name}[]' value='" . esc_attr($key) . "' {$checked}> " . esc_html($label) . "</label><br>";
                }
                echo "</fieldset>";
                break;
        }

        if (!empty($config['description'])) {
            echo "<p class='description'>" . esc_html($config['description']) . "</p>";
        }
    }

    public function register_settings() {
        register_setting(
            'ai_review_generator_settings',
            'ai_review_generator_options', // This is a dummy option name. We handle saving manually.
            array($this, 'handle_settings_save')
        );

        add_settings_section(
            'ai_review_generator_general',
            __('General Settings', 'ai-review-generator'),
            null,
            'ai-review-generator-settings'
        );

        add_settings_section(
            'ai_review_generator_api',
            __('API Settings', 'ai-review-generator'),
            array($this, 'api_section_callback'),
            'ai-review-generator-settings'
        );

        add_settings_section(
            'ai_review_generator_content',
            __('Review Content', 'ai-review-generator'),
            null,
            'ai-review-generator-settings'
        );

        add_settings_section(
            'ai_review_generator_scheduling',
            __('Scheduling', 'ai-review-generator'),
            null,
            'ai-review-generator-settings'
        );

        add_settings_section(
            'ai_review_generator_advanced',
            __('Advanced Settings', 'ai-review-generator'),
            null,
            'ai-review-generator-settings'
        );

        $settings_class = ai_review_generator()->get_settings();
        $all_fields = $settings_class->get_all_fields_config();

        foreach ($all_fields as $field_id => $field_config) {
            add_settings_field(
                $field_id,
                $field_config['label'],
                array($this, 'render_field_callback'),
                'ai-review-generator-settings',
                $field_config['section'],
                array('id' => $field_id)
            );
        }
    }

    /**
     * AJAX handler for testing API connection
     */
    public function test_api_connection() {
        check_ajax_referer('ai-review-generator-admin', 'nonce');

        $ai_manager = ai_review_generator()->ai_manager;
        $result = $ai_manager->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('API connection successful!', 'ai-review-generator')]);
        }
    }

    /**
     * AJAX handler for generating a preview review
     */
    public function generate_preview_review() {
        check_ajax_referer('ai-review-generator-admin', 'nonce');

        $product_manager = ai_review_generator()->product_manager;
        $ai_manager = ai_review_generator()->ai_manager;

        $product = $product_manager->get_random_product_for_review();

        if (!$product) {
            wp_send_json_error(['message' => __('No eligible products found to generate a preview.', 'ai-review-generator')]);
            return;
        }

        $review_params = [
            'rating' => rand(4, 5),
            'style' => 'enthusiastic'
        ];

        $review_content = $ai_manager->generate_review($product, $review_params);

        if (is_wp_error($review_content)) {
            wp_send_json_error(['message' => $review_content->get_error_message()]);
        } else {
            wp_send_json_success([
                'product_name' => $product->get_name(),
                'review' => $review_content
            ]);
        }
    }

    /**
     * AJAX handler for syncing products
     */
    public function sync_products() {
        check_ajax_referer('ai-review-generator-admin', 'nonce');

        $product_manager = ai_review_generator()->product_manager;
        $result = $product_manager->sync_all_products();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => sprintf(__('%d products were added to the review rotation.', 'ai-review-generator'), $result)]);
        }
    }

    /**
     * Handle queue bulk actions
     */
    /**
     * Handle product-related actions like toggling exclusion.
     */
    public function handle_product_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'ai-review-generator-products') {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'toggle_exclusion' && isset($_GET['product_id'])) {
            $product_id = intval($_GET['product_id']);
            $nonce = $_REQUEST['_wpnonce'] ?? '';

            if (!wp_verify_nonce($nonce, 'toggle_exclusion_' . $product_id)) {
                $this->add_notice(__('Security check failed.', 'ai-review-generator'), 'error');
                return;
            }

            $product_manager = ai_review_generator()->product_manager;
            $db_manager = ai_review_generator()->database;

            $rotation_data = $db_manager->get_product_rotation_data($product_id);
            $is_currently_excluded = $rotation_data ? (bool) $rotation_data->is_excluded : false;

            $product_manager->toggle_product_exclusion($product_id, !$is_currently_excluded);

            $message = !$is_currently_excluded ? __('Product excluded from rotation.', 'ai-review-generator') : __('Product included in rotation.', 'ai-review-generator');
            $this->add_notice($message, 'success');

            // Redirect to avoid re-processing on refresh
            wp_safe_redirect(remove_query_arg(['action', 'product_id', '_wpnonce']));
            exit;
        }
    }

    /**
     * Handle queue bulk actions
     */
    public function handle_bulk_actions() {
        $action = $_REQUEST['action'] ?? '';
        $action2 = $_REQUEST['action2'] ?? '';
        $current_action = $action === '-1' ? $action2 : $action;

        if (empty($current_action) || !isset($_REQUEST['queue_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_REQUEST['queue_nonce'], 'queue_bulk_actions')) {
            $this->add_notice(__('Security check failed.', 'ai-review-generator'), 'error');
            return;
        }

        $queue_items = isset($_REQUEST['queue_items']) ? array_map('intval', $_REQUEST['queue_items']) : [];

        if (empty($queue_items)) {
            return;
        }

        $database = ai_review_generator()->database;
        $count = count($queue_items);
        $message = '';

        switch ($current_action) {
            case 'delete':
                $database->delete_queue_items($queue_items);
                $message = sprintf(__('%d queue item(s) deleted.', 'ai-review-generator'), $count);
                break;

            case 'retry':
                $database->reset_queue_items($queue_items);
                $message = sprintf(__('%d queue item(s) set for retry.', 'ai-review-generator'), $count);
                break;

            case 'reschedule':
                $database->reschedule_queue_items($queue_items);
                $message = sprintf(__('%d queue item(s) rescheduled.', 'ai-review-generator'), $count);
                break;
        }

        if ($message) {
            $this->add_notice($message, 'success');
        }
    }

    /**
     * Add a notice to be displayed in the admin area.
     */
    public function add_notice($message, $type = 'info') {
        $this->notices[] = ['message' => $message, 'type' => $type];
    }

    /**
     * Display admin notices.
     */
    public function display_notices() {
        foreach ($this->notices as $notice) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($notice['type']), esc_html($notice['message']));
        }
    }
}