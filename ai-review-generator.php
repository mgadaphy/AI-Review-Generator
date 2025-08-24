<?php
/**
 * Plugin Name: AI Review Generator
 * Plugin URI: https://mogadonko.com/plugins/ai-review-generator
 * Description: Automatically generate realistic product reviews using AI models for WooCommerce stores
 * Version: 1.0.0
 * Author: MOGADONKO AGENCY
 * Author URI: https://mogadonko.com
 * Text Domain: ai-review-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AI_REVIEW_GENERATOR_VERSION', '1.0.0');
define('AI_REVIEW_GENERATOR_PLUGIN_FILE', __FILE__);
define('AI_REVIEW_GENERATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_REVIEW_GENERATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_REVIEW_GENERATOR_PLUGIN_BASENAME', plugin_basename(__FILE__));


// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'AI_Review_Generator_') !== 0) {
        return;
    }

    $prefix = 'AI_Review_Generator_';
    $base_dir = AI_REVIEW_GENERATOR_PLUGIN_DIR;

    // Get the part of the class name after the prefix
    $relative_class = str_replace($prefix, '', $class);

    // Convert from snake_case (or CamelCase) to kebab-case for the filename
    $file_path = str_replace('_', '-', strtolower($relative_class));

    // Define the path mappings for special cases
    $path_map = [
        'admin-interface'  => 'admin/class-admin-interface.php',
        'database-manager' => 'database/class-manager.php',
        'scheduler'        => 'cron/class-scheduler.php',
    ];

    if (isset($path_map[$file_path])) {
        $file = $base_dir . $path_map[$file_path];
    } else if (strpos($file_path, 'model-') === 0) {
        // Handle models, e.g., model-openai-api -> models/class-openai-api.php
        $model_file = str_replace('model-', '', $file_path);
        $file = $base_dir . 'models/class-' . $model_file . '.php';
    } else {
        // Default to includes directory for all other classes
        $file = $base_dir . 'includes/class-' . $file_path . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Main Plugin Class
 */
register_activation_hook(AI_REVIEW_GENERATOR_PLUGIN_FILE, array('AI_Review_Generator', 'activate'));
register_deactivation_hook(AI_REVIEW_GENERATOR_PLUGIN_FILE, array('AI_Review_Generator', 'deactivate'));

class AI_Review_Generator {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $settings;
    public $admin;
    public $database;
    public $scheduler;
    public $ai_manager;
    public $review_generator;
    public $product_manager;
    public $core;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
        private function init() {
        // Setup the plugin now that all plugins are loaded.
        $this->setup();
    }

    /**
     * Setup plugin once all other plugins are loaded
     */
    public function setup() {
        // Now check for WooCommerce
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize components
        $this->init_components();

        // Hook into WordPress
        add_action('init', array($this, 'load_textdomain'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        $class = 'notice notice-error';
        $message = __('AI Review Generator requires WooCommerce to be installed and active.', 'ai-review-generator');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('ai-review-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    

    /**
     * Initialize plugin components
     */
    public function init_components() {
        $this->database = new AI_Review_Generator_Database_Manager();
        $this->settings = new AI_Review_Generator_Settings();
        $this->admin = new AI_Review_Generator_Admin_Interface();
        $this->scheduler = new AI_Review_Generator_Scheduler();
        $this->ai_manager = new AI_Review_Generator_AI_Manager();
        $this->review_generator = new AI_Review_Generator_Review_Generator();
        $this->product_manager = new AI_Review_Generator_Product_Manager();
        $this->core = AI_Review_Generator_Core::instance();
    }
    
    /**
     * Plugin activation
     */
        public static function activate() {
        $database = new AI_Review_Generator_Database_Manager();
        $database->create_tables();

        // Set default options directly to avoid dependency issues on activation
        if (!get_option('ai_review_generator_settings')) {
            $default_settings = array(
                'api_key' => '',
                'api_provider' => 'openai',
                'review_style' => 'detailed',
                'review_tone' => 'neutral',
                'review_interval' => 'daily',
                'reviews_per_interval' => 1,
                'product_status' => 'any',
                'min_stock' => 0,
                'min_reviews' => 0,
                'max_reviews' => 10,
                'days_since_last_review' => 30,
                'product_ids' => '',
                'excluded_product_ids' => '',
                'product_categories' => array(),
                'enable_logging' => 0,
            );
            add_option('ai_review_generator_settings', $default_settings);
        }

        if (!wp_next_scheduled('ai_review_generator_daily_cron')) {
            wp_schedule_event(time(), 'daily', 'ai_review_generator_daily_cron');
        }

        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled('ai_review_generator_daily_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ai_review_generator_daily_cron');
        }

        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Review Generator', 'ai-review-generator'),
            __('AI Reviews', 'ai-review-generator'),
            'manage_woocommerce',
            'ai-review-generator',
            array($this->admin, 'display_main_page'),
            'dashicons-star-filled',
            56
        );
        
        add_submenu_page(
            'ai-review-generator',
            __('Settings', 'ai-review-generator'),
            __('Settings', 'ai-review-generator'),
            'manage_woocommerce',
            'ai-review-generator-settings',
            array($this->admin, 'display_settings_page')
        );
        
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'ai-review-generator') === false) {
            return;
        }
        
        wp_enqueue_script(
            'ai-review-generator-admin',
            AI_REVIEW_GENERATOR_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            AI_REVIEW_GENERATOR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ai-review-generator-admin',
            AI_REVIEW_GENERATOR_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            AI_REVIEW_GENERATOR_VERSION
        );
        
        // Localize script
        wp_localize_script('ai-review-generator-admin', 'ai_review_generator_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_review_generator_admin'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'ai-review-generator'),
                'processing' => __('Processing...', 'ai-review-generator'),
                'success' => __('Success!', 'ai-review-generator'),
                'error' => __('Error occurred. Please try again.', 'ai-review-generator')
            )
        ));
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return AI_REVIEW_GENERATOR_VERSION;
    }
    
    /**
     * Log message
     */
    public function log($message, $level = 'info') {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('[AI Review Generator] [' . strtoupper($level) . '] ' . $message);
        }
    }
}

/**
 * Initialize plugin
 */
function ai_review_generator_init() {
    return AI_Review_Generator::instance();
}

// Get the plugin running.
add_action('plugins_loaded', 'ai_review_generator_init');