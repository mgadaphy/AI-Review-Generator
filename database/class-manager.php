<?php
/**
 * Database Manager Class
 * Handles all database operations for the AI Review Generator plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Review_Generator_Database_Manager {
    
    /**
     * Database version
     */
    private $db_version = '1.0.0';
    
    /**
     * Table names
     */
    private $tables = array(
        'reviews_queue' => 'ai_review_generator_queue',
        'review_history' => 'ai_review_generator_history',
        'product_rotation' => 'ai_review_generator_product_rotation',
        'api_usage' => 'ai_review_generator_api_usage',
        'settings' => 'ai_review_generator_settings'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'check_database_version'));
    }
    
    /**
     * Get table name with WordPress prefix
     */
    public function get_table_name($table_key) {
        global $wpdb;
        return $wpdb->prefix . $this->tables[$table_key];
    }
    
    /**
     * Check database version and upgrade if needed
     */
    public function check_database_version() {
        $current_version = get_option('ai_review_generator_db_version', '0');
        
        if (version_compare($current_version, $this->db_version, '<')) {
            $this->create_tables();
            update_option('ai_review_generator_db_version', $this->db_version);
        }
    }
    
    /**
     * Create all plugin tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Reviews Queue Table
        $table_name = $this->get_table_name('reviews_queue');
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            reviewer_name varchar(100) NOT NULL,
            reviewer_email varchar(100) NOT NULL,
            rating tinyint(1) NOT NULL,
            review_text longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            scheduled_date datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            ai_model varchar(50) NOT NULL,
            generation_params longtext NULL,
            error_message text NULL,
            retry_count tinyint(2) DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY status (status),
            KEY scheduled_date (scheduled_date),
            KEY ai_model (ai_model)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
        
        // Review History Table
        $table_name = $this->get_table_name('review_history');
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            wc_review_id bigint(20) NULL,
            reviewer_name varchar(100) NOT NULL,
            reviewer_email varchar(100) NOT NULL,
            rating tinyint(1) NOT NULL,
            review_text longtext NOT NULL,
            ai_model varchar(50) NOT NULL,
            generation_params longtext NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            posted_at datetime NULL,
            api_cost decimal(10,6) DEFAULT 0,
            tokens_used int DEFAULT 0,
            generation_time float DEFAULT 0,
            quality_score tinyint(3) DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY wc_review_id (wc_review_id),
            KEY ai_model (ai_model),
            KEY created_at (created_at),
            KEY quality_score (quality_score)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
        
        // Product Rotation Table
        $table_name = $this->get_table_name('product_rotation');
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL UNIQUE,
            total_reviews_generated int DEFAULT 0,
            last_review_date datetime NULL,
            next_scheduled_date datetime NULL,
            priority_score int DEFAULT 50,
            category_ids varchar(255) NULL,
            product_price decimal(10,2) DEFAULT 0,
            product_status varchar(20) DEFAULT 'publish',
            review_target int DEFAULT 0,
            is_excluded tinyint(1) DEFAULT 0,
            created_at datetime NULL,
            updated_at datetime NULL,
            PRIMARY KEY (id),
            KEY last_review_date (last_review_date),
            KEY next_scheduled_date (next_scheduled_date),
            KEY priority_score (priority_score),
            KEY is_excluded (is_excluded)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
        
        // API Usage Table
        $table_name = $this->get_table_name('api_usage');
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ai_model varchar(50) NOT NULL,
            api_endpoint varchar(100) NOT NULL,
            request_date datetime DEFAULT CURRENT_TIMESTAMP,
            tokens_used int DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            response_time float DEFAULT 0,
            status varchar(20) NOT NULL,
            error_message text NULL,
            request_data longtext NULL,
            response_data longtext NULL,
            PRIMARY KEY (id),
            KEY ai_model (ai_model),
            KEY request_date (request_date),
            KEY status (status)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
        
        // Settings Table (for complex settings that need structure)
        $table_name = $this->get_table_name('settings');
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL UNIQUE,
            setting_value longtext NULL,
            setting_type varchar(20) DEFAULT 'string',
            is_encrypted tinyint(1) DEFAULT 0,
            created_at datetime NULL,
            updated_at datetime NULL,
            PRIMARY KEY (id),
            KEY setting_type (setting_type)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }
    
    /**
     * Execute SQL with error handling
     */
    private function execute_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            error_log('AI Review Generator DB Delta Error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Insert review into queue
     */
    public function insert_review_queue($data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('reviews_queue');
        
        $defaults = array(
            'status' => 'pending',
            'scheduled_date' => current_time('mysql'),
            'retry_count' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log('Failed to insert review queue: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get reviews from queue
     */
    public function get_queue_reviews($limit = 10, $status = 'pending') {
        global $wpdb;
        
        $table_name = $this->get_table_name('reviews_queue');
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = %s 
             AND scheduled_date <= %s 
             ORDER BY scheduled_date ASC 
             LIMIT %d",
            $status,
            current_time('mysql'),
            $limit
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Update queue review status
     */
    public function update_queue_status($id, $status, $error_message = null) {
        global $wpdb;
        
        $table_name = $this->get_table_name('reviews_queue');
        
        $data = [
            'status' => $status,
            'processed_at' => current_time('mysql'),
        ];

        if (!empty($error_message)) {
            $data['error_message'] = $error_message;
        }

        if ($status === 'failed') {
            return $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET status = %s, error_message = %s, processed_at = %s, retry_count = retry_count + 1 WHERE id = %d",
                $status,
                $error_message,
                current_time('mysql'),
                $id
            ));
        } else {
            return $wpdb->update($table_name, $data, ['id' => $id]);
        }
    }
    
    /**
     * Insert review into history
     */
    public function insert_review_history($data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('review_history');
        
        $defaults = array(
            'posted_at' => current_time('mysql'),
            'api_cost' => 0,
            'tokens_used' => 0,
            'generation_time' => 0,
            'quality_score' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log('Failed to insert review history: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update product rotation data
     */
    public function update_product_rotation($product_id, $data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('product_rotation');
        
        // Check if product exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE product_id = %d",
            $product_id
        ));
        
        if ($exists) {
            // Update existing
            $data['updated_at'] = current_time('mysql');
            return $wpdb->update(
                $table_name,
                $data,
                array('product_id' => $product_id)
            );
        } else {
            // Insert new
            $data['product_id'] = $product_id;
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            
            return $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Get total count of products for pagination.
     */
    public function get_total_products_count($search = '') {
        global $wpdb;
        $posts_table = $wpdb->posts;

        $sql = "SELECT COUNT(p.ID) 
                FROM {$posts_table} p
                WHERE p.post_type = 'product' AND p.post_status = 'publish'";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND p.post_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * Get all products for admin list table.
     */
    public function get_all_products_for_admin($per_page, $offset, $search = '') {
        global $wpdb;
        $rotation_table = $this->get_table_name('product_rotation');
        $posts_table = $wpdb->posts;

        $sql = "SELECT p.ID, p.post_title, rot.is_excluded, rot.total_reviews_generated, rot.last_review_date
                FROM {$posts_table} p
                LEFT JOIN {$rotation_table} rot ON p.ID = rot.product_id
                WHERE p.post_type = 'product' AND p.post_status = 'publish'";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND p.post_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $sql .= " ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get rotation data for a single product.
     */
    public function get_product_rotation_data($product_id) {
        global $wpdb;
        $table_name = $this->get_table_name('product_rotation');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE product_id = %d", $product_id));
    }

    /**
     * Get product rotation data.
     */
    public function get_product_rotation($limit = null, $exclude_reviewed_today = false) {
        global $wpdb;
        
        $table_name = $this->get_table_name('product_rotation');
        
        $where_clauses = array('is_excluded = 0');
        $params = array();

        if ($exclude_reviewed_today) {
            $today = current_time('Y-m-d');
            $where_clauses[] = "(last_review_date IS NULL OR DATE(last_review_date) < %s)";
            $params[] = $today;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY priority_score DESC, last_review_date ASC";
        
        if ($limit) {
            $sql .= " LIMIT %d";
            $params[] = $limit;
        }

        $sql = $wpdb->prepare($sql, $params);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Log API usage.
     */
    public function log_api_usage($data) {
        global $wpdb;
        
        $table_name = $this->get_table_name('api_usage');
        
        $defaults = array(
            'request_date' => current_time('mysql'),
            'tokens_used' => 0,
            'cost' => 0,
            'response_time' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table_name, $data);
    }
    
    /**
     * Get total count of review history for pagination.
     */
    public function get_review_history_count() {
        global $wpdb;
        $history_table = $this->get_table_name('review_history');

        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$history_table}");
    }

    /**
     * Get all review history for admin list table.
     */
    public function get_review_history($per_page, $offset) {
        global $wpdb;
        $history_table = $this->get_table_name('review_history');
        $posts_table = $wpdb->posts;

        $sql = $wpdb->prepare(
            "SELECT h.*, p.post_title 
             FROM {$history_table} h
             LEFT JOIN {$posts_table} p ON h.product_id = p.ID
             ORDER BY h.created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get dashboard statistics.
     */
    public function get_dashboard_stats() {
        global $wpdb;

        $rotation_table = $this->get_table_name('product_rotation');
        $queue_table = $this->get_table_name('reviews_queue');
        $history_table = $this->get_table_name('review_history');

        $stats = [];

        $stats['products_in_rotation'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $rotation_table WHERE is_excluded = 0");
        $stats['pending_reviews'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE status = %s", 'pending'));
        $stats['reviews_generated'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $history_table");

        return $stats;
    }

    /**
     * Get analytics data.
     */
    public function get_analytics($date_from = null, $date_to = null) {
        global $wpdb;
        
        $history_table = $this->get_table_name('review_history');
        $usage_table = $this->get_table_name('api_usage');
        
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }
        
        // Reviews generated
        $reviews_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $history_table WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // API costs
        $total_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(api_cost) FROM $history_table WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Average rating
        $avg_rating = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(rating) FROM $history_table WHERE DATE(created_at) BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        return array(
            'reviews_count' => (int) $reviews_count,
            'total_cost' => (float) $total_cost,
            'avg_rating' => (float) $avg_rating,
            'date_range' => array($date_from, $date_to)
        );
    }
    
    /**
     * Clean up old data
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Clean up processed queue items
        $queue_table = $this->get_table_name('reviews_queue');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $queue_table WHERE status IN ('completed', 'failed') AND processed_at < %s",
            $date_threshold
        ));
        
        // Clean up old API usage logs
        $usage_table = $this->get_table_name('api_usage');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $usage_table WHERE request_date < %s",
            $date_threshold
        ));
        
        error_log("Cleaned up data older than $days days");
    }
    
    /**
     * Drop all plugin tables (for uninstall)
     */
    public function drop_tables() {
        global $wpdb;
        
        foreach ($this->tables as $table_key => $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $wpdb->query("DROP TABLE IF EXISTS $full_table_name");
        }
    }
}