<?php

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

class BKM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'check_database'));
        
        // AJAX işlemleri
        add_action('wp_ajax_bkm_save_aksiyon', array($this, 'save_aksiyon'));
        add_action('wp_ajax_bkm_delete_aksiyon', array($this, 'delete_aksiyon'));
        add_action('wp_ajax_bkm_add_kategori', array($this, 'add_kategori'));
        add_action('wp_ajax_bkm_delete_kategori', array($this, 'delete_kategori'));
        add_action('wp_ajax_bkm_add_performans', array($this, 'add_performans'));
        add_action('wp_ajax_bkm_delete_performans', array($this, 'delete_performans'));
        add_action('wp_ajax_bkm_get_aksiyon_details', array($this, 'get_aksiyon_details'));
        add_action('wp_ajax_bkm_get_trend_data', array($this, 'get_trend_data'));
        add_action('wp_ajax_bkm_get_next_sira_no_ajax', array($this, 'get_next_sira_no_ajax'));
    }
    
    public function check_database() {
        if (class_exists('BKM_Database')) {
            $database = new BKM_Database();
            if (!$database->check_tables_exist()) {
                add_action('admin_notices', array($this, 'database_error_notice'));
            }
        }
    }
    
    public function database_error_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>BKM Aksiyon Takip:</strong> Veritabanı tabloları eksik veya bozuk. Eklentiyi devre dışı bırakıp tekrar etkinleştirmeyi deneyin.</p>
        </div>
        <?php
    }
    
    // Admin menü ekleme
    public function add_admin_menu() {
        add_menu_page(
            'BKM Aksiyon Takip',
            'Aksiyon Takip',
            'manage_options',
            'bkm-aksiyon',
            array($this, 'admin_page'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'bkm-aksiyon',
            'Tüm Aksiyonlar',
            'Tüm Aksiyonlar',
            'manage_options',
            'bkm-aksiyon',
            array($this, 'all_actions_page')
        );
        
        add_submenu_page(
            'bkm-aksiyon',
            'Aksiyon Ekle',
            'Aksiyon Ekle',
            'manage_options',
            'bkm-add-aksiyon',
            array($this, 'add_action_page')
        );
        
        add_submenu_page(
            'bkm-aksiyon',
            'Kategoriler',
            'Kategoriler',
            'manage_options',
            'bkm-kategoriler',
            array($this, 'categories_page')
        );
        
        add_submenu_page(
            'bkm-aksiyon',
            'Performanslar',
            'Performanslar',
            'manage_options',
            'bkm-performanslar',
            array($this, 'performances_page')
        );
        
        add_submenu_page(
            'bkm-aksiyon',
            'Raporlar',
            'Raporlar',
            'manage_options',
            'bkm-raporlar',
            array($this, 'reports_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'bkm-') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
            
            wp_enqueue_style('bkm-admin-css', BKM_PLUGIN_URL . 'admin/css/admin.css', array(), BKM_VERSION);
            wp_enqueue_script('bkm-admin-js', BKM_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), BKM_VERSION, true);
            
            wp_localize_script('bkm-admin-js', 'bkm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bkm_nonce')
            ));
        }
    }
    
    public function admin_page() {
        $this->all_actions_page();
    }
    
    public function all_actions_page() {
        global $wpdb;
        
        $aksiyonlar = $wpdb->get_results("
            SELECT a.*, 
                   k.kategori_adi, 
                   p.performans_adi,
                   u.display_name as tanimlayan_adi
            FROM {$wpdb->prefix}bkm_aksiyonlar a
            LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
            LEFT JOIN {$wpdb->prefix}bkm_performanslar p ON a.performans_id = p.id
            LEFT JOIN {$wpdb->users} u ON a.aksiyonu_tanimlayan = u.ID
            ORDER BY a.olusturma_tarihi DESC
        ");
        
        $template_path = BKM_PLUGIN_PATH . 'admin/templates/all-actions.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template dosyası bulunamadı: all-actions.php</p></div>';
        }
    }
    
    public function add_action_page() {
        // Form işleme
        if ($_POST && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_add_action')) {
            $this->process_add_action();
        }
        
        $users = get_users();
        $kategoriler = $this->get_kategoriler();
        $performanslar = $this->get_performanslar();
        
        $template_path = BKM_PLUGIN_PATH . 'admin/templates/add-action.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template dosyası bulunamadı: add-action.php</p></div>';
        }
    }
    
    public function categories_page() {
        $template_path = BKM_PLUGIN_PATH . 'admin/templates/categories.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template dosyası bulunamadı: categories.php</p></div>';
        }
    }
    
    public function performances_page() {
        $template_path = BKM_PLUGIN_PATH . 'admin/templates/performances.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template dosyası bulunamadı: performances.php</p></div>';
        }
    }
    
    public function reports_page() {
        $template_path = BKM_PLUGIN_PATH . 'admin/templates/reports.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template dosyası bulunamadı: reports.php</p></div>';
        }
    }
    
    // Helper metodlar
    private function get_kategoriler() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bkm_kategoriler ORDER BY kategori_adi");
    }
    
    private function get_performanslar() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bkm_performanslar ORDER BY performans_adi");
    }
    
    private function get_next_sira_no() {
        global $wpdb;
        return $wpdb->get_var("SELECT COALESCE(MAX(sira_no), 0) + 1 FROM {$wpdb->prefix}bkm_aksiyonlar");
    }
    
    // Form işleme metodları
    private function process_add_action() {
        global $wpdb;
        
        // Güvenlik kontrolü
        if (!current_user_can('manage_options')) {
            wp_die('Bu işlem için yetkiniz yok!');
        }
        
        // Veri sanitizasyonu
        $data = array(
            'aksiyonu_tanimlayan' => intval($_POST['aksiyonu_tanimlayan']),
            'sira_no' => intval($_POST['sira_no']),
            'onem_derecesi' => intval($_POST['onem_derecesi']),
            'acilma_tarihi' => sanitize_text_field($_POST['acilma_tarihi']),
            'hafta' => intval($_POST['hafta']),
            'kategori_id' => intval($_POST['kategori_id']) ?: null,
            'aksiyon_sorumlusu' => isset($_POST['aksiyon_sorumlusu']) ? implode(',', array_map('intval', $_POST['aksiyon_sorumlusu'])) : '',
            'tespit_nedeni' => sanitize_textarea_field($_POST['tespit_nedeni']),
            'aksiyon_aciklamasi' => sanitize_textarea_field($_POST['aksiyon_aciklamasi']),
            'hedef_tarih' => sanitize_text_field($_POST['hedef_tarih']) ?: null,
            'kapanma_tarihi' => sanitize_text_field($_POST['kapanma_tarihi']) ?: null,
            'performans_id' => intval($_POST['performans_id']) ?: null,
            'ilerleme_durumu' => intval($_POST['ilerleme_durumu']),
            'notlar' => sanitize_textarea_field($_POST['notlar'])
        );
        
        if (isset($_POST['aksiyon_id']) && !empty($_POST['aksiyon_id'])) {
            // Güncelleme
            $result = $wpdb->update(
                $wpdb->prefix . 'bkm_aksiyonlar',
                $data,
                array('id' => intval($_POST['aksiyon_id'])),
                array('%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
                array('%d')
            );
            
            $message = 'Aksiyon başarıyla güncellendi!';
        } else {
            // Yeni ekleme
            $result = $wpdb->insert(
                $wpdb->prefix . 'bkm_aksiyonlar',
                $data,
                array('%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
            );
            
            $message = 'Aksiyon başarıyla eklendi!';
        }
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>İşlem sırasında bir hata oluştu!</p></div>';
        }
    }
    
    // AJAX Handler: Aksiyon kaydetme
    public function save_aksiyon() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $data = array(
            'aksiyonu_tanimlayan' => intval($_POST['aksiyonu_tanimlayan']),
            'sira_no' => intval($_POST['sira_no']),
            'onem_derecesi' => intval($_POST['onem_derecesi']),
            'acilma_tarihi' => sanitize_text_field($_POST['acilma_tarihi']),
            'hafta' => intval($_POST['hafta']),
            'kategori_id' => intval($_POST['kategori_id']) ?: null,
            'aksiyon_sorumlusu' => isset($_POST['aksiyon_sorumlusu']) ? implode(',', array_map('intval', $_POST['aksiyon_sorumlusu'])) : '',
            'tespit_nedeni' => sanitize_textarea_field($_POST['tespit_nedeni']),
            'aksiyon_aciklamasi' => sanitize_textarea_field($_POST['aksiyon_aciklamasi']),
            'hedef_tarih' => sanitize_text_field($_POST['hedef_tarih']) ?: null,
            'kapanma_tarihi' => sanitize_text_field($_POST['kapanma_tarihi']) ?: null,
            'performans_id' => intval($_POST['performans_id']) ?: null,
            'ilerleme_durumu' => intval($_POST['ilerleme_durumu']),
            'notlar' => sanitize_textarea_field($_POST['notlar'])
        );
        
        if (isset($_POST['aksiyon_id']) && !empty($_POST['aksiyon_id'])) {
            // Güncelleme
            $result = $wpdb->update(
                $wpdb->prefix . 'bkm_aksiyonlar',
                $data,
                array('id' => intval($_POST['aksiyon_id'])),
                array('%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'),
                array('%d')
            );
        } else {
            // Yeni ekleme
            $result = $wpdb->insert(
                $wpdb->prefix . 'bkm_aksiyonlar',
                $data,
                array('%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Aksiyon başarıyla kaydedildi!',
                'redirect' => admin_url('admin.php?page=bkm-aksiyon')
            ));
        } else {
            wp_send_json_error(array('message' => 'Kaydetme işlemi başarısız!'));
        }
    }
    
    // AJAX Handler: Aksiyon silme
    public function delete_aksiyon() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'bkm_aksiyonlar',
            array('id' => $aksiyon_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Aksiyon başarıyla silindi!'));
        } else {
            wp_send_json_error(array('message' => 'Silme işlemi başarısız!'));
        }
    }
    
    // AJAX Handler: Kategori ekleme
    public function add_kategori() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'bkm_kategoriler',
            array(
                'kategori_adi' => sanitize_text_field($_POST['kategori_adi']),
                'aciklama' => sanitize_textarea_field($_POST['aciklama'])
            ),
            array('%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Kategori başarıyla eklendi!'));
        } else {
            wp_send_json_error(array('message' => 'Kategori ekleme başarısız!'));
        }
    }
    
    // AJAX Handler: Kategori silme
    public function delete_kategori() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $kategori_id = intval($_POST['item_id']);
        
        // Kullanımda olup olmadığını kontrol et
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar WHERE kategori_id = %d",
            $kategori_id
        ));
        
        if ($usage_count > 0) {
            wp_send_json_error(array('message' => 'Bu kategori kullanımda olduğu için silinemez!'));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'bkm_kategoriler',
            array('id' => $kategori_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Kategori başarıyla silindi!'));
        } else {
            wp_send_json_error(array('message' => 'Kategori silme başarısız!'));
        }
    }
    
    // AJAX Handler: Performans ekleme
    public function add_performans() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'bkm_performanslar',
            array(
                'performans_adi' => sanitize_text_field($_POST['performans_adi']),
                'aciklama' => sanitize_textarea_field($_POST['aciklama'])
            ),
            array('%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Performans verisi başarıyla eklendi!'));
        } else {
            wp_send_json_error(array('message' => 'Performans verisi ekleme başarısız!'));
        }
    }
    
    // AJAX Handler: Performans silme
    public function delete_performans() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $performans_id = intval($_POST['item_id']);
        
        // Kullanımda olup olmadığını kontrol et
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar WHERE performans_id = %d",
            $performans_id
        ));
        
        if ($usage_count > 0) {
            wp_send_json_error(array('message' => 'Bu performans verisi kullanımda olduğu için silinemez!'));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'bkm_performanslar',
            array('id' => $performans_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Performans verisi başarıyla silindi!'));
        } else {
            wp_send_json_error(array('message' => 'Performans verisi silme başarısız!'));
        }
    }
    
    // AJAX Handler: Aksiyon detayları
    public function get_aksiyon_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        
        $aksiyon = $wpdb->get_row($wpdb->prepare("
            SELECT a.*, 
                   k.kategori_adi, 
                   p.performans_adi,
                   u.display_name as tanimlayan_adi
            FROM {$wpdb->prefix}bkm_aksiyonlar a
            LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
            LEFT JOIN {$wpdb->prefix}bkm_performanslar p ON a.performans_id = p.id
            LEFT JOIN {$wpdb->users} u ON a.aksiyonu_tanimlayan = u.ID
            WHERE a.id = %d
        ", $aksiyon_id));
        
        if ($aksiyon) {
            ob_start();
            $template_path = BKM_PLUGIN_PATH . 'admin/templates/aksiyon-details.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<p>Detay template dosyası bulunamadı.</p>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error(array('message' => 'Aksiyon bulunamadı!'));
        }
    }
    
    // AJAX Handler: Trend verileri
    public function get_trend_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        global $wpdb;
        
        // Son 12 ayın verilerini al
        $trend_data = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(acilma_tarihi, '%Y-%m') as ay,
                COUNT(*) as toplam,
                COUNT(CASE WHEN kapanma_tarihi IS NOT NULL THEN 1 END) as tamamlanan
            FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE acilma_tarihi >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(acilma_tarihi, '%Y-%m')
            ORDER BY ay
        ");
        
        $labels = array();
        $toplam_data = array();
        $tamamlanan_data = array();
        
        foreach ($trend_data as $data) {
            $labels[] = date('M Y', strtotime($data->ay . '-01'));
            $toplam_data[] = intval($data->toplam);
            $tamamlanan_data[] = intval($data->tamamlanan);
        }
        
        $chart_data = array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => 'Toplam Aksiyonlar',
                    'data' => $toplam_data,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0,115,170,0.1)'
                ),
                array(
                    'label' => 'Tamamlanan',
                    'data' => $tamamlanan_data,
                    'borderColor' => '#4CAF50',
                    'backgroundColor' => 'rgba(76,175,80,0.1)'
                )
            )
        );
        
        wp_send_json_success($chart_data);
    }
    
    // AJAX Handler: Sonraki sıra numarası
    public function get_next_sira_no_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        $next_sira_no = $this->get_next_sira_no();
        
        wp_send_json_success(array('next_sira_no' => $next_sira_no));
    }
}