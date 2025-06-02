<?php
/**
 * Plugin Name: BKM Aksiyon Takip
 * Plugin URI: https://example.com
 * Description: Aksiyon ve görev takip sistemi
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: bkm-aksiyon-takip
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('BKM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BKM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BKM_VERSION', '1.0.0');

class BKM_Aksiyon_Takip {
    
    public function __construct() {
        // Hook'ları kaydet
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // WordPress tam yüklendikten sonra başlat
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Dosyaları yükle
        $this->load_dependencies();
        
        // Hook'ları başlat
        $this->init_hooks();
        
        // Text domain yükle
        load_plugin_textdomain('bkm-aksiyon-takip', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function load_dependencies() {
        // Dosya varlığını kontrol et ve yükle
        $required_files = array(
            'includes/class-bkm-database.php',
            'includes/class-bkm-admin.php',
            'includes/class-bkm-frontend.php',
            'includes/class-bkm-frontend-ajax.php',
            'includes/class-bkm-security.php'
        );
        
        foreach ($required_files as $file) {
            $file_path = BKM_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Debug için hata log'u
                error_log("BKM Plugin: Dosya bulunamadı - " . $file_path);
            }
        }
    }
    
    private function init_hooks() {
        // Sınıfların varlığını kontrol et
        if (class_exists('BKM_Admin')) {
            new BKM_Admin();
        }
        
        if (class_exists('BKM_Frontend')) {
            new BKM_Frontend();
        }
        
        if (class_exists('BKM_Frontend_Ajax')) {
            new BKM_Frontend_Ajax();
        }
    }
    
    public function activate() {
        // Dosyaları yükle (activation sırasında)
        $this->load_dependencies();
        
        // Database sınıfının varlığını kontrol et
        if (class_exists('BKM_Database')) {
            $database = new BKM_Database();
            $database->create_tables();
            $database->insert_default_data();
            
            // Veritabanı versiyonunu kaydet
            update_option('bkm_db_version', BKM_VERSION);
            
            // Varsayılan ayarları ekle
            $this->set_default_options();
        } else {
            // Hata durumunda log
            error_log("BKM Plugin: BKM_Database sınıfı bulunamadı!");
            wp_die('BKM Aksiyon Takip eklentisi etkinleştirilemedi. Gerekli dosyalar eksik.');
        }
    }
    
    public function deactivate() {
        // Geçici dosyaları temizle
        $this->cleanup_temp_files();
        
        // Scheduled event'leri temizle
        wp_clear_scheduled_hook('bkm_daily_cleanup');
        wp_clear_scheduled_hook('bkm_delete_export_file');
    }
    
    private function set_default_options() {
        // Varsayılan plugin ayarları
        $default_options = array(
            'bkm_enable_notifications' => 'yes',
            'bkm_auto_cleanup_days' => 30,
            'bkm_max_export_files' => 5,
            'bkm_items_per_page' => 10
        );
        
        foreach ($default_options as $option => $value) {
            if (!get_option($option)) {
                update_option($option, $value);
            }
        }
    }
    
    private function cleanup_temp_files() {
        // Geçici export dosyalarını temizle
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/bkm-exports/';
        
        if (is_dir($export_dir)) {
            $files = glob($export_dir . '*.csv');
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}

// Plugin'i başlat
new BKM_Aksiyon_Takip();

// Uninstall hook
register_uninstall_hook(__FILE__, 'bkm_uninstall_plugin');

function bkm_uninstall_plugin() {
    global $wpdb;
    
    // Tabloları sil
    $tables = array(
        $wpdb->prefix . 'bkm_aksiyonlar',
        $wpdb->prefix . 'bkm_kategoriler', 
        $wpdb->prefix . 'bkm_performanslar',
        $wpdb->prefix . 'bkm_gorevler'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Plugin options'ları sil
    delete_option('bkm_db_version');
    delete_option('bkm_enable_notifications');
    delete_option('bkm_auto_cleanup_days');
    delete_option('bkm_max_export_files');
    delete_option('bkm_items_per_page');
    
    // User meta'ları temizle
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bkm_%'");
    
    // Upload klasörünü temizle
    $upload_dir = wp_upload_dir();
    $export_dir = $upload_dir['basedir'] . '/bkm-exports/';
    
    if (is_dir($export_dir)) {
        $files = glob($export_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($export_dir);
    }
}