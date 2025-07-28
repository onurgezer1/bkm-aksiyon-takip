<?php
/**
 * Plugin Name: BKM AKSİYON TAKİP
 * Plugin URI: https://github.com/anadolubirlik/BKMAksiyonTakip_Claude4
 * Description: WordPress eklentisi ile aksiyon ve görev takip sistemi
 * Version: 1.0.4
 * Author: Anadolu Birlik
 * Text Domain: bkm-aksiyon-takip
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BKM_AKSIYON_TAKIP_VERSION', '1.0.4');
define('BKM_AKSIYON_TAKIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BKM_AKSIYON_TAKIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BKM_AKSIYON_TAKIP_PLUGIN_FILE', __FILE__);

// Ortak HTML e-posta şablonu fonksiyonu
define('BKM_EMAIL_TEMPLATE_HEADER', '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{subject}</title></head><body style="font-family: Arial, sans-serif; background: #f6f8fa; margin:0; padding:0;"><div style="max-width:600px;margin:40px auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px #e0e0e0;overflow:hidden;"><div style="background:#0073aa;color:#fff;padding:24px 32px 16px 32px;"><h2 style="margin:0;font-size:24px;">BKM Aksiyon Takip</h2></div><div style="padding:32px;">');
define('BKM_EMAIL_TEMPLATE_FOOTER', '</div><div style="background:#f6f8fa;color:#888;padding:16px 32px;text-align:center;font-size:13px;">Bu e-posta otomatik olarak oluşturulmuştur.<br>BKM Aksiyon Takip Sistemi</div></div></body></html>');

/**
 * Debug logging function - only logs when WP_DEBUG is enabled
 */
function bkm_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[BKM] ' . $message);
    }
}

/**
 * Enable debug mode for this plugin temporarily
 */
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

function bkm_get_html_email($subject, $content_html) {
    $header = str_replace('{subject}', esc_html($subject), BKM_EMAIL_TEMPLATE_HEADER);
    $footer = BKM_EMAIL_TEMPLATE_FOOTER;
    return $header . $content_html . $footer;
}

function bkm_send_html_email($to, $subject, $content_html, $headers = array()) {
    $body = bkm_get_html_email($subject, $content_html);
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    return wp_mail($to, $subject, $body, $headers);
}

/**
 * Main plugin class
 */
class BKM_Aksiyon_Takip {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'setup_role_capabilities'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Add shortcode
        add_shortcode('aksiyon_takipx', array($this, 'shortcode_handler'));
        
        // Add AJAX handlers
        add_action('wp_ajax_bkm_refresh_stats', array($this, 'ajax_refresh_stats'));
        add_action('wp_ajax_bkm_delete_item', array($this, 'ajax_delete_item'));
        add_action('wp_ajax_bkm_update_task_progress', array($this, 'ajax_update_task_progress'));
        
        // Note AJAX handlers
        add_action('wp_ajax_bkm_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_nopriv_bkm_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_bkm_reply_note', array($this, 'ajax_reply_note'));
        add_action('wp_ajax_nopriv_bkm_reply_note', array($this, 'ajax_reply_note'));
        add_action('wp_ajax_bkm_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_nopriv_bkm_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_bkm_check_tables', array($this, 'ajax_check_tables'));
        add_action('wp_ajax_nopriv_bkm_check_tables', array($this, 'ajax_check_tables'));
        
        // Action AJAX handlers
        add_action('wp_ajax_bkm_add_action', array($this, 'ajax_add_action'));
        add_action('wp_ajax_nopriv_bkm_add_action', array($this, 'ajax_add_action'));
        add_action('wp_ajax_bkm_get_actions', array($this, 'ajax_get_actions'));
        add_action('wp_ajax_nopriv_bkm_get_actions', array($this, 'ajax_get_actions'));
        
        // Task AJAX handlers
        add_action('wp_ajax_bkm_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_nopriv_bkm_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_bkm_get_tasks', array($this, 'ajax_get_tasks'));
        add_action('wp_ajax_nopriv_bkm_get_tasks', array($this, 'ajax_get_tasks'));
        add_action('wp_ajax_bkm_complete_task', array($this, 'ajax_complete_task'));
        add_action('wp_ajax_nopriv_bkm_complete_task', array($this, 'ajax_complete_task'));
        add_action('wp_ajax_bkm_get_task_notes', array($this, 'ajax_get_task_notes'));
        add_action('wp_ajax_nopriv_bkm_get_task_notes', array($this, 'ajax_get_task_notes'));
        
        // Category AJAX handlers
        add_action('wp_ajax_bkm_add_category', array($this, 'ajax_add_category'));
        add_action('wp_ajax_bkm_edit_category', array($this, 'ajax_edit_category'));
        add_action('wp_ajax_bkm_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_bkm_get_categories', array($this, 'ajax_get_categories'));
        
        // Performance AJAX handlers
        add_action('wp_ajax_bkm_add_performance', array($this, 'ajax_add_performance'));
        add_action('wp_ajax_bkm_edit_performance', array($this, 'ajax_edit_performance'));
        add_action('wp_ajax_bkm_delete_performance', array($this, 'ajax_delete_performance'));
        add_action('wp_ajax_bkm_get_performances', array($this, 'ajax_get_performances'));
        
        // User AJAX handlers
        add_action('wp_ajax_bkm_add_user', array($this, 'ajax_add_user'));
        add_action('wp_ajax_bkm_edit_user', array($this, 'ajax_edit_user'));
        add_action('wp_ajax_bkm_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_bkm_get_users', array($this, 'ajax_get_users'));
        add_action('wp_ajax_nopriv_bkm_get_users', array($this, 'ajax_get_users')); // Giriş yapmamış kullanıcılar için
        
        // Company AJAX handlers
        add_action('wp_ajax_bkm_save_company_settings', array($this, 'ajax_save_company_settings'));
        add_action('wp_ajax_bkm_upload_company_logo', array($this, 'ajax_upload_company_logo'));
        add_action('wp_ajax_bkm_remove_company_logo', array($this, 'ajax_remove_company_logo'));
        add_action('wp_ajax_bkm_get_company_info', array($this, 'ajax_get_company_info'));
        add_action('wp_ajax_nopriv_bkm_get_company_info', array($this, 'ajax_get_company_info'));
        
        // Forgot password AJAX handler
        add_action('wp_ajax_bkm_forgot_password', array($this, 'ajax_forgot_password'));
        add_action('wp_ajax_nopriv_bkm_forgot_password', array($this, 'ajax_forgot_password'));
        
        // Custom login handling
        add_action('wp_login_failed', array($this, 'handle_login_failed'), 10, 2);
        add_filter('authenticate', array($this, 'custom_authenticate'), 30, 3);
        
        // User activity logging
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        add_action('clear_auth_cookie', array($this, 'log_user_logout'));
        
        // Session timeout check
        add_action('init', array($this, 'check_session_timeout'), 5);
        add_action('wp_ajax_bkm_heartbeat', array($this, 'ajax_heartbeat'));
        add_action('wp_ajax_nopriv_bkm_heartbeat', array($this, 'ajax_heartbeat'));
        
        // AJAX: Kullanıcı listesini döndür
        add_action('wp_ajax_bkm_get_users', 'bkm_get_users_callback');
        add_action('wp_ajax_nopriv_bkm_get_users', 'bkm_get_users_callback');
    }
    
    /**
     * Get current page URL
     */
    private function get_current_page_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }
    
    /**
     * Handle login failures
     */
    public function handle_login_failed($username, $error) {
        // Redirect back to login page with error message
        $redirect_url = $this->get_current_page_url();
        $redirect_url = add_query_arg('login_error', urlencode($error->get_error_message()), $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Custom authentication
     */
    public function custom_authenticate($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }
        
        if (!empty($username) && !empty($password)) {
            $user = wp_authenticate_username_password(null, $username, $password);
            if (is_wp_error($user)) {
                return $user;
            }
            
            // Log successful login
            $this->log_user_login($username, $user);
            
            // Set auth cookie
            wp_set_auth_cookie($user->ID, isset($_POST['rememberme']));
            
            // Redirect to the current page after successful login
            $redirect_url = $this->get_current_page_url();
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        return $user;
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Check and create missing database tables
        $this->check_and_create_tables();
        
        // Load text domain for translations
        load_plugin_textdomain('bkm-aksiyon-takip', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Handle custom login form submission
        if (isset($_POST['bkm_login_submit']) && isset($_POST['bkm_nonce']) && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_login_nonce')) {
            $username = sanitize_text_field($_POST['log']);
            $password = $_POST['pwd'];
            $remember = isset($_POST['rememberme']) ? true : false;
            
            $user = wp_signon(array(
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $remember
            ), is_ssl());
            
            if (is_wp_error($user)) {
                // Store error message in transient to display on redirect
                set_transient('bkm_login_error', $user->get_error_message(), 30);
                wp_safe_redirect($this->get_current_page_url());
                exit;
            } else {
                // Log successful login
                $this->log_user_login($username, $user);
                
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, $remember);
                wp_safe_redirect($this->get_current_page_url());
                exit;
            }
        }
    }
    
    /**
     * Setup role capabilities for BKM plugin
     * Editör rolüne sadece sınırlı yetkiler verir, yönetici yetkilerini vermez
     */
    public function setup_role_capabilities() {
        // Get the Editor role
        $editor_role = get_role('editor');
        
        if ($editor_role) {
            // SADECE editör için gereken yetkiler - kullanıcı yönetimi YOK
            $editor_capabilities = array(
                'read',                // Temel okuma yetkisi
                'create_users',        // Sadece kullanıcı ekleme
                // 'delete_users' YOK - sil yetkisi yok
                // 'edit_users' YOK - düzenleme yetkisi yok  
                // 'manage_options' YOK - admin ayarları yetkisi yok
                'list_users',          // Kullanıcı listesini görme
            );
            
            // Editör rolünden admin yetkilerini kaldır (eğer daha önce eklenmişse)
            $admin_only_capabilities = array(
                'manage_options',
                'delete_users', 
                'edit_users',
                'promote_users',
                'remove_users'
            );
            
            // Admin yetkilerini kaldır
            foreach ($admin_only_capabilities as $capability) {
                $editor_role->remove_cap($capability);
            }
            
            // Sadece editör yetkilerini ekle
            foreach ($editor_capabilities as $capability) {
                $editor_role->add_cap($capability);
            }
        }
        
        // Administrator rolünün tüm yetkilerini koruyalım
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_capabilities = array(
                'manage_options',
                'delete_users',
                'create_users', 
                'edit_users',
                'list_users',
                'promote_users',
                'remove_users'
            );
            
            foreach ($admin_capabilities as $capability) {
                $admin_role->add_cap($capability);
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        $this->check_requirements();
        
        // Force table creation and log results
        error_log('🚀 BKM Plugin activation started');
        $table_creation_result = $this->create_database_tables();
        error_log('📊 BKM Table creation result: ' . ($table_creation_result ? 'SUCCESS' : 'FAILED'));
        
        // Setup role capabilities
        $this->setup_role_capabilities();
        
        // Create default categories
        //$this->create_default_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('bkm_aksiyon_takip_activated', true);
        
        error_log('✅ BKM Plugin activation completed');
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        global $wp_version;
        
        if (version_compare($wp_version, '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti WordPress 5.0 veya üzeri sürüm gerektirir.');
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti PHP 7.4 veya üzeri sürüm gerektirir.');
        }
        
        if (!function_exists('wp_mail')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Bu eklenti wp_mail fonksiyonuna ihtiyaç duyar.');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
/**
 * Create database tables
 */
private function create_database_tables() {
    global $wpdb;
    
    // First check if WordPress database is available
    if (!$wpdb) {
        error_log('❌ BKM Error: WordPress database object not available during table creation');
        return false;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Actions table
    $actions_table = $wpdb->prefix . 'bkm_actions';
    $actions_sql = "CREATE TABLE $actions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tanımlayan_id bigint(20) UNSIGNED DEFAULT 1,
        onem_derecesi tinyint(1) NOT NULL DEFAULT 1,
        acilma_tarihi date NOT NULL,
        hafta int(11) NOT NULL,
        kategori_id mediumint(9) NOT NULL,
        sorumlu_ids text NOT NULL,
        tespit_konusu text NOT NULL,
        aciklama text NOT NULL,
        hedef_tarih date NOT NULL,
        kapanma_tarihi date NULL,
        performans_id mediumint(9) NOT NULL,
        ilerleme_durumu int(3) NOT NULL DEFAULT 0,
        notlar text,
        title varchar(500) DEFAULT '',
        description text DEFAULT '',
        priority varchar(50) DEFAULT 'normal',
        start_date date NULL,
        target_date date NULL,
        responsible varchar(255) DEFAULT '',
        status varchar(50) DEFAULT 'open',
        category_id mediumint(9) DEFAULT 0,
        created_by bigint(20) UNSIGNED DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Categories table
    $categories_table = $wpdb->prefix . 'bkm_categories';
    $categories_sql = "CREATE TABLE $categories_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        created_by bigint(20) UNSIGNED DEFAULT 1,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Performance table
    $performance_table = $wpdb->prefix . 'bkm_performances';
    $performance_sql = "CREATE TABLE $performance_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        value decimal(10,2) DEFAULT 0.00,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        created_by bigint(20) UNSIGNED DEFAULT 1,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Tasks table
    $tasks_table = $wpdb->prefix . 'bkm_tasks';
    $tasks_sql = "CREATE TABLE $tasks_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        action_id mediumint(9) NOT NULL,
        content text NOT NULL,
        baslangic_tarihi date NOT NULL,
        sorumlu_id bigint(20) UNSIGNED NOT NULL,
        hedef_bitis_tarihi date NOT NULL,
        ilerleme_durumu int(3) NOT NULL DEFAULT 0,
        gercek_bitis_tarihi datetime NULL,
        tamamlandi tinyint(1) NOT NULL DEFAULT 0,
        title varchar(500) DEFAULT '',
        description text DEFAULT '',
        responsible varchar(255) DEFAULT '',
        start_date date NULL,
        target_date date NULL,
        status varchar(50) DEFAULT 'pending',
        progress int(3) DEFAULT 0,
        completed_at datetime NULL,
        created_by bigint(20) UNSIGNED DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action_id (action_id)
    ) $charset_collate;";
    
    // Task Notes table
    $notes_table = $wpdb->prefix . 'bkm_task_notes';
    $notes_sql = "CREATE TABLE $notes_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        task_id mediumint(9) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        content text NOT NULL,
        parent_note_id mediumint(9) NULL,
        progress int(3) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY task_id (task_id),
        KEY parent_note_id (parent_note_id)
    ) $charset_collate;";
    
    // User Activities Logs table
    $user_activities_table = $wpdb->prefix . 'bkm_user_activities_logs';
    $user_activities_sql = "CREATE TABLE $user_activities_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        username varchar(255) NOT NULL,
        activity_type varchar(50) NOT NULL,
        ip_address varchar(45) NOT NULL,
        user_agent text,
        session_id varchar(255),
        login_time datetime DEFAULT CURRENT_TIMESTAMP,
        logout_time datetime NULL,
        last_activity datetime DEFAULT CURRENT_TIMESTAMP,
        logout_reason varchar(100) DEFAULT 'manual',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY activity_type (activity_type),
        KEY login_time (login_time),
        KEY last_activity (last_activity)
    ) $charset_collate;";
    
    // Task Note Replies table
    $note_replies_table = $wpdb->prefix . 'bkm_task_note_replies';
    $note_replies_sql = "CREATE TABLE $note_replies_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        note_id mediumint(9) NOT NULL,
        reply text NOT NULL,
        created_by bigint(20) UNSIGNED NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY note_id (note_id),
        KEY created_by (created_by)
    ) $charset_collate;";
    
    // Include WordPress database upgrade functions
    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    
    // Create tables with error checking
    $results = array();
    $results['actions'] = dbDelta($actions_sql);
    $results['categories'] = dbDelta($categories_sql);
    $results['performance'] = dbDelta($performance_sql);
    $results['tasks'] = dbDelta($tasks_sql);
    $results['notes'] = dbDelta($notes_sql);
    $results['user_activities'] = dbDelta($user_activities_sql);
    $results['note_replies'] = dbDelta($note_replies_sql);
    
    // Log results
    foreach ($results as $table => $result) {
        if (is_array($result) && !empty($result)) {
            error_log("✅ BKM Table creation result for $table: " . implode(', ', $result));
        } else {
            error_log("❌ BKM Table creation failed for $table");
        }
    }
    
    // Verify table creation
    $tables_created = 0;
    $required_tables = array(
        'bkm_actions',
        'bkm_categories', 
        'bkm_performances',
        'bkm_tasks',
        'bkm_task_notes',
        'bkm_user_activities_logs',
        'bkm_task_note_replies'
    );
    
    foreach ($required_tables as $table_name) {
        $full_table_name = $wpdb->prefix . $table_name;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
        
        if ($table_exists) {
            $tables_created++;
            error_log("✅ BKM Table verified: $full_table_name");
        } else {
            error_log("❌ BKM Table verification failed: $full_table_name");
        }
    }
    
    error_log("🎯 BKM Database creation complete: $tables_created/" . count($required_tables) . " tables created");
    
    // Update database version
    update_option('bkm_aksiyon_takip_db_version', BKM_AKSIYON_TAKIP_VERSION);
    
    return $tables_created == count($required_tables);
}

    /**
     * Check and create missing tables
     */
    public function check_and_create_tables() {
        global $wpdb;
        
        // First check if WordPress database is available
        if (!$wpdb) {
            error_log('❌ BKM Error: WordPress database object not available');
            return false;
        }
        
        // Check if we can connect to database
        $test_query = $wpdb->get_var("SELECT 1");
        if ($test_query !== '1') {
            error_log('❌ BKM Error: Database connection failed');
            return false;
        }
        
        // Check if required tables exist
        $required_tables = array(
            'bkm_actions',
            'bkm_categories', 
            'bkm_performances',
            'bkm_tasks',
            'bkm_task_notes',
            'bkm_user_activities_logs',
            'bkm_task_note_replies'
        );
        
        $missing_tables = array();
        foreach ($required_tables as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
            
            if (!$table_exists) {
                $missing_tables[] = $table_name;
                error_log("❌ BKM Missing table: $full_table_name");
            } else {
                error_log("✅ BKM Table exists: $full_table_name");
            }
        }
        
        // Create tables if they don't exist
        if (!empty($missing_tables)) {
            error_log("🔧 BKM Creating missing tables: " . implode(', ', $missing_tables));
            $this->create_database_tables();
            
            // Verify tables were created
            foreach ($missing_tables as $table_name) {
                $full_table_name = $wpdb->prefix . $table_name;
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                
                if (!$table_exists) {
                    error_log("❌ BKM Failed to create table: $full_table_name");
                } else {
                    error_log("✅ BKM Successfully created table: $full_table_name");
                }
            }
        }
        
        // Then check for missing columns and add them
        $this->upgrade_database_structure();
        
        return true;
    }
    
    /**
     * Upgrade database structure for existing installations
     */
    private function upgrade_database_structure() {
        global $wpdb;
        
        // First check if table exists before trying to modify it
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'");
        
        if (!$table_exists) {
            error_log("❌ BKM Error: Table $notes_table does not exist, skipping column upgrades");
            return false;
        }
        
        // Check and add progress column to bkm_task_notes table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $notes_table LIKE 'progress'");
        
        if (empty($column_exists)) {
            $result = $wpdb->query("ALTER TABLE $notes_table ADD COLUMN progress int(3) DEFAULT NULL AFTER parent_note_id");
            if ($result !== false) {
                error_log("✅ Added progress column to $notes_table table");
            } else {
                error_log("❌ Failed to add progress column to $notes_table table: " . $wpdb->last_error);
            }
        }
        
        // Check and add user_name column to bkm_task_notes table for easier querying
        $user_name_exists = $wpdb->get_results("SHOW COLUMNS FROM $notes_table LIKE 'user_name'");
        
        if (empty($user_name_exists)) {
            $wpdb->query("ALTER TABLE $notes_table ADD COLUMN user_name varchar(255) DEFAULT NULL AFTER user_id");
            
            // Populate existing records with user names (first_name + last_name)
            $users = $wpdb->get_results("
                SELECT u.ID, 
                       COALESCE(
                           CONCAT(
                               TRIM(COALESCE(fn.meta_value, '')), 
                               ' ', 
                               TRIM(COALESCE(ln.meta_value, ''))
                           ), 
                           u.display_name, 
                           'Bilinmeyen Kullanıcı'
                       ) as full_name
                FROM {$wpdb->users} u 
                INNER JOIN $notes_table n ON u.ID = n.user_id 
                LEFT JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id AND fn.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id AND ln.meta_key = 'last_name'
                WHERE n.user_name IS NULL
            ");
            
            foreach ($users as $user) {
                $wpdb->update(
                    $notes_table,
                    array('user_name' => $user->full_name),
                    array('user_id' => $user->ID),
                    array('%s'),
                    array('%d')
                );
            }
            
            error_log("✅ Added user_name column to $notes_table table and populated existing records with first_name + last_name");
        } else {
            // Check if there are any notes with empty user_name and populate them with first_name + last_name
            $empty_user_names = $wpdb->get_results("
                SELECT DISTINCT n.user_id, 
                       COALESCE(
                           CONCAT(
                               TRIM(COALESCE(fn.meta_value, '')), 
                               ' ', 
                               TRIM(COALESCE(ln.meta_value, ''))
                           ), 
                           u.display_name, 
                           'Bilinmeyen Kullanıcı'
                       ) as full_name
                FROM $notes_table n 
                LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                LEFT JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id AND fn.meta_key = 'first_name'
                LEFT JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id AND ln.meta_key = 'last_name'
                WHERE (n.user_name IS NULL OR n.user_name = '') AND u.ID IS NOT NULL
            ");
            
            if (!empty($empty_user_names)) {
                foreach ($empty_user_names as $user) {
                    $wpdb->update(
                        $notes_table,
                        array('user_name' => $user->full_name),
                        array('user_id' => $user->user_id),
                        array('%s'),
                        array('%d')
                    );
                }
                error_log("✅ Updated " . count($empty_user_names) . " empty user_name records in $notes_table table with first_name + last_name");
            }
        }
        
        // Fix NULL or 0 tanımlayan_id values in actions table
        $this->fix_tanimlayan_id_values();
        
        // Update tanımlayan_id column to allow NULL and set default
        $this->update_tanimlayan_id_column();
        
        // Add performance indexes
        $this->add_performance_indexes();
    }
    
    /**
     * Add performance indexes to improve query speed
     */
    private function add_performance_indexes() {
        global $wpdb;
        
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        
        // Add indexes for frequently queried columns
        $indexes = array(
            "ALTER TABLE $actions_table ADD INDEX idx_status_created (status, created_at)",
            "ALTER TABLE $actions_table ADD INDEX idx_tanimlayan_status (tanımlayan_id, status)",
            "ALTER TABLE $tasks_table ADD INDEX idx_sorumlu_status (sorumlu_id, status)",
            "ALTER TABLE $tasks_table ADD INDEX idx_status_created (status, created_at)",
            "ALTER TABLE $notes_table ADD INDEX idx_user_created (user_id, created_at)"
        );
        
        foreach ($indexes as $index_sql) {
            // Check if index already exists before creating
            $wpdb->query($index_sql);
            // Ignore errors for existing indexes
        }
        
        bkm_debug_log("✅ Performance indexes added/updated");
    }
    
    /**
     * Update tanımlayan_id column to allow NULL and set default value
     */
    private function update_tanimlayan_id_column() {
        global $wpdb;
        
        $actions_table = $wpdb->prefix . 'bkm_actions';
        
        // Check current column definition
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM $actions_table WHERE Field = 'tanımlayan_id'");
        
        if ($column_info && strpos($column_info->Null, 'NO') !== false) {
            // Column is currently NOT NULL, update it to allow NULL with default
            $result = $wpdb->query("ALTER TABLE $actions_table MODIFY COLUMN tanımlayan_id bigint(20) UNSIGNED DEFAULT 1");
            
            if ($result !== false) {
                error_log("✅ Updated tanımlayan_id column to allow NULL with default value 1");
            } else {
                error_log("❌ Failed to update tanımlayan_id column: " . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Fix NULL or 0 tanımlayan_id values in bkm_actions table
     */
    private function fix_tanimlayan_id_values() {
        global $wpdb;
        
        $actions_table = $wpdb->prefix . 'bkm_actions';
        
        // Check if there are any problematic records
        $problematic_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $actions_table 
            WHERE tanımlayan_id IS NULL OR tanımlayan_id = 0 OR tanımlayan_id = ''
        ");
        
        if ($problematic_count > 0) {
            // Get first admin user as fallback
            $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
            $fallback_admin_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
            
            // Update all problematic records
            $affected_rows = $wpdb->query($wpdb->prepare("
                UPDATE $actions_table 
                SET tanımlayan_id = %d 
                WHERE tanımlayan_id IS NULL OR tanımlayan_id = 0 OR tanımlayan_id = ''
            ", $fallback_admin_id));
            
            if ($affected_rows > 0) {
                error_log("✅ Fixed $affected_rows records with NULL/0 tanımlayan_id values using admin ID: $fallback_admin_id");
            }
        }
    }

    /**
     * Create default data
     */
    private function create_default_data() {
        global $wpdb;
        
        // Default categories
        $categories = array(
            'Kalite',
            'Üretim',
            'Satış',
            'İnsan Kaynakları',
            'Bilgi İşlem'
        );
        
        $categories_table = $wpdb->prefix . 'bkm_categories';
        foreach ($categories as $category) {
            $wpdb->insert($categories_table, array('name' => $category));
        }
        
        // Default performance data
        $performances = array(
            array('name' => 'Düşük', 'value' => 1.0),
            array('name' => 'Orta', 'value' => 2.0),
            array('name' => 'Yüksek', 'value' => 3.0),
            array('name' => 'Kritik', 'value' => 4.0)
        );
        
        $performance_table = $wpdb->prefix . 'bkm_performances';
        foreach ($performances as $performance) {
            $wpdb->insert($performance_table, $performance);
        }
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        // Main menu
        add_menu_page(
            'BKM Aksiyon Takip',
            'Aksiyon Takip',
            'edit_posts',
            'bkm-aksiyon-takip',
            array($this, 'admin_page_main'),
            'dashicons-clipboard',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Aksiyon Ekle',
            'Aksiyon Ekle',
            'edit_posts',
            'bkm-aksiyon-ekle',
            array($this, 'admin_page_add_action')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Kategoriler',
            'Kategoriler',
            'edit_posts',
            'bkm-kategoriler',
            array($this, 'admin_page_categories')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Performanslar',
            'Performanslar',
            'edit_posts',
            'bkm-performanslar',
            array($this, 'admin_page_performance')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Raporlar',
            'Raporlar',
            'edit_posts',
            'bkm-raporlar',
            array($this, 'admin_page_reports')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Kullanıcı Aktiviteleri',
            'Kullanıcı Aktiviteleri',
            'manage_options',
            'bkm-user-activities',
            array($this, 'admin_page_user_activities')
        );
        
        add_submenu_page(
            'bkm-aksiyon-takip',
            'Sistem Durumu',
            'Sistem Durumu',
            'manage_options',
            'bkm-system-status',
            array($this, 'admin_page_system_status')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'bkm-') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
            
            // Enqueue utility JavaScript first
            wp_enqueue_script(
                'bkm-utils-js',
                BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/bkm-utils.js',
                array('jquery'),
                BKM_AKSIYON_TAKIP_VERSION,
                true
            );
            
            wp_enqueue_script(
                'bkm-admin-js',
                BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-datepicker', 'bkm-utils-js'),
                BKM_AKSIYON_TAKIP_VERSION,
                true
            );
            
            wp_enqueue_style(
                'bkm-admin-css',
                BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BKM_AKSIYON_TAKIP_VERSION
            );
            
            wp_localize_script('bkm-admin-js', 'bkmAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bkm_ajax_nonce')
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles - Improved with better error handling
     */
    public function frontend_enqueue_scripts() {
        // Only load on our pages
        if (!$this->should_load_frontend_scripts()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Enqueue utility JavaScript first
        wp_enqueue_script(
            'bkm-utils-js',
            BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/bkm-utils.js',
            array('jquery'),
            BKM_AKSIYON_TAKIP_VERSION,
            true
        );
        
        wp_enqueue_script(
            'bkm-frontend-js',
            BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'bkm-utils-js'),
            BKM_AKSIYON_TAKIP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'bkm-frontend-css',
            BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BKM_AKSIYON_TAKIP_VERSION,
            'all'
        );
        
        // CSS'leri yüksek priority ile ekle ve tema çakışmalarını önle
        wp_add_inline_style('bkm-frontend-css', '
            /* BKM Plugin CSS Priority Fix - Ultra Modern Design Protection */
            .bkm-frontend-container { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
                background: #f8f9fa !important;
            }
            
            /* Modern Settings Container Protection */
            .bkm-settings-container * {
                box-sizing: border-box !important;
            }
            
            .bkm-settings-container .bkm-management-form,
            .bkm-settings-container .bkm-management-list {
                background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%) !important;
                border-radius: 16px !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
            }
            
            .bkm-settings-container .bkm-btn {
                border: none !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                text-decoration: none !important;
            }
            
            .bkm-settings-container .bkm-btn:hover {
                transform: translateY(-3px) !important;
                text-decoration: none !important;
            }
            
            /* Tab Protection */
            .bkm-settings-container .settings-tab {
                border: none !important;
                outline: none !important;
                background: none !important;
            }
            
            .bkm-settings-container .settings-tab.active {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: white !important;
            }
        ');
        
        // Enhanced localization with better error handling and session timeout
        wp_localize_script('bkm-frontend-js', 'bkmFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkm_frontend_nonce'),
            'current_user_id' => get_current_user_id(),
            'is_admin' => current_user_can('administrator'),
            'plugin_version' => BKM_AKSIYON_TAKIP_VERSION,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG, // For easier JS access
            'heartbeat_interval' => 60000, // 1 minute
            'session_timeout' => 1800000, // 30 minutes
            'timeout_warning' => 1500000, // 25 minutes
            'is_logged_in' => is_user_logged_in()
        ));
        
        // Add heartbeat script inline
        if (is_user_logged_in()) {
            wp_add_inline_script('bkm-frontend-js', '
                // BKM Session Timeout and Activity Tracking
                (function($) {
                    var lastActivity = Date.now();
                    var timeoutWarning = false;
                    var heartbeatInterval;
                    
                    function updateActivity() {
                        lastActivity = Date.now();
                        timeoutWarning = false;
                    }
                    
                    function sendHeartbeat() {
                        if (!bkmFrontend.is_logged_in) return;
                        
                        $.ajax({
                            url: bkmFrontend.ajax_url,
                            type: "POST",
                            data: {
                                action: "bkm_heartbeat",
                                nonce: bkmFrontend.nonce
                            },
                            success: function(response) {
                                console.log("Heartbeat sent successfully");
                            },
                            error: function() {
                                console.log("Heartbeat failed");
                            }
                        });
                    }
                    
                    function checkTimeout() {
                        var currentTime = Date.now();
                        var timeSinceActivity = currentTime - lastActivity;
                        
                        // Show warning at 25 minutes
                        if (timeSinceActivity > bkmFrontend.timeout_warning && !timeoutWarning) {
                            timeoutWarning = true;
                            if (confirm("Oturum süreniz 5 dakika içinde dolacak. Devam etmek istiyor musunuz?")) {
                                updateActivity();
                                sendHeartbeat();
                            }
                        }
                        
                        // Logout at 30 minutes
                        if (timeSinceActivity > bkmFrontend.session_timeout) {
                            alert("30 Dakika boyunca aktif olmadınız, bu yüzden çıkış yapıldı.");
                            window.location.href = window.location.href + "?timeout=1";
                        }
                    }
                    
                    $(document).ready(function() {
                        // Track user activity
                        $(document).on("click keypress mousemove scroll", updateActivity);
                        
                        // Start heartbeat
                        heartbeatInterval = setInterval(function() {
                            sendHeartbeat();
                            checkTimeout();
                        }, bkmFrontend.heartbeat_interval);
                        
                        // Initial heartbeat
                        sendHeartbeat();
                    });
                })(jQuery);
            ');
        }
    }
    
    /**
     * Check if we should load frontend scripts
     */
    private function should_load_frontend_scripts() {
        global $post;
        
        // Load on our shortcode pages
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'aksiyon_takipx')) {
            return true;
        }
        
        // Load on admin pages
        if (is_admin()) {
            return true;
        }
        
        // Load on specific pages if needed
        return false;
    }
    
    /**
     * Main admin page
     */
    public function admin_page_main() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/main.php';
    }
    
    /**
     * Add action admin page
     */
    public function admin_page_add_action() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/add-action.php';
    }
    
    /**
     * Categories admin page
     */
    public function admin_page_categories() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/categories.php';
    }
    
    /**
     * Performance admin page
     */
    public function admin_page_performance() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/performance.php';
    }
    
    /**
     * Reports admin page
     */
    public function admin_page_reports() {
        include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/reports.php';
    }
    
    /**
     * User Activities admin page
     */
    public function admin_page_user_activities() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_user_activities_logs';
        
        // Get filter parameters
        $user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
        $activity_filter = isset($_GET['activity_type']) ? sanitize_text_field($_GET['activity_type']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // Build query
        $where_conditions = array();
        $query_params = array();
        
        if ($user_filter) {
            $where_conditions[] = "user_id = %d";
            $query_params[] = $user_filter;
        }
        
        if ($activity_filter) {
            $where_conditions[] = "activity_type = %s";
            $query_params[] = $activity_filter;
        }
        
        if ($date_from) {
            $where_conditions[] = "DATE(login_time) >= %s";
            $query_params[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = "DATE(login_time) <= %s";
            $query_params[] = $date_to;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY login_time DESC LIMIT 100";
        
        if (!empty($query_params)) {
            $activities = $wpdb->get_results($wpdb->prepare($query, $query_params));
        } else {
            $activities = $wpdb->get_results($query);
        }
        
        ?>
        <div class="wrap">
            <h1>Kullanıcı Aktiviteleri</h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="bkm-user-activities">
                    
                    <select name="user_id">
                        <option value="">Tüm Kullanıcılar</option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user) {
                            $selected = ($user_filter == $user->ID) ? 'selected' : '';
                            echo "<option value='{$user->ID}' {$selected}>{$user->display_name} ({$user->user_login})</option>";
                        }
                        ?>
                    </select>
                    
                    <select name="activity_type">
                        <option value="">Tüm Aktiviteler</option>
                        <option value="login" <?php selected($activity_filter, 'login'); ?>>Giriş</option>
                        <option value="logout" <?php selected($activity_filter, 'logout'); ?>>Çıkış</option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="Başlangıç Tarihi">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="Bitiş Tarihi">
                    
                    <input type="submit" class="button" value="Filtrele">
                    <a href="<?php echo admin_url('admin.php?page=bkm-user-activities'); ?>" class="button">Temizle</a>
                </form>
            </div>
            
            <!-- Activities Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Aktivite</th>
                        <th>Giriş Zamanı</th>
                        <th>Çıkış Zamanı</th>
                        <th>Çıkış Sebebi</th>
                        <th>Son Aktivite</th>
                        <th>IP Adresi</th>
                        <th>Süre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="8">Hiç aktivite bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $user = get_user_by('ID', $activity->user_id);
                                    echo $user ? $user->display_name . ' (' . $user->user_login . ')' : 'Bilinmeyen Kullanıcı';
                                    ?>
                                </td>
                                <td>
                                    <span class="activity-type activity-<?php echo esc_attr($activity->activity_type); ?>">
                                        <?php echo $activity->activity_type == 'login' ? 'Giriş' : 'Çıkış'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i:s', strtotime($activity->login_time)); ?></td>
                                <td>
                                    <?php 
                                    if ($activity->logout_time) {
                                        echo date('d.m.Y H:i:s', strtotime($activity->logout_time));
                                    } else {
                                        echo '<span style="color: green;">Aktif</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($activity->logout_reason) {
                                        $reasons = array(
                                            'manual' => 'Manuel',
                                            'timeout' => 'Zaman Aşımı',
                                            'new_login' => 'Yeni Giriş'
                                        );
                                        echo $reasons[$activity->logout_reason] ?? $activity->logout_reason;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($activity->last_activity) {
                                        echo date('d.m.Y H:i:s', strtotime($activity->last_activity));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($activity->ip_address); ?></td>
                                <td>
                                    <?php 
                                    if ($activity->logout_time) {
                                        $login = strtotime($activity->login_time);
                                        $logout = strtotime($activity->logout_time);
                                        $duration = $logout - $login;
                                        
                                        $hours = floor($duration / 3600);
                                        $minutes = floor(($duration % 3600) / 60);
                                        $seconds = $duration % 60;
                                        
                                        echo sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                                    } else {
                                        echo 'Devam ediyor';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .activity-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .activity-login {
            background: #d4edda;
            color: #155724;
        }
        .activity-logout {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        <?php
    }
    
    /**
     * System status admin page
     */
    public function admin_page_system_status() {
        global $wpdb;
        
        echo '<div class="wrap">';
        echo '<h1>BKM Sistem Durumu</h1>';
        
        // Database Tables Status
        echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
        echo '<h2>📊 Veritabanı Tabloları</h2>';
        
        $required_tables = array(
            'bkm_actions' => 'Aksiyonlar',
            'bkm_categories' => 'Kategoriler',
            'bkm_performances' => 'Performanslar',
            'bkm_tasks' => 'Görevler',
            'bkm_task_notes' => 'Görev Notları',
            'bkm_user_activities_logs' => 'Kullanıcı Aktiviteleri',
            'bkm_task_note_replies' => 'Not Cevapları'
        );
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Tablo</th><th>Durum</th><th>Kayıt Sayısı</th></tr></thead><tbody>';
        
        foreach ($required_tables as $table_suffix => $table_name) {
            $full_table_name = $wpdb->prefix . $table_suffix;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
            $status = $exists ? '<span style="color: green;">✅ Mevcut</span>' : '<span style="color: red;">❌ Eksik</span>';
            
            echo "<tr><td>$table_name</td><td>$status</td><td>$count</td></tr>";
        }
        
        echo '</tbody></table>';
        echo '</div>';
        
        // Data Integrity Checks
        echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
        echo '<h2>🔍 Veri Bütünlüğü Kontrolleri</h2>';
        
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        
        // Check for NULL/0 tanımlayan_id values
        $null_tanimlayan = $wpdb->get_var("SELECT COUNT(*) FROM $actions_table WHERE tanımlayan_id IS NULL OR tanımlayan_id = 0");
        
        // Check for orphaned tasks
        $orphaned_tasks = $wpdb->get_var("
            SELECT COUNT(*) FROM $tasks_table t 
            LEFT JOIN $actions_table a ON t.action_id = a.id 
            WHERE a.id IS NULL
        ");
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Kontrol</th><th>Durum</th><th>Detay</th></tr></thead><tbody>';
        
        $null_status = $null_tanimlayan == 0 ? '<span style="color: green;">✅ Temiz</span>' : '<span style="color: orange;">⚠️ Dikkat</span>';
        echo "<tr><td>NULL Tanımlayan ID'leri</td><td>$null_status</td><td>$null_tanimlayan kayıt</td></tr>";
        
        $orphaned_status = $orphaned_tasks == 0 ? '<span style="color: green;">✅ Temiz</span>' : '<span style="color: red;">❌ Problem</span>';
        echo "<tr><td>Öksüz Görevler</td><td>$orphaned_status</td><td>$orphaned_tasks kayıt</td></tr>";
        
        echo '</tbody></table>';
        echo '</div>';
        
        // Plugin Information
        echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
        echo '<h2>ℹ️ Plugin Bilgileri</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Özellik</th><th>Değer</th></tr></thead><tbody>';
        
        echo '<tr><td>Plugin Versiyon</td><td>' . BKM_AKSIYON_TAKIP_VERSION . '</td></tr>';
        echo '<tr><td>WordPress Versiyon</td><td>' . get_bloginfo('version') . '</td></tr>';
        echo '<tr><td>PHP Versiyon</td><td>' . PHP_VERSION . '</td></tr>';
        echo '<tr><td>MySQL Versiyon</td><td>' . $wpdb->db_version() . '</td></tr>';
        echo '<tr><td>Debug Modu</td><td>' . (defined('WP_DEBUG') && WP_DEBUG ? '✅ Aktif' : '❌ Deaktif') . '</td></tr>';
        
        echo '</tbody></table>';
        echo '</div>';
        
        // Quick Actions
        echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
        echo '<h2>⚡ Hızlı İşlemler</h2>';
        echo '<button type="button" class="button button-secondary" onclick="if(confirm(\'Tanımlayan ID\'lerini düzeltmek istediğinizden emin misiniz?\')) { window.location.href=\'' . admin_url('admin.php?page=bkm-system-status&fix_tanimlayan=1') . '\'; }">Tanımlayan ID\'lerini Düzelt</button>';
        echo '<button type="button" class="button button-secondary" onclick="if(confirm(\'Tabloları yeniden oluşturmak istediğinizden emin misiniz?\')) { window.location.href=\'' . admin_url('admin.php?page=bkm-system-status&recreate_tables=1') . '\'; }">Tabloları Yeniden Oluştur</button>';
        echo '</div>';
        
        // Handle quick actions
        if (isset($_GET['fix_tanimlayan']) && $_GET['fix_tanimlayan'] == '1') {
            $this->fix_tanimlayan_id_values();
            echo '<div class="notice notice-success"><p>Tanımlayan ID\'leri düzeltildi!</p></div>';
        }
        
        if (isset($_GET['recreate_tables']) && $_GET['recreate_tables'] == '1') {
            $this->check_and_create_tables();
            echo '<div class="notice notice-success"><p>Tablolar kontrol edildi ve güncellendi!</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Log user login activity
     */
    public function log_user_login($user_login, $user) {
        global $wpdb;
        
        $session_id = session_id();
        if (empty($session_id)) {
            $session_id = wp_generate_password(32, false);
        }
        
        $ip_address = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $table_name = $wpdb->prefix . 'bkm_user_activities_logs';
        
        // Close any existing open sessions for this user
        $wpdb->update(
            $table_name,
            array(
                'logout_time' => current_time('mysql'),
                'logout_reason' => 'new_login'
            ),
            array(
                'user_id' => $user->ID,
                'logout_time' => null
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        // Insert new login activity
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user->ID,
                'username' => $user_login,
                'activity_type' => 'login',
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'session_id' => $session_id,
                'login_time' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'logout_reason' => null
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Store session info for timeout check
        update_user_meta($user->ID, 'bkm_session_id', $session_id);
        update_user_meta($user->ID, 'bkm_last_activity', current_time('timestamp'));
    }
    
    /**
     * Log user logout activity
     */
    public function log_user_logout() {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) {
            return;
        }
        
        $session_id = get_user_meta($current_user->ID, 'bkm_session_id', true);
        $table_name = $wpdb->prefix . 'bkm_user_activities_logs';
        
        // Update the logout time for the current session
        $updated = $wpdb->update(
            $table_name,
            array(
                'logout_time' => current_time('mysql'),
                'logout_reason' => 'manual'
            ),
            array(
                'user_id' => $current_user->ID,
                'session_id' => $session_id,
                'logout_time' => null
            ),
            array('%s', '%s'),
            array('%d', '%s', '%s')
        );
        
        // Clean up session meta
        delete_user_meta($current_user->ID, 'bkm_session_id');
        delete_user_meta($current_user->ID, 'bkm_last_activity');
    }
    
    /**
     * Check for session timeout
     */
    public function check_session_timeout() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        $last_activity = get_user_meta($current_user->ID, 'bkm_last_activity', true);
        
        if (!$last_activity) {
            // Set initial activity time if not set
            update_user_meta($current_user->ID, 'bkm_last_activity', current_time('timestamp'));
            return;
        }
        
        $timeout_duration = 30 * 60; // 30 minutes in seconds
        $current_time = current_time('timestamp');
        
        if (($current_time - $last_activity) > $timeout_duration) {
            // Session has timed out
            $this->force_logout_with_message('30 Dakika boyunca aktif olmadınız, bu yüzden çıkış yapıldı.');
            return;
        }
        
        // Update last activity time
        update_user_meta($current_user->ID, 'bkm_last_activity', $current_time);
    }
    
    /**
     * Force logout with timeout message
     */
    public function force_logout_with_message($message) {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->ID) {
            $session_id = get_user_meta($current_user->ID, 'bkm_session_id', true);
            $table_name = $wpdb->prefix . 'bkm_user_activities_logs';
            
            // Log the timeout logout
            $wpdb->update(
                $table_name,
                array(
                    'logout_time' => current_time('mysql'),
                    'logout_reason' => 'timeout'
                ),
                array(
                    'user_id' => $current_user->ID,
                    'session_id' => $session_id,
                    'logout_time' => null
                ),
                array('%s', '%s'),
                array('%d', '%s', '%s')
            );
        }
        
        // Clear all authentication
        wp_clear_auth_cookie();
        wp_set_current_user(0);
        
        // Store timeout message
        set_transient('bkm_timeout_message', $message, 60);
        
        // Redirect to login page
        wp_safe_redirect($this->get_current_page_url() . '?timeout=1');
        exit;
    }
    
    /**
     * AJAX heartbeat to update last activity
     */
    public function ajax_heartbeat() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $current_user = wp_get_current_user();
        update_user_meta($current_user->ID, 'bkm_last_activity', current_time('timestamp'));
        
        wp_send_json_success(array(
            'last_activity' => current_time('timestamp'),
            'message' => 'Activity updated'
        ));
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Handle shortcode
     */
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(), $atts, 'aksiyon_takipx');
        
        // Enqueue frontend scripts when shortcode is used
        $this->frontend_enqueue_scripts();
        
        ob_start();
        
        // Check for timeout message
        $timeout_message = get_transient('bkm_timeout_message');
        if ($timeout_message && isset($_GET['timeout'])) {
            echo '<div class="bkm-timeout-alert" style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 8px; margin: 20px 0; text-align: center; font-weight: 500;">';
            echo '<i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>';
            echo esc_html($timeout_message);
            echo '</div>';
            delete_transient('bkm_timeout_message');
        }
        
        // Check for login error message
        $login_error = get_transient('bkm_login_error');
        if ($login_error) {
            echo '<div class="bkm-login-error" style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 8px; margin: 20px 0; text-align: center;">';
            echo '<i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>';
            echo esc_html($login_error);
            echo '</div>';
            delete_transient('bkm_login_error');
        }
        
        if (!is_user_logged_in()) {
            include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/login.php';
        } else {
            include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/dashboard.php';
        }
        return ob_get_clean();
    }

/**
 * Send email notification
 */
public function send_email_notification($type, $data) {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $subject = '';
    $content_html = '';
    $to_emails = array($admin_email);
    global $wpdb;

    switch ($type) {
        case 'action_created':
        case 'action_updated':
        case 'action_completed': {
            // Aksiyon detaylarını tek sorgu ile çek (optimized with JOIN)
            $action_data = $wpdb->get_row($wpdb->prepare("
                SELECT a.*, c.name as kategori_name, p.name as performans_name
                FROM {$wpdb->prefix}bkm_actions a
                LEFT JOIN {$wpdb->prefix}bkm_categories c ON a.kategori_id = c.id
                LEFT JOIN {$wpdb->prefix}bkm_performances p ON a.performans_id = p.id
                WHERE a.id = %d
            ", $data['id']));
            
            if (!$action_data) {
                error_log("❌ Action not found for ID: " . $data['id']);
                return false;
            }
            
            $tanımlayan = get_user_by('ID', $action_data->tanımlayan_id);
            $sorumlu_ids = explode(',', $action_data->sorumlu_ids);
            $sorumlu_names = array();
            foreach ($sorumlu_ids as $sid) {
                $u = get_user_by('ID', trim($sid));
                if ($u) $sorumlu_names[] = $u->display_name;
            }
            
            // Önem derecesi label'ı
            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek', 4 => 'Kritik');
            $onem_label = isset($priority_labels[$action_data->onem_derecesi]) ? $priority_labels[$action_data->onem_derecesi] : $action_data->onem_derecesi;
            
            $content_html = '<h3 style="color:#0073aa;">Aksiyon Detayları</h3>'
                .'<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
                .'<tr><td style="padding:8px 0;font-weight:bold;width:160px;">Aksiyon ID:</td><td style="padding:8px 0;">' . esc_html($action_data->id) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Başlık:</td><td style="padding:8px 0;">' . esc_html($action_data->tespit_konusu) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Açıklama:</td><td style="padding:8px 0;">' . nl2br(esc_html($action_data->aciklama)) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Kategori:</td><td style="padding:8px 0;">' . esc_html($action_data->kategori_name ?: 'Belirtilmemiş') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Tanımlayan:</td><td style="padding:8px 0;">' . ($tanımlayan ? esc_html($tanımlayan->display_name) : '-') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Sorumlular:</td><td style="padding:8px 0;">' . implode(', ', $sorumlu_names) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Önem Derecesi:</td><td style="padding:8px 0;">' . esc_html($onem_label) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Performans:</td><td style="padding:8px 0;">' . esc_html($action_data->performans_name ?: 'Belirtilmemiş') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Açılma Tarihi:</td><td style="padding:8px 0;">' . esc_html($action_data->acilma_tarihi) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Hedef Tarih:</td><td style="padding:8px 0;">' . esc_html($action_data->hedef_tarih) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Kapanma Tarihi:</td><td style="padding:8px 0;">' . esc_html($action_data->kapanma_tarihi) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">İlerleme Durumu:</td><td style="padding:8px 0;">' . esc_html($action_data->ilerleme_durumu) . '%</td></tr>'
                .'</table>';
            $subject = sprintf('[%s] Aksiyon: %s', $site_name, $type === 'action_created' ? 'Oluşturuldu' : ($type === 'action_updated' ? 'Güncellendi' : 'Tamamlandı'));
            break;
        }
        case 'task_created':
        case 'task_updated':
        case 'task_completed': {
            // Optimize task and action data retrieval with single JOIN query
            $task_data = $wpdb->get_row($wpdb->prepare("
                SELECT t.*, a.tespit_konusu as action_title, c.name as kategori_name
                FROM {$wpdb->prefix}bkm_tasks t
                LEFT JOIN {$wpdb->prefix}bkm_actions a ON t.action_id = a.id
                LEFT JOIN {$wpdb->prefix}bkm_categories c ON a.kategori_id = c.id
                WHERE t.id = %d
            ", $data['task_id'] ?? 0));
            
            if (!$task_data) {
                error_log("❌ Task not found for ID: " . ($data['task_id'] ?? 0));
                return false;
            }
            
            $sorumlu = get_user_by('ID', $task_data->sorumlu_id);
            $content_html = '<h3 style="color:#0073aa;">Görev Detayları</h3>'
                .'<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
                .'<tr><td style="padding:8px 0;font-weight:bold;width:160px;">Görev ID:</td><td style="padding:8px 0;">' . esc_html($task_data->id) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">İçerik:</td><td style="padding:8px 0;">' . esc_html($task_data->content) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Açıklama:</td><td style="padding:8px 0;">' . nl2br(esc_html($task_data->description)) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Sorumlu:</td><td style="padding:8px 0;">' . ($sorumlu ? esc_html($sorumlu->display_name) : '-') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Başlangıç Tarihi:</td><td style="padding:8px 0;">' . esc_html($task_data->baslangic_tarihi) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Hedef Tarih:</td><td style="padding:8px 0;">' . esc_html($task_data->hedef_bitis_tarihi) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">İlerleme:</td><td style="padding:8px 0;">' . esc_html($task_data->ilerleme_durumu) . '%</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Aksiyon:</td><td style="padding:8px 0;">' . esc_html($task_data->action_title ?: 'Belirtilmemiş') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Kategori:</td><td style="padding:8px 0;">' . esc_html($task_data->kategori_name ?: 'Belirtilmemiş') . '</td></tr>'
                .'</table>';
            $subject = sprintf('[%s] Görev: %s', $site_name, $type === 'task_created' ? 'Oluşturuldu' : ($type === 'task_updated' ? 'Güncellendi' : 'Tamamlandı'));
            break;
        }
        case 'note_added':
        case 'note_replied': {
            // Optimize note, task and action data retrieval with single JOIN query
            $note_data = $wpdb->get_row($wpdb->prepare("
                SELECT t.*, a.tespit_konusu as action_title, c.name as kategori_name
                FROM {$wpdb->prefix}bkm_tasks t
                LEFT JOIN {$wpdb->prefix}bkm_actions a ON t.action_id = a.id
                LEFT JOIN {$wpdb->prefix}bkm_categories c ON a.kategori_id = c.id
                WHERE t.id = %d
            ", $data['task_id'] ?? 0));
            
            if (!$note_data) {
                error_log("❌ Task not found for note, ID: " . ($data['task_id'] ?? 0));
                return false;
            }
            
            $sorumlu = get_user_by('ID', $note_data->sorumlu_id);
            $content_html = '<h3 style="color:#0073aa;">Not Detayları</h3>'
                .'<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
                .'<tr><td style="padding:8px 0;font-weight:bold;width:160px;">Not:</td><td style="padding:8px 0;">' . nl2br(esc_html($data['content'])) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Ekleyen:</td><td style="padding:8px 0;">' . esc_html($data['sorumlu']) . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Görev:</td><td style="padding:8px 0;">' . esc_html($note_data->content ?: 'Belirtilmemiş') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Aksiyon:</td><td style="padding:8px 0;">' . esc_html($note_data->action_title ?: 'Belirtilmemiş') . '</td></tr>'
                .'<tr><td style="padding:8px 0;font-weight:bold;">Kategori:</td><td style="padding:8px 0;">' . esc_html($note_data->kategori_name ?: 'Belirtilmemiş') . '</td></tr>'
                .'</table>';
            $subject = sprintf('[%s] Not: %s', $site_name, $type === 'note_added' ? 'Eklendi' : 'Cevaplandı');
            break;
        }
        default:
            $content_html = '<p>Olay detayları bulunamadı.</p>';
            $subject = '[' . $site_name . '] Bildirim';
    }

    // Alıcıları belirle
    if (isset($data['sorumlu_emails']) && is_array($data['sorumlu_emails'])) {
        $to_emails = array_unique(array_merge($to_emails, $data['sorumlu_emails']));
    }
    if (isset($data['tanımlayan_email']) && !empty($data['tanımlayan_email'])) {
        $to_emails[] = $data['tanımlayan_email'];
    }
    $to_emails = array_unique($to_emails);

    // HTML e-posta gönder
    foreach ($to_emails as $to) {
        bkm_send_html_email($to, $subject, $content_html);
    }
}

// AJAX Handler Functions - Fully Implemented

// User Management Functions
public function ajax_add_user() {
    global $wpdb;
    
    // Check permissions
    if (!current_user_can('administrator')) {
        wp_die('Yetkiniz yok.');
    }
    
    $username = sanitize_text_field($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = sanitize_text_field($_POST['password']);
    $role = sanitize_text_field($_POST['role']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error('Kullanıcı oluşturulamadı: ' . $user_id->get_error_message());
    }
    
    // Update user meta
    wp_update_user(array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role
    ));
    
    // Send welcome email
    $this->send_welcome_email(get_user_by('id', $user_id), $password);
    
    wp_send_json_success('Kullanıcı başarıyla oluşturuldu.');
}

public function ajax_edit_user() {
    global $wpdb;
    
    if (!current_user_can('administrator')) {
        wp_die('Yetkiniz yok.');
    }
    
    $user_id = intval($_POST['user_id']);
    $email = sanitize_email($_POST['email']);
    $role = sanitize_text_field($_POST['role']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    
    $result = wp_update_user(array(
        'ID' => $user_id,
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role
    ));
    
    if (is_wp_error($result)) {
        wp_send_json_error('Kullanıcı güncellenemedi: ' . $result->get_error_message());
    }
    
    wp_send_json_success('Kullanıcı başarıyla güncellendi.');
}

public function ajax_delete_user() {
    if (!current_user_can('administrator')) {
        wp_die('Yetkiniz yok.');
    }
    
    $user_id = intval($_POST['user_id']);
    
    if ($user_id == get_current_user_id()) {
        wp_send_json_error('Kendi kullanıcınızı silemezsiniz.');
    }
    
    $result = wp_delete_user($user_id);
    
    if (!$result) {
        wp_send_json_error('Kullanıcı silinemedi.');
    }
    
    wp_send_json_success('Kullanıcı başarıyla silindi.');
}

public function ajax_get_users() {
    global $wpdb;
    $users_table = $wpdb->users;
    $usermeta_table = $wpdb->usermeta;
    $cap_key = $wpdb->prefix . 'capabilities';
    $sql = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered, um.meta_value as capabilities
            FROM $users_table u
            INNER JOIN $usermeta_table um ON u.ID = um.user_id
            WHERE um.meta_key = '$cap_key'";
    $results = $wpdb->get_results($sql);
    $user_data = array();
    foreach ($results as $row) {
        $roles = array();
        $caps = maybe_unserialize($row->capabilities);
        if (is_array($caps)) {
            $roles = array_keys(array_filter($caps));
        }
        if (in_array('editor', $roles) || in_array('contributor', $roles)) {
            $user_data[] = array(
                'ID' => $row->ID,
                'user_login' => $row->user_login,
                'user_email' => $row->user_email,
                'display_name' => $row->display_name,
                'roles' => $roles,
                'role_name' => implode(', ', $roles),
                'user_registered' => $row->user_registered,
                'registration_date' => date('d.m.Y', strtotime($row->user_registered)),
                'first_name' => get_user_meta($row->ID, 'first_name', true),
                'last_name' => get_user_meta($row->ID, 'last_name', true),
            );
        }
    }
    wp_send_json_success(array('users' => $user_data));
}

// Category Management Functions
public function ajax_add_category() {
    global $wpdb;
    
    $name = sanitize_text_field($_POST['name']);
    $description = sanitize_textarea_field($_POST['description']);
    
    $table_name = $wpdb->prefix . 'bkm_categories';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'description' => $description,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        ),
        array('%s', '%s', '%s', '%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Kategori eklenemedi.');
    }
    
    wp_send_json_success('Kategori başarıyla eklendi.');
}

public function ajax_edit_category() {
    global $wpdb;
    
    $id = intval($_POST['id']);
    $name = sanitize_text_field($_POST['name']);
    $description = sanitize_textarea_field($_POST['description']);
    
    $table_name = $wpdb->prefix . 'bkm_categories';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'name' => $name,
            'description' => $description,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Kategori güncellenemedi.');
    }
    
    wp_send_json_success('Kategori başarıyla güncellendi.');
}

public function ajax_delete_category() {
    global $wpdb;
    
    $id = intval($_POST['id']);
    $table_name = $wpdb->prefix . 'bkm_categories';
    
    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
    
    if ($result === false) {
        wp_send_json_error('Kategori silinemedi.');
    }
    
    wp_send_json_success('Kategori başarıyla silindi.');
}

public function ajax_get_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bkm_categories';
    $categories = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    wp_send_json_success(['categories' => $categories]);
}

// Performance Management Functions
public function ajax_add_performance() {
    global $wpdb;
    
    $name = sanitize_text_field($_POST['name']);
    $value = isset($_POST['value']) ? floatval($_POST['value']) : 0;
    $description = sanitize_textarea_field($_POST['description']);
    
    $table_name = $wpdb->prefix . 'bkm_performances';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'value' => $value,
            'description' => $description,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        ),
        array('%s', '%f', '%s', '%s', '%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Performans eklenemedi.');
    }
    
    wp_send_json_success('Performans başarıyla eklendi.');
}

public function ajax_edit_performance() {
    global $wpdb;
    
    $id = intval($_POST['id']);
    $name = sanitize_text_field($_POST['name']);
    $value = isset($_POST['value']) ? floatval($_POST['value']) : 0;
    $description = sanitize_textarea_field($_POST['description']);
    
    $table_name = $wpdb->prefix . 'bkm_performances';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'name' => $name,
            'value' => $value,
            'description' => $description,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $id),
        array('%s', '%f', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Performans güncellenemedi.');
    }
    
    wp_send_json_success('Performans başarıyla güncellendi.');
}

public function ajax_delete_performance() {
    global $wpdb;
    
    $id = intval($_POST['id']);
    $table_name = $wpdb->prefix . 'bkm_performances';
    
    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
    
    if ($result === false) {
        wp_send_json_error('Performans silinemedi.');
    }
    
    wp_send_json_success('Performans başarıyla silindi.');
}

public function ajax_get_performances() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bkm_performances';
    $performances = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    wp_send_json_success(['performances' => $performances]);
}

// Action and Task List Functions
public function ajax_get_actions() {
    global $wpdb;
    
    // Check if WordPress database is available
    if (!$wpdb) {
        error_log('❌ BKM Error: WordPress database object not available in ajax_get_actions');
        wp_send_json_error('Veritabanı bağlantısı mevcut değil.');
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Giriş yapmalısınız.');
    }
    
    // Consistent user role checking function to avoid discrepancies
    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;
    $user_roles = $current_user->roles;
    
    // Force array if user_roles is not an array
    if (!is_array($user_roles)) {
        $user_roles = array();
    }
    
    // More robust role checking
    $is_admin = in_array('administrator', $user_roles) || current_user_can('manage_options');
    $is_editor = in_array('editor', $user_roles) || current_user_can('edit_others_posts');
    
    $actions_table = $wpdb->prefix . 'bkm_actions';
    $categories_table = $wpdb->prefix . 'bkm_categories';
    $performance_table = $wpdb->prefix . 'bkm_performances';
    
    // Check if actions table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$actions_table'");
    if (!$table_exists) {
        error_log("❌ BKM Error: Table $actions_table does not exist");
        wp_send_json_error("Aksiyonlar tablosu bulunamadı. Lütfen plugin'i yeniden aktifleştirin.");
        return;
    }
    
    // Debug: Same logic as dashboard.php
    $debug_show_all_actions = defined('BKM_DEBUG_SHOW_ALL_ACTIONS') && BKM_DEBUG_SHOW_ALL_ACTIONS;
    
    // DEBUG: Log user role and permissions for troubleshooting
    bkm_debug_log('🎯 AJAX get_actions - User ID: ' . $current_user_id . ', Roles: ' . implode(',', $user_roles));
    bkm_debug_log('🔍 manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO') . ', edit_others_posts: ' . (current_user_can('edit_others_posts') ? 'YES' : 'NO'));
    bkm_debug_log('🔍 Admin: ' . ($is_admin ? 'YES' : 'NO') . ', Editor: ' . ($is_editor ? 'YES' : 'NO') . ', Debug Mode: ' . ($debug_show_all_actions ? 'YES' : 'NO'));
    
    if ($debug_show_all_actions || $is_admin || $is_editor) {
        // Admins and editors (and debug mode) see all actions
        $actions_query = "SELECT a.*, 
                                COALESCE(u.display_name, 'Bilinmiyor') as tanımlayan_name,
                                c.name as kategori_name,
                                p.name as performans_name
                         FROM $actions_table a
                         LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
                         LEFT JOIN $categories_table c ON a.kategori_id = c.id
                         LEFT JOIN $performance_table p ON a.performans_id = p.id
                         ORDER BY a.created_at DESC";
        bkm_debug_log('📋 AJAX - Admin/Editor sorgusu kullanılıyor');

    } else {
        // Non-admins see actions they created OR are responsible for
        $actions_query = $wpdb->prepare(
            "SELECT a.*, 
                    COALESCE(u.display_name, 'Bilinmiyor') as tanımlayan_name,
                    c.name as kategori_name,
                    p.name as performans_name
             FROM $actions_table a
             LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
             LEFT JOIN $categories_table c ON a.kategori_id = c.id
             LEFT JOIN $performance_table p ON a.performans_id = p.id
             WHERE (a.tanımlayan_id = %d OR a.sorumlu_ids LIKE %s)
             ORDER BY a.created_at DESC",
            $current_user_id,
            '%' . $wpdb->esc_like($current_user_id) . '%'
        );
        bkm_debug_log('📋 AJAX - Kullanıcı kısıtlı sorgu kullanılıyor');

    }
    
    $actions = $wpdb->get_results($actions_query);
    
    // Debug logging
    bkm_debug_log('📊 AJAX - Bulunan aksiyon sayısı: ' . count($actions));
    if (count($actions) > 0) {
        $latest_action = $actions[0];
        bkm_debug_log('📋 Latest action: ID=' . $latest_action->id . ', Tanımlayan=' . $latest_action->tanımlayan_id . ', Created=' . $latest_action->created_at);
    }
    
    // Her action için task count'u ekle
    $tasks_table = $wpdb->prefix . 'bkm_tasks';
    foreach ($actions as $action) {
        $task_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tasks_table WHERE action_id = %d",
            $action->id
        ));
        $action->task_count = (int)$task_count;
    }
    
    wp_send_json_success($actions);
}

public function ajax_get_tasks() {
    global $wpdb;
    
    // Clean up excessive debug logging but keep essential ones
    error_log('🔧 ajax_get_tasks called for action_id: ' . ($_POST['action_id'] ?? 'not_set'));
    
    // Check if WordPress database is available
    if (!$wpdb) {
        error_log('❌ BKM Error: WordPress database object not available in ajax_get_tasks');
        wp_send_json_error('Veritabanı bağlantısı mevcut değil.');
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        error_log('❌ User not logged in');
        wp_send_json_error('Giriş yapmalısınız.');
        return;
    }
    
    // Verify nonce for security (temporarily more lenient for debugging)
    if (isset($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            error_log('❌ Invalid nonce in ajax_get_tasks. Provided: ' . $_POST['nonce']);
            wp_send_json_error('Güvenlik kontrolü başarısız. Lütfen sayfayı yenileyin.');
            return;
        }
    } else {
        error_log('⚠️ No nonce provided in ajax_get_tasks');
        // For debugging, allow to continue
    }
    
    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;
    $user_roles = $current_user->roles;
    $is_admin = in_array('administrator', $user_roles) || current_user_can('manage_options');
    $is_editor = in_array('editor', $user_roles) || current_user_can('edit_others_posts');
    
    $action_id = isset($_POST['action_id']) ? intval($_POST['action_id']) : 0;
    
    if ($action_id <= 0) {
        error_log('❌ Invalid action_id in ajax_get_tasks: ' . $action_id);
        wp_send_json_error('Geçersiz aksiyon ID: ' . $action_id);
        return;
    }
    
    $table_name = $wpdb->prefix . 'bkm_tasks';
    $actions_table = $wpdb->prefix . 'bkm_actions';
    
    // Check if tasks table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        error_log("❌ BKM Error: Table $table_name does not exist");
        wp_send_json_error("Görevler tablosu bulunamadı. Lütfen plugin'i yeniden aktifleştirin.");
        return;
    }
    
    // Initialize tasks array
    $tasks = array();
    
    try {
        // Check if the action exists and user has access
        $action_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $actions_table WHERE id = %d", $action_id));
        
        if (!$action_exists) {
            wp_send_json_error('Belirtilen aksiyon bulunamadı.');
            return;
        }
        
        // Check user access to this action
        if (!($is_admin || $is_editor)) {
            $action_access = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $actions_table WHERE id = %d AND (tanımlayan_id = %d OR sorumlu_ids LIKE %s)",
                $action_id,
                $current_user_id,
                '%' . $wpdb->esc_like($current_user_id) . '%'
            ));
            
            if ($action_access === 0) {
                wp_send_json_error('Bu aksiyonun görevlerini görme yetkiniz yok.');
                return;
            }
        }
        
        // Query all tasks for this action (no permission filtering on tasks themselves)
        $query = $wpdb->prepare(
            "SELECT t.id, t.action_id, 
                    COALESCE(t.content, t.title, 'Görev') as content,
                    t.content as description,
                    t.baslangic_tarihi, t.hedef_bitis_tarihi, t.gercek_bitis_tarihi,
                    t.ilerleme_durumu, t.tamamlandi, t.sorumlu_id, t.created_at,
                    CASE 
                        WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                        THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                        ELSE COALESCE(u.display_name, 'Belirtilmemiş')
                    END as sorumlu_name 
             FROM $table_name t 
             LEFT JOIN {$wpdb->users} u ON t.sorumlu_id = u.ID 
             LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
             WHERE t.action_id = %d 
             ORDER BY t.created_at DESC",
            $action_id
        );
        
        error_log("🔍 Executing query: " . $query);
        $tasks = $wpdb->get_results($query);
        
        // Check for database errors
        if ($wpdb->last_error) {
            error_log("❌ Database error: " . $wpdb->last_error);
            wp_send_json_error('Veritabanı hatası: ' . $wpdb->last_error);
        }
        
        error_log("🔍 Query executed. Found " . count($tasks) . " tasks");
        
        // Log each task for debugging
        foreach ($tasks as $task) {
            error_log("📋 Task ID: {$task->id}, Content: '{$task->content}', Sorumlu: '{$task->sorumlu_name}', Progress: {$task->ilerleme_durumu}%");
        }
        
        // Ensure tasks is always an array
        if (!is_array($tasks)) {
            $tasks = array();
        }
        
        // Additional debug information
        if (empty($tasks)) {
            // Check total tasks in system
            $total_tasks_system = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            error_log("🔍 No tasks found for action $action_id. Total tasks in system: $total_tasks_system");
            
            // Check if any tasks exist for this specific action (simpler query)
            $simple_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE action_id = %d", $action_id));
            error_log("🔍 Simple count query result: $simple_count");
        }
        
    } catch (Exception $e) {
        error_log("❌ BKM Task Loading Error: " . $e->getMessage());
        wp_send_json_error('Görevler yüklenirken bir hata oluştu: ' . $e->getMessage());
        return;
    }
    
    error_log("✅ Returning " . count($tasks) . " tasks to frontend for action $action_id");
    
    // Ensure proper JSON response format
    wp_send_json_success(array(
        'tasks' => $tasks,
        'count' => count($tasks),
        'action_id' => $action_id
    ));
}

// Complete task AJAX handler
public function ajax_complete_task() {
    global $wpdb;
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Giriş yapmalısınız.');
        return;
    }
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız. Lütfen sayfayı yenileyin.');
        return;
    }
    
    $current_user = wp_get_current_user();
    $current_user_id = $current_user->ID;
    $user_roles = $current_user->roles;
    $is_admin = in_array('administrator', $user_roles) || current_user_can('manage_options');
    $is_editor = in_array('editor', $user_roles) || current_user_can('edit_others_posts');
    
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    
    if ($task_id <= 0) {
        wp_send_json_error('Geçersiz görev ID.');
        return;
    }
    
    $tasks_table = $wpdb->prefix . 'bkm_tasks';
    $actions_table = $wpdb->prefix . 'bkm_actions';
    
    // Get task details
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, a.tanımlayan_id, a.sorumlu_ids 
         FROM $tasks_table t 
         LEFT JOIN $actions_table a ON t.action_id = a.id 
         WHERE t.id = %d", 
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error('Görev bulunamadı.');
        return;
    }
    
    // Check if user has permission to complete this task
    $can_complete = false;
    
    if ($is_admin || $is_editor) {
        $can_complete = true;
    } else {
        // User can complete if they are responsible for the task OR the action
        if ($task->sorumlu_id == $current_user_id || 
            $task->tanımlayan_id == $current_user_id || 
            (strpos($task->sorumlu_ids, (string)$current_user_id) !== false)) {
            $can_complete = true;
        }
    }
    
    if (!$can_complete) {
        wp_send_json_error('Bu görevi tamamlama yetkiniz yok.');
        return;
    }
    
    // Check if task is already completed
    if ($task->tamamlandi == 1) {
        wp_send_json_error('Bu görev zaten tamamlanmış.');
        return;
    }
    
    // Update task as completed
    $result = $wpdb->update(
        $tasks_table,
        array(
            'tamamlandi' => 1,
            'ilerleme_durumu' => 100,
            'gercek_bitis_tarihi' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ),
        array('id' => $task_id),
        array('%d', '%d', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        error_log("❌ Task completion failed for task $task_id: " . $wpdb->last_error);
        wp_send_json_error('Görev tamamlanırken veritabanı hatası oluştu.');
        return;
    }
    
    error_log("✅ Task $task_id marked as completed by user $current_user_id");
    
    // Update parent action progress based on all tasks
    $new_action_progress = $this->update_action_progress_from_tasks($task_id);
    
    wp_send_json_success(array(
        'message' => 'Görev başarıyla tamamlandı.',
        'task_id' => $task_id,
        'action_id' => $task->action_id,
        'action_progress_updated' => $new_action_progress !== false,
        'new_action_progress' => $new_action_progress
    ));
}

// Action AJAX handlers
public function ajax_add_action() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    global $wpdb;
    
    // Check if WordPress database is available
    if (!$wpdb) {
        error_log('❌ BKM Error: WordPress database object not available in ajax_add_action');
        wp_send_json_error('Veritabanı bağlantısı mevcut değil.');
        return;
    }
    
    // Check if actions table exists
    $actions_table = $wpdb->prefix . 'bkm_actions';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$actions_table'");
    if (!$table_exists) {
        error_log("❌ BKM Error: Table $actions_table does not exist in ajax_add_action");
        wp_send_json_error("Aksiyonlar tablosu bulunamadı. Lütfen plugin'i yeniden aktifleştirin.");
        return;
    }
    
    // Map form fields to correct values
    $category_id = intval($_POST['kategori_id'] ?? $_POST['category_id'] ?? 0);
    $performance_id = intval($_POST['performans_id'] ?? $_POST['performance_id'] ?? 1);
    $onem_derecesi = intval($_POST['onem_derecesi'] ?? $_POST['priority'] ?? 1);
    $tespit_konusu = sanitize_textarea_field($_POST['tespit_konusu'] ?? $_POST['title'] ?? '');
    $aciklama = sanitize_textarea_field($_POST['aciklama'] ?? $_POST['description'] ?? '');
    $hedef_tarih = sanitize_text_field($_POST['hedef_tarih'] ?? $_POST['target_date'] ?? '');
    $sorumlu_ids = isset($_POST['sorumlu_ids']) ? $_POST['sorumlu_ids'] : (isset($_POST['responsible']) ? $_POST['responsible'] : '');
    
    // Validation
    if (empty($tespit_konusu) || empty($aciklama) || empty($hedef_tarih) || $category_id <= 0) {
        wp_send_json_error('Lütfen tüm zorunlu alanları doldurun.');
    }
    
    // Validate tanımlayan_id (current user)
    $tanımlayan_id = get_current_user_id();
    if ($tanımlayan_id <= 0) {
        // If no valid user, try to get the first admin user as fallback
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $tanımlayan_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
    }
    
    // Debug logging
    bkm_debug_log('🎯 Action ekleniyor - User ID: ' . $tanımlayan_id . ', Sorumlu IDs: ' . $sorumlu_ids);
    
    // If sorumlu_ids is array, convert to string
    if (is_array($sorumlu_ids)) {
        $sorumlu_ids = implode(',', array_map('intval', $sorumlu_ids));
    } else {
        $sorumlu_ids = sanitize_text_field($sorumlu_ids);
    }
    
    // Backward compatibility
    $title = $tespit_konusu;
    $description = $aciklama;
    $priority = ($onem_derecesi == 4 ? 'critical' : ($onem_derecesi == 3 ? 'high' : ($onem_derecesi == 2 ? 'normal' : 'low')));
    $start_date = current_time('Y-m-d');
    $target_date = $hedef_tarih;
    $responsible = $sorumlu_ids;
    
    $table_name = $wpdb->prefix . 'bkm_actions';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'category_id' => $category_id,
            'kategori_id' => $category_id, // Legacy field
            'title' => $title,
            'tespit_konusu' => $tespit_konusu, // Legacy field
            'description' => $description,
            'aciklama' => $aciklama, // Legacy field
            'priority' => $priority,
            'start_date' => $start_date,
            'acilma_tarihi' => $start_date, // Legacy field
            'target_date' => $target_date,
            'hedef_tarih' => $hedef_tarih, // Legacy field
            'responsible' => $responsible,
            'sorumlu_ids' => $sorumlu_ids, // Legacy field
            'status' => 'open',
            'tanımlayan_id' => $tanımlayan_id,
            'onem_derecesi' => $onem_derecesi,
            'performans_id' => $performance_id,
            'ilerleme_durumu' => 0,
            'hafta' => date('W'),
            'created_at' => current_time('mysql'),
            'created_by' => $tanımlayan_id
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d')
    );
    
    if ($result === false) {
        bkm_debug_log('❌ Action ekleme hatası: ' . $wpdb->last_error);
        wp_send_json_error('Aksiyon eklenemedi.');
    }
    
    $action_id = $wpdb->insert_id;
    bkm_debug_log('✅ Action eklendi - ID: ' . $action_id . ', Tanımlayan: ' . $tanımlayan_id);
    
    // Get category name
    $categories_table = $wpdb->prefix . 'bkm_categories';
    $kategori = $wpdb->get_row($wpdb->prepare("SELECT name FROM $categories_table WHERE id = %d", $category_id));
    
    // Get current user (tanımlayan)
    $current_user = wp_get_current_user();
    
    // Get responsible users' emails
    $sorumlu_emails = array();
    if (!empty($sorumlu_ids)) {
        $sorumlu_user_ids = explode(',', $sorumlu_ids);
        foreach ($sorumlu_user_ids as $user_id) {
            $user = get_user_by('ID', trim($user_id));
            if ($user && !empty($user->user_email)) {
                $sorumlu_emails[] = $user->user_email;
            }
        }
    }
    
    // Send notification email
    $notification_data = array(
        'id' => $action_id,
        'tanımlayan' => $current_user->display_name,
        'kategori' => $kategori ? $kategori->name : 'Bilinmiyor',
        'aciklama' => $aciklama
    );
    
    // Add tanımlayan email
    if (!empty($current_user->user_email)) {
        $notification_data['tanımlayan_email'] = $current_user->user_email;
    }
    
    // Add sorumlu emails
    if (!empty($sorumlu_emails)) {
        $notification_data['sorumlu_emails'] = $sorumlu_emails;
    }
    
    $this->send_email_notification('action_created', $notification_data);
    
    wp_send_json_success('Aksiyon başarıyla eklendi.');
}

// Task Management Functions
public function ajax_add_task() {
    // Debug logging
    bkm_debug_log('🧪 ajax_add_task çağrıldı. POST verileri: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    global $wpdb;
    
    // Check if WordPress database is available
    if (!$wpdb) {
        error_log('❌ BKM Error: WordPress database object not available in ajax_add_task');
        wp_send_json_error('Veritabanı bağlantısı mevcut değil.');
        return;
    }
    
    // Check if tasks table exists
    $tasks_table = $wpdb->prefix . 'bkm_tasks';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$tasks_table'");
    if (!$table_exists) {
        error_log("❌ BKM Error: Table $tasks_table does not exist in ajax_add_task");
        wp_send_json_error("Görevler tablosu bulunamadı. Lütfen plugin'i yeniden aktifleştirin.");
        return;
    }
    
    // Enhanced field mapping with better fallbacks
    $action_id = intval($_POST['action_id'] ?? $_POST['aksiyon_id'] ?? 0);
    $content = sanitize_textarea_field($_POST['content'] ?? $_POST['title'] ?? $_POST['aciklama'] ?? $_POST['description'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? $_POST['aciklama'] ?? $_POST['content'] ?? '');
    $sorumlu_id = intval($_POST['sorumlu_id'] ?? $_POST['responsible_id'] ?? $_POST['responsible'] ?? 0);
    $baslangic_tarihi = sanitize_text_field($_POST['baslangic_tarihi'] ?? $_POST['start_date'] ?? current_time('Y-m-d'));
    $hedef_bitis_tarihi = sanitize_text_field($_POST['hedef_bitis_tarihi'] ?? $_POST['bitis_tarihi'] ?? $_POST['target_date'] ?? $_POST['hedef_tarih'] ?? '');
    $ilerleme_durumu = intval($_POST['ilerleme_durumu'] ?? $_POST['progress'] ?? 0);
    
    // Validate responsible user ID
    if ($sorumlu_id <= 0) {
        // Try to get current user, fallback to admin
        $sorumlu_id = get_current_user_id();
        if ($sorumlu_id <= 0) {
            $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
            $sorumlu_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
        }
    }
    
    // Validate created_by user ID
    $created_by = get_current_user_id();
    if ($created_by <= 0) {
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $created_by = !empty($admin_users) ? $admin_users[0]->ID : 1;
    }
    
    // Extra field options to handle frontend variations
    if (empty($content)) {
        $content = sanitize_textarea_field($_POST['task_content'] ?? $_POST['görev_içerik'] ?? '');
    }
    
    if (empty($hedef_bitis_tarihi)) {
        $hedef_bitis_tarihi = sanitize_text_field($_POST['deadline'] ?? $_POST['due_date'] ?? '');
    }
    
    // Debug log
    error_log("🔍 Final parsed values: action_id=$action_id, content='$content', description='$description', sorumlu_id=$sorumlu_id, hedef_bitis_tarihi='$hedef_bitis_tarihi'");
    
    // Enhanced validation with specific error messages
    if ($action_id <= 0) {
        error_log('❌ Validation failed: action_id is 0 or negative');
        wp_send_json_error('Geçersiz aksiyon ID. Lütfen geçerli bir aksiyon seçin.');
    }
    
    if (empty($content) && empty($description)) {
        error_log('❌ Validation failed: both content and description are empty');
        wp_send_json_error('Görev içeriği veya açıklama gerekli. Lütfen en az birini doldurun.');
    }
    
    if (empty($hedef_bitis_tarihi)) {
        error_log('❌ Validation failed: hedef_bitis_tarihi is empty');
        wp_send_json_error('Hedef bitiş tarihi gerekli. Lütfen tarih seçin.');
    }
    
    // Use content as title if empty, and vice versa
    if (empty($content)) {
        $content = $description;
    }
    if (empty($description)) {
        $description = $content;
    }
    
    // Backward compatibility
    $title = $content;
    $responsible = $sorumlu_id;
    $start_date = $baslangic_tarihi;
    $target_date = $hedef_bitis_tarihi;
    
    $table_name = $wpdb->prefix . 'bkm_tasks';
    error_log("🗃️ Using table: $table_name");
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'action_id' => $action_id,
            'title' => $title,
            'content' => $content, // Legacy field
            'description' => $description,
            'responsible' => $responsible,
            'sorumlu_id' => $sorumlu_id, // Legacy field
            'start_date' => $start_date,
            'baslangic_tarihi' => $baslangic_tarihi, // Legacy field
            'target_date' => $target_date,
            'hedef_bitis_tarihi' => $hedef_bitis_tarihi, // Legacy field
            'status' => 'pending',
            'progress' => $ilerleme_durumu,
            'ilerleme_durumu' => $ilerleme_durumu, // Legacy field
            'tamamlandi' => 0, // Legacy field
            'created_at' => current_time('mysql'),
            'created_by' => $created_by
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d')
    );
    
    error_log("💾 Database insert result: " . ($result ? 'SUCCESS' : 'FAILED'));
    if ($result === false) {
        error_log("❌ Database error: " . $wpdb->last_error);
        wp_send_json_error('Görev veritabanına eklenemedi: ' . $wpdb->last_error);
    }
    
    $insert_id = $wpdb->insert_id;
    error_log("✅ Task inserted with ID: $insert_id");
    
    // Send notification email
    $current_user = wp_get_current_user();
    // Görev sorumlusunun e-postası
    $sorumlu_user = get_user_by('ID', $sorumlu_id);
    $sorumlu_email = $sorumlu_user && !empty($sorumlu_user->user_email) ? $sorumlu_user->user_email : '';
    // Aksiyonun tanımlayanı ve e-postası
    $action = $wpdb->get_row($wpdb->prepare("SELECT tanımlayan_id FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $action_id));
    $tanımlayan_user = $action ? get_user_by('ID', $action->tanımlayan_id) : null;
    $tanımlayan_email = $tanımlayan_user && !empty($tanımlayan_user->user_email) ? $tanımlayan_user->user_email : '';
    $notification_data = array(
        'task_id' => $insert_id,
        'action_id' => $action_id,
        'content' => $content,
        'sorumlu' => $current_user->display_name
    );
    $notification_data['sorumlu_emails'] = array();
    if ($sorumlu_email) $notification_data['sorumlu_emails'][] = $sorumlu_email;
    if ($tanımlayan_email) $notification_data['sorumlu_emails'][] = $tanımlayan_email;
    $this->send_email_notification('task_created', $notification_data);
    
    // Update action progress after adding new task
    $response_data = array(
        'message' => 'Görev başarıyla eklendi!',
        'task_id' => $insert_id,
        'action_id' => $action_id,
        'data' => array(
            'action_id' => $action_id,
            'content' => $content,
            'sorumlu_id' => $sorumlu_id
        )
    );
    
    // Update action progress based on all tasks of this action
    $new_action_progress = $this->update_action_progress_from_tasks($insert_id);
    if ($new_action_progress !== false) {
        $response_data['action_progress_updated'] = true;
        $response_data['new_action_progress'] = $new_action_progress;
        
        // Get updated action status
        $action_info = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $action_id));
        if ($action_info) {
            $response_data['new_action_status'] = $action_info->status;
        }
        
        error_log("✅ Action progress updated after task creation: $new_action_progress%");
    } else {
        error_log("❌ Failed to update action progress after task creation");
    }
    
    wp_send_json_success($response_data);
}

public function ajax_update_task_progress() {
    global $wpdb;
    
    $task_id = intval($_POST['task_id']);
    $progress = intval($_POST['progress']);
    $status = sanitize_text_field($_POST['status']);
    
    $table_name = $wpdb->prefix . 'bkm_tasks';
    
    $update_data = array(
        'progress' => $progress,
        'updated_at' => current_time('mysql')
    );
    
    if ($status) {
        $update_data['status'] = $status;
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }
    }
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $task_id),
        array('%d', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Görev güncellenemedi.');
    }
    
    wp_send_json_success('Görev durumu güncellendi.');

    // E-posta bildirimi: Görev tamamlandıysa ilgili kişilere gönder
    $should_notify = false;
    if ($progress === 100 || $status === 'completed') {
        $should_notify = true;
    }
    if ($should_notify) {
        // Görev ve aksiyon bilgilerini al
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $task_id));
        $action = $wpdb->get_row($wpdb->prepare("SELECT tanımlayan_id FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $task->action_id));
        $tanımlayan_user = $action ? get_user_by('ID', $action->tanımlayan_id) : null;
        $tanımlayan_email = $tanımlayan_user && !empty($tanımlayan_user->user_email) ? $tanımlayan_user->user_email : '';
        $sorumlu_user = get_user_by('ID', $task->sorumlu_id);
        $sorumlu_email = $sorumlu_user && !empty($sorumlu_user->user_email) ? $sorumlu_user->user_email : '';
        $current_user = wp_get_current_user();
        $notification_data = array(
            'task_id' => $task_id,
            'action_id' => $task->action_id,
            'content' => $task->content,
            'sorumlu' => $current_user->display_name
        );
        $notification_data['sorumlu_emails'] = array();
        if ($sorumlu_email) $notification_data['sorumlu_emails'][] = $sorumlu_email;
        if ($tanımlayan_email) $notification_data['sorumlu_emails'][] = $tanımlayan_email;
        $this->send_email_notification('task_completed', $notification_data);
    }
}

/**
 * Update action progress based on its tasks' progress
 */
private function update_action_progress_from_tasks($task_id) {
    global $wpdb;
    
    // Get the action_id from task
    $task = $wpdb->get_row($wpdb->prepare("SELECT action_id FROM {$wpdb->prefix}bkm_tasks WHERE id = %d", $task_id));
    
    if (!$task || !$task->action_id) {
        return false;
    }
    
    $action_id = $task->action_id;
    
    // Get all tasks for this action
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT ilerleme_durumu FROM {$wpdb->prefix}bkm_tasks WHERE action_id = %d", 
        $action_id
    ));
    
    if (empty($tasks)) {
        return false;
    }
    
    // Calculate average progress
    $total_progress = 0;
    $task_count = count($tasks);
    
    foreach ($tasks as $task) {
        $total_progress += intval($task->ilerleme_durumu);
    }
    
    $average_progress = round($total_progress / $task_count);
    
    // Determine status based on progress
    if ($average_progress == 0) {
        $action_status = 'open';
    } elseif ($average_progress >= 1 && $average_progress <= 99) {
        $action_status = 'active';
    } elseif ($average_progress == 100) {
        $action_status = 'completed';
    } else {
        $action_status = 'active'; // fallback
    }
    
    // Update action progress and status
    $actions_table = $wpdb->prefix . 'bkm_actions';
    $result = $wpdb->update(
        $actions_table,
        array(
            'ilerleme_durumu' => $average_progress,
            'status' => $action_status
        ),
        array('id' => $action_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        error_log("✅ Action $action_id progress updated to $average_progress% and status updated to '$action_status' (calculated from $task_count tasks)");
        return $average_progress;
    } else {
        error_log("❌ Failed to update action $action_id progress. Last error: " . $wpdb->last_error);
        return false;
    }
}

// Note Management Functions
public function ajax_add_note() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    global $wpdb;
    
    // Enhanced field mapping for note adding
    $task_id = intval($_POST['task_id'] ?? $_POST['gorev_id'] ?? 0);
    $content = sanitize_textarea_field($_POST['content'] ?? $_POST['note_content'] ?? $_POST['not_icerik'] ?? $_POST['icerik'] ?? '');
    $parent_note_id = isset($_POST['parent_note_id']) ? intval($_POST['parent_note_id']) : null;
    $progress = isset($_POST['progress']) ? intval($_POST['progress']) : null;
    
    // Additional field options
    if (empty($content)) {
        $content = sanitize_textarea_field($_POST['text'] ?? $_POST['message'] ?? $_POST['aciklama'] ?? '');
    }
    
    // Debug logging
    error_log("🧪 ajax_add_note - task_id: $task_id, content: '$content', parent_note_id: $parent_note_id, progress: $progress");
    
    // Enhanced validation
    if ($task_id <= 0) {
        error_log('❌ Note validation failed: invalid task_id');
        wp_send_json_error('Geçersiz görev ID. Lütfen geçerli bir görev seçin.');
    }
    
    if (empty($content)) {
        error_log('❌ Note validation failed: empty content');
        wp_send_json_error('Not içeriği boş olamaz. Lütfen bir metin girin.');
    }
    
    // Validate user ID
    $current_user_id = get_current_user_id();
    if ($current_user_id <= 0) {
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $current_user_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
    }
    
    // Progress validation
    if ($progress !== null && ($progress < 0 || $progress > 100)) {
        wp_send_json_error('İlerleme durumu 0-100 arasında olmalıdır.');
    }
    
    $table_name = $wpdb->prefix . 'bkm_task_notes';
    
    // Insert note with progress if provided
    $current_user = get_user_by('ID', $current_user_id);
    if (!$current_user) {
        // Fallback to first admin if user not found
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $current_user = !empty($admin_users) ? $admin_users[0] : (object)array('ID' => 1, 'display_name' => 'System');
        $current_user_id = $current_user->ID;
    }
    
    // Get user's first name and last name
    $first_name = get_user_meta($current_user->ID, 'first_name', true);
    $last_name = get_user_meta($current_user->ID, 'last_name', true);
    $user_full_name = trim($first_name . ' ' . $last_name);
    
    // Fall back to display_name if first/last name is empty
    if (empty($user_full_name)) {
        $user_full_name = $current_user->display_name;
    }
    
    $note_data = array(
        'task_id' => $task_id,
        'user_id' => $current_user_id,
        'user_name' => $user_full_name,
        'content' => $content,
        'parent_note_id' => $parent_note_id,
        'created_at' => current_time('mysql')
    );
    
    $note_format = array('%d', '%d', '%s', '%s', '%d', '%s');
    
    // Add progress to note if provided
    if ($progress !== null) {
        $note_data['progress'] = $progress;
        $note_format[] = '%d';
    }
    
    $result = $wpdb->insert($table_name, $note_data, $note_format);
    
    if ($result === false) {
        wp_send_json_error('Not eklenemedi.');
    }
    
    $response_data = array('message' => 'Not başarıyla eklendi.');
    
    // Update task progress if progress is provided
    if ($progress !== null) {
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $task_update_result = $wpdb->update(
            $tasks_table,
            array('ilerleme_durumu' => $progress),
            array('id' => $task_id),
            array('%d'),
            array('%d')
        );
        
        if ($task_update_result !== false) {
            $response_data['progress_updated'] = true;
            $response_data['new_progress'] = $progress;
            
            // Get task action_id for frontend update
            $task_info = $wpdb->get_row($wpdb->prepare("SELECT action_id FROM {$wpdb->prefix}bkm_tasks WHERE id = %d", $task_id));
            if ($task_info && $task_info->action_id) {
                $response_data['action_id'] = $task_info->action_id;
                error_log("✅ Task action_id found: " . $task_info->action_id);
            } else {
                error_log("❌ Task action_id not found for task_id: $task_id");
            }
            
            // Update action progress based on task progress
            $new_action_progress = $this->update_action_progress_from_tasks($task_id);
            if ($new_action_progress !== false) {
                $response_data['action_progress_updated'] = true;
                $response_data['new_action_progress'] = $new_action_progress;
                
                // Get updated action status
                $action_info = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $task_info->action_id));
                if ($action_info) {
                    $response_data['new_action_status'] = $action_info->status;
                }
                
                error_log("✅ Action progress update successful: $new_action_progress%");
            } else {
                error_log("❌ Action progress update failed");
            }
            
            error_log("✅ Task progress updated: $progress%");
        } else {
            error_log("❌ Failed to update task progress");
        }
    }
    
    // Get task and action info for notification
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bkm_tasks WHERE id = %d", $task_id));
    $current_user = wp_get_current_user();
    // Görev sorumlusunun e-postası
    $sorumlu_user = get_user_by('ID', $task->sorumlu_id);
    $sorumlu_email = $sorumlu_user && !empty($sorumlu_user->user_email) ? $sorumlu_user->user_email : '';
    // Aksiyonun tanımlayanı ve e-postası
    $action = $wpdb->get_row($wpdb->prepare("SELECT tanımlayan_id FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $task->action_id));
    $tanımlayan_user = $action ? get_user_by('ID', $action->tanımlayan_id) : null;
    $tanımlayan_email = $tanımlayan_user && !empty($tanımlayan_user->user_email) ? $tanımlayan_user->user_email : '';
    $notification_data = array(
        'task_id' => $task_id,
        'action_id' => $task->action_id,
        'content' => $content,
        'sorumlu' => $current_user->display_name
    );
    $notification_data['sorumlu_emails'] = array();
    if ($sorumlu_email) $notification_data['sorumlu_emails'][] = $sorumlu_email;
    if ($tanımlayan_email) $notification_data['sorumlu_emails'][] = $tanımlayan_email;
    $this->send_email_notification('note_added', $notification_data);
    
    wp_send_json_success($response_data);
}

public function ajax_reply_note() {
    global $wpdb;
    
    // Use the same structure as adding a note with parent_note_id
    $task_id = intval($_POST['task_id'] ?? 0);
    $parent_note_id = intval($_POST['parent_note_id'] ?? $_POST['note_id'] ?? 0);
    $content = sanitize_textarea_field($_POST['reply'] ?? $_POST['note_content'] ?? $_POST['content'] ?? '');
    
    error_log("🧪 ajax_reply_note - task_id: $task_id, parent_note_id: $parent_note_id, content: '$content'");
    
    if ($task_id <= 0) {
        wp_send_json_error('Geçersiz görev ID.');
    }
    
    if ($parent_note_id <= 0) {
        wp_send_json_error('Geçersiz not ID.');
    }
    
    if (empty($content)) {
        wp_send_json_error('Cevap içeriği boş olamaz.');
    }
    
    // Validate user ID
    $current_user_id = get_current_user_id();
    if ($current_user_id <= 0) {
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $current_user_id = !empty($admin_users) ? $admin_users[0]->ID : 1;
    }
    
    // Insert reply as a child note
    $table_name = $wpdb->prefix . 'bkm_task_notes';
    $current_user = get_user_by('ID', $current_user_id);
    if (!$current_user) {
        // Fallback to first admin if user not found
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        $current_user = !empty($admin_users) ? $admin_users[0] : (object)array('ID' => 1, 'display_name' => 'System');
        $current_user_id = $current_user->ID;
    }
    
    // Get user's first name and last name
    $first_name = get_user_meta($current_user->ID, 'first_name', true);
    $last_name = get_user_meta($current_user->ID, 'last_name', true);
    $user_full_name = trim($first_name . ' ' . $last_name);
    
    // Fall back to display_name if first/last name is empty
    if (empty($user_full_name)) {
        $user_full_name = $current_user->display_name;
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'task_id' => $task_id,
            'user_id' => $current_user_id,
            'user_name' => $user_full_name,
            'content' => $content,
            'parent_note_id' => $parent_note_id,
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%d', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error('Cevap eklenemedi: ' . $wpdb->last_error);
    }
    
    // Get note and task info for notification
    $note = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bkm_task_notes WHERE id = %d", $parent_note_id));
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bkm_tasks WHERE id = %d", $task_id));
    $current_user = wp_get_current_user();
    // Görev sorumlusunun e-postası
    $sorumlu_user = $task ? get_user_by('ID', $task->sorumlu_id) : null;
    $sorumlu_email = $sorumlu_user && !empty($sorumlu_user->user_email) ? $sorumlu_user->user_email : '';
    // Aksiyonun tanımlayanı ve e-postası
    $action = $task ? $wpdb->get_row($wpdb->prepare("SELECT tanımlayan_id FROM {$wpdb->prefix}bkm_actions WHERE id = %d", $task->action_id)) : null;
    $tanımlayan_user = $action ? get_user_by('ID', $action->tanımlayan_id) : null;
    $tanımlayan_email = $tanımlayan_user && !empty($tanımlayan_user->user_email) ? $tanımlayan_user->user_email : '';
    $notification_data = array(
        'task_id' => $task_id,
        'action_id' => $task ? $task->action_id : 0,
        'content' => $content,
        'sorumlu' => $current_user->display_name
    );
    $notification_data['sorumlu_emails'] = array();
    if ($sorumlu_email) $notification_data['sorumlu_emails'][] = $sorumlu_email;
    if ($tanımlayan_email) $notification_data['sorumlu_emails'][] = $tanımlayan_email;
    $this->send_email_notification('note_replied', $notification_data);
    
    wp_send_json_success('Cevap başarıyla eklendi.');
}

public function ajax_get_notes() {
    // Debug logging for notes retrieval
    error_log('🧪 ajax_get_notes çağrıldı. POST verileri: ' . print_r($_POST, true));
    
    // Verify nonce for security (but don't fail on missing nonce for backward compatibility)
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
        error_log('❌ Invalid nonce in ajax_get_notes');
        wp_send_json_error('Güvenlik kontrolü başarısız oldu.');
        return;
    }
    
    global $wpdb;
    
    // Enhanced field mapping for task_id
    $task_id = intval($_POST['task_id'] ?? $_POST['gorev_id'] ?? 0);
    
    if ($task_id <= 0) {
        error_log('❌ Invalid task_id in ajax_get_notes: ' . $task_id);
        wp_send_json_error('Geçersiz görev ID: ' . $task_id);
    }
    
    $notes_table = $wpdb->prefix . 'bkm_task_notes';
    
    error_log("🗃️ Notlar için tablo: $notes_table, görev ID: $task_id");
    
    // Get all notes for this task (both main notes and replies)
    $notes = $wpdb->get_results($wpdb->prepare("
        SELECT n.*, 
               COALESCE(
                   CONCAT(
                       TRIM(COALESCE(fn.meta_value, '')), 
                       ' ', 
                       TRIM(COALESCE(ln.meta_value, ''))
                   ), 
                   u.display_name, 
                   'Bilinmeyen Kullanıcı'
               ) as user_name,
               u.user_login,
               u.user_email,
               fn.meta_value as first_name,
               ln.meta_value as last_name
        FROM $notes_table n 
        LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
        LEFT JOIN {$wpdb->usermeta} fn ON u.ID = fn.user_id AND fn.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} ln ON u.ID = ln.user_id AND ln.meta_key = 'last_name'
        WHERE n.task_id = %d 
        ORDER BY n.created_at ASC
    ", $task_id));
    
    error_log('📋 Bulunan not sayısı: ' . count($notes));
    
    if ($wpdb->last_error) {
        error_log('❌ Database error in ajax_get_notes: ' . $wpdb->last_error);
        wp_send_json_error('Veritabanı hatası: ' . $wpdb->last_error);
    }
    
    // Organize notes into hierarchical structure
    $organized_notes = array();
    $note_by_id = array();
    
    // First pass: create index by ID
    foreach ($notes as $note) {
        $note_by_id[$note->id] = $note;
        $note->replies = array();
    }
    
    // Second pass: organize hierarchy
    foreach ($notes as $note) {
        if ($note->parent_note_id && isset($note_by_id[$note->parent_note_id])) {
            // This is a reply to another note
            $note_by_id[$note->parent_note_id]->replies[] = $note;
        } else {
            // This is a main note (no parent)
            $organized_notes[] = $note;
        }
    }
    
    error_log('✅ Organized notes structure: ' . json_encode(array('notes' => $organized_notes), JSON_UNESCAPED_UNICODE));
    wp_send_json_success(array('notes' => $organized_notes));
}

public function ajax_get_task_notes() {
    $this->ajax_get_notes();
}

// System Functions
public function ajax_check_tables() {
    $this->check_and_create_tables();
    wp_send_json_success('Tablolar kontrol edildi ve gerekirse oluşturuldu.');
}

public function ajax_refresh_stats() {
    global $wpdb;
    
    $stats = array(
        'total_actions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_actions"),
        'active_actions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_actions WHERE status = 'active'"),
        'completed_actions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_actions WHERE status = 'completed'"),
        'total_tasks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_tasks"),
        'pending_tasks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_tasks WHERE status = 'pending'"),
        'in_progress_tasks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_tasks WHERE status = 'in_progress'"),
        'completed_tasks' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_tasks WHERE status = 'completed'"),
        'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}"),
        'total_categories' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_categories")
    );
    
    wp_send_json_success($stats);
}

public function ajax_delete_item() {
    global $wpdb;
    
    $type = sanitize_text_field($_POST['type']);
    $id = intval($_POST['id']);
    
    $table_map = array(
        'action' => 'bkm_actions',
        'task' => 'bkm_tasks',
        'note' => 'bkm_task_notes',
        'category' => 'bkm_categories',
        'performance' => 'bkm_performances'
    );
    
    if (!isset($table_map[$type])) {
        wp_send_json_error('Geçersiz öğe türü.');
    }
    
    $table_name = $wpdb->prefix . $table_map[$type];
    $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
    
    if ($result === false) {
        wp_send_json_error('Öğe silinemedi.');
    }
    
    wp_send_json_success('Öğe başarıyla silindi.');
}

// Company Settings Functions
public function ajax_save_company_settings() {
    // Nonce kontrolü
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    // Kullanıcı yetki kontrolü
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Bu işlemi gerçekleştirmek için yetkiniz yok.');
    }
    
    // Form verilerini kaydet
    $company_name = sanitize_text_field($_POST['company_name'] ?? '');
    $company_address = sanitize_textarea_field($_POST['company_address'] ?? '');
    $company_phone = sanitize_text_field($_POST['company_phone'] ?? '');
    $company_email = sanitize_email($_POST['company_email'] ?? '');
    
    update_option('bkm_company_name', $company_name);
    update_option('bkm_company_address', $company_address);
    update_option('bkm_company_phone', $company_phone);
    update_option('bkm_company_email', $company_email);
    
    $messages = array('Firma bilgileri kaydedildi.');
    $logo_url = get_option('bkm_company_logo', '');
    
    // Logo upload işlemi (eğer dosya gönderildiyse)
    if (!empty($_FILES['company_logo']) && $_FILES['company_logo']['size'] > 0) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Dosya türü ve boyut kontrolü
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        $file_type = $_FILES['company_logo']['type'];
        $file_size = $_FILES['company_logo']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error('Sadece JPG, PNG ve GIF formatları desteklenmektedir.');
        }
        
        if ($file_size > 2 * 1024 * 1024) { // 2MB limit
            wp_send_json_error('Dosya boyutu 2MB\'dan küçük olmalıdır.');
        }
        
        // WordPress media library'ye upload et
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            )
        );
        
        $movefile = wp_handle_upload($_FILES['company_logo'], $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // WordPress Media Library'e attachment olarak kaydet
            $attachment = array(
                'guid' => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title' => 'BKM Firma Logosu - ' . date('Y-m-d H:i:s'),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            // Media Library'e ekle
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            // Attachment metadata'sını oluştur
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                // Attachment ID'sini de kaydet (gelecekte kullanım için)
                update_option('bkm_company_logo_attachment_id', $attachment_id);
            }
            
            // Eski logoyu temizle (opsiyonel)
            $old_logo = get_option('bkm_company_logo', '');
            $old_attachment_id = get_option('bkm_company_logo_attachment_id', 0);
            if ($old_logo && $old_logo !== $movefile['url'] && $old_attachment_id) {
                // Eski attachment'ı sil (isteğe bağlı)
                // wp_delete_attachment($old_attachment_id, true);
            }
            
            // Yeni logo URL'ini kaydet
            update_option('bkm_company_logo', $movefile['url']);
            $logo_url = $movefile['url'];
            $messages[] = 'Logo başarıyla yüklendi ve WordPress Ortamlar\'a kaydedildi.';
        } else {
            wp_send_json_error('Logo yüklenemedi: ' . ($movefile['error'] ?? 'Bilinmeyen hata'));
        }
    }
    
    wp_send_json_success(array(
        'message' => implode(' ', $messages),
        'logo_url' => $logo_url,
        'company_info' => array(
            'name' => $company_name,
            'address' => $company_address,
            'phone' => $company_phone,
            'email' => $company_email,
            'logo' => $logo_url
        )
    ));
}

public function ajax_upload_company_logo() {
    // Nonce kontrolü
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    // Kullanıcı yetki kontrolü
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Bu işlemi gerçekleştirmek için yetkiniz yok.');
    }
    
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Dosya kontrolü
    if (empty($_FILES['logo']) || $_FILES['logo']['size'] <= 0) {
        wp_send_json_error('Lütfen bir logo dosyası seçin.');
    }
    
    // Dosya türü ve boyut kontrolü
    $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
    $file_type = $_FILES['logo']['type'];
    $file_size = $_FILES['logo']['size'];
    
    if (!in_array($file_type, $allowed_types)) {
        wp_send_json_error('Sadece JPG, PNG ve GIF formatları desteklenmektedir.');
    }
    
    if ($file_size > 2 * 1024 * 1024) { // 2MB limit
        wp_send_json_error('Dosya boyutu 2MB\'dan küçük olmalıdır.');
    }
    
    $uploadedfile = $_FILES['logo'];
    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        )
    );
    
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        // WordPress Media Library'e attachment olarak kaydet
        $attachment = array(
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => 'BKM Firma Logosu - ' . date('Y-m-d H:i:s'),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Media Library'e ekle
        $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
        
        // Attachment metadata'sını oluştur
        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            // Attachment ID'sini de kaydet
            update_option('bkm_company_logo_attachment_id', $attachment_id);
        }
        
        update_option('bkm_company_logo', $movefile['url']);
        wp_send_json_success(array(
            'logo_url' => $movefile['url'],
            'attachment_id' => $attachment_id ?? null,
            'message' => 'Logo başarıyla yüklendi ve WordPress Ortamlar\'a kaydedildi.'
        ));
    } else {
        wp_send_json_error('Logo yüklenemedi: ' . ($movefile['error'] ?? 'Bilinmeyen hata'));
    }
}

public function ajax_remove_company_logo() {
    // Nonce kontrolü
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_frontend_nonce')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    // Kullanıcı yetki kontrolü
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Bu işlemi gerçekleştirmek için yetkiniz yok.');
    }
    
    // Attachment'ı da sil (isteğe bağlı)
    $attachment_id = get_option('bkm_company_logo_attachment_id', 0);
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true); // true = force delete
        delete_option('bkm_company_logo_attachment_id');
    }
    
    // Logo URL'ini sil
    delete_option('bkm_company_logo');
    
    wp_send_json_success(array(
        'message' => 'Logo başarıyla kaldırıldı ve WordPress Ortamlar\'dan silindi.'
    ));
}

public function ajax_get_company_info() {
    // Debug için log
    error_log('BKM: ajax_get_company_info çağrıldı');
    
    $company_info = array(
        'name' => get_option('bkm_company_name', ''),
        'address' => get_option('bkm_company_address', ''),
        'phone' => get_option('bkm_company_phone', ''),
        'email' => get_option('bkm_company_email', ''),
        'logo' => get_option('bkm_company_logo', '')
    );
    
    // Debug için log
    error_log('BKM: Firma bilgileri: ' . json_encode($company_info));
    
    wp_send_json_success(['company_info' => $company_info]);
}

// Authentication Functions
public function ajax_forgot_password() {
    $email = sanitize_email($_POST['email']);
    
    if (!email_exists($email)) {
        wp_send_json_error('Bu e-posta adresi sistemde kayıtlı değil.');
    }
    
    $user = get_user_by('email', $email);
    $new_password = wp_generate_password(12, false);
    
    wp_set_password($new_password, $user->ID);
    
    // Send new password via email
    $subject = '[' . get_bloginfo('name') . '] Yeni Şifreniz';
    $message = sprintf(
        "Merhaba %s,\n\nYeni şifreniz: %s\n\nGiriş yaptıktan sonra şifrenizi değiştirmenizi öneririz.\n\nSaygılar,\n%s",
        $user->display_name,
        $new_password,
        get_bloginfo('name')
    );
    
    $sent = wp_mail($email, $subject, $message);
    
    if ($sent) {
        wp_send_json_success('Yeni şifreniz e-posta adresinize gönderildi.');
    } else {
        wp_send_json_error('E-posta gönderilemedi.');
    }
}

// Welcome Email Function
public function send_welcome_email($user, $password) {
    $subject = '[' . get_bloginfo('name') . '] Hoş Geldiniz - Giriş Bilgileriniz';
    $login_url = wp_login_url();
    $content_html = '<p style="font-size:18px; margin-bottom:24px;">Merhaba <strong>' . esc_html($user->display_name) . '</strong>,<br><br>' .
        esc_html(get_bloginfo('name')) . ' sistemine hoş geldiniz!</p>' .
        '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">'
        .'<tr><td style="padding:8px 0;font-weight:bold;width:160px;">Kullanıcı Adı:</td><td style="padding:8px 0;">' . esc_html($user->user_login) . '</td></tr>'
        .'<tr><td style="padding:8px 0;font-weight:bold;">E-posta:</td><td style="padding:8px 0;">' . esc_html($user->user_email) . '</td></tr>'
        .'<tr><td style="padding:8px 0;font-weight:bold;">Şifre:</td><td style="padding:8px 0;">' . esc_html($password) . '</td></tr>'
        .'<tr><td style="padding:8px 0;font-weight:bold;">Giriş Linki:</td><td style="padding:8px 0;"><a href="' . esc_url($login_url) . '" style="color:#0073aa;">' . esc_html($login_url) . '</a></td></tr>'
        .'</table>'
        .'<p style="font-size:15px; color:#555;">Giriş yaptıktan sonra şifrenizi değiştirmenizi öneririz.</p>';
    bkm_send_html_email($user->user_email, $subject, $content_html);
}

/**
 * AJAX handler for fixing all action statuses
 */
public function ajax_fix_action_statuses() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bkm_fix_action_statuses')) {
        wp_send_json_error('Güvenlik kontrolü başarısız.');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Bu işlemi yapmaya yetkiniz yok.');
    }
    
    global $wpdb;
    
    error_log("🔧 Manual action status correction started by user: " . get_current_user_id());
    
    // Get all actions
    $actions_table = $wpdb->prefix . 'bkm_actions';
    $tasks_table = $wpdb->prefix . 'bkm_tasks';
    
    $actions = $wpdb->get_results("SELECT id, ilerleme_durumu, status FROM $actions_table");
    
    if (!$actions) {
        wp_send_json_success(array(
            'message' => 'Düzeltilecek aksiyon bulunamadı.',
            'fixed_count' => 0,
            'total_count' => 0
        ));
    }
    
    $fixed_count = 0;
    $total_count = count($actions);
    $errors = array();
    
    foreach ($actions as $action) {
        // Get tasks for this action
        $tasks = $wpdb->get_results($wpdb->prepare("
            SELECT ilerleme_durumu 
            FROM $tasks_table 
            WHERE action_id = %d
        ", $action->id));
        
        if (empty($tasks)) {
            // No tasks, set action to 'open' status with 0% progress
            $new_status = 'open';
            $new_progress = 0;
        } else {
            // Calculate average progress from tasks
            $total_progress = 0;
            $task_count = count($tasks);
            
            foreach ($tasks as $task) {
                $total_progress += intval($task->ilerleme_durumu);
            }
            
            $new_progress = $task_count > 0 ? round($total_progress / $task_count) : 0;
            
            // Determine status based on progress
            if ($new_progress == 0) {
                $new_status = 'open';
            } elseif ($new_progress >= 1 && $new_progress <= 99) {
                $new_status = 'active';
            } elseif ($new_progress == 100) {
                $new_status = 'completed';
            } else {
                $new_status = 'active'; // fallback
            }
        }
        
        // Only update if there's a change
        if ($action->ilerleme_durumu != $new_progress || $action->status != $new_status) {
            // Update action with correct progress and status
            $update_result = $wpdb->update(
                $actions_table,
                array(
                    'ilerleme_durumu' => $new_progress,
                    'status' => $new_status
                ),
                array('id' => $action->id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($update_result !== false) {
                $fixed_count++;
                error_log("✅ Action ID {$action->id}: {$action->ilerleme_durumu}%→{$new_progress}%, {$action->status}→{$new_status}");
            } else {
                $error_msg = "Action ID {$action->id} update failed: " . $wpdb->last_error;
                $errors[] = $error_msg;
                error_log("❌ " . $error_msg);
            }
        }
    }
    
    error_log("🎉 Manual action status correction completed: {$fixed_count}/{$total_count} actions fixed");
    
    // Prepare response message
    if ($fixed_count > 0) {
        $message = sprintf(
            '%d aksiyon güncellendi. Toplamda %d aksiyon kontrol edildi.',
            $fixed_count,
            $total_count
        );
        
        if (!empty($errors)) {
            $message .= ' ' . count($errors) . ' hata oluştu.';
        }
    } else {
        $message = sprintf(
            'Tüm aksiyonlar (%d adet) zaten güncel durumda.',
            $total_count
        );
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'fixed_count' => $fixed_count,
        'total_count' => $total_count,
        'errors' => $errors
    ));
}

}

// Initialize plugin
BKM_Aksiyon_Takip::get_instance();

// AJAX: Kullanıcı listesini döndür
add_action('wp_ajax_bkm_get_users', 'bkm_get_users_callback');
add_action('wp_ajax_nopriv_bkm_get_users', 'bkm_get_users_callback');
function bkm_get_users_callback() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Giriş yapmalısınız.']);
        wp_die();
    }
    $users = get_users();
    $user_data = [];
    foreach ($users as $user) {
        $user_data[] = [
            'ID' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'user_login' => $user->user_login,
            'roles' => $user->roles,
            'role_name' => implode(', ', $user->roles),
            'user_registered' => $user->user_registered,
            'registration_date' => date('d.m.Y', strtotime($user->user_registered)),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
    }
    wp_send_json_success(['users' => $user_data]);
    wp_die();
}