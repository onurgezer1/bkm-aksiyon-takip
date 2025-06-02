<?php

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

class BKM_Database {
    
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    public function create_tables() {
        global $wpdb;
        
        // dbDelta fonksiyonunu dahil et
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            $this->create_kategoriler_table();
            $this->create_performanslar_table();
            $this->create_aksiyonlar_table();
            $this->create_gorevler_table();
            $this->create_user_activities_table();
            
            return true;
        } catch (Exception $e) {
            error_log("BKM Database Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function create_kategoriler_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_kategoriler';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            kategori_adi varchar(100) NOT NULL,
            aciklama text,
            olusturma_tarihi datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY kategori_adi (kategori_adi)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_performanslar_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_performanslar';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            performans_adi varchar(100) NOT NULL,
            aciklama text,
            olusturma_tarihi datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY performans_adi (performans_adi)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_aksiyonlar_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_aksiyonlar';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            aksiyonu_tanimlayan bigint(20) UNSIGNED NOT NULL,
            sira_no int NOT NULL,
            onem_derecesi tinyint(1) NOT NULL DEFAULT 1,
            acilma_tarihi date NOT NULL,
            hafta int,
            kategori_id mediumint(9),
            aksiyon_sorumlusu text,
            tespit_nedeni text,
            aksiyon_aciklamasi text NOT NULL,
            hedef_tarih date,
            kapanma_tarihi date,
            performans_id mediumint(9),
            ilerleme_durumu int DEFAULT 0,
            notlar text,
            olusturma_tarihi datetime DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sira_no (sira_no),
            KEY aksiyonu_tanimlayan (aksiyonu_tanimlayan),
            KEY kategori_id (kategori_id),
            KEY performans_id (performans_id),
            KEY acilma_tarihi (acilma_tarihi),
            KEY durum (kapanma_tarihi)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_gorevler_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_gorevler';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            aksiyon_id mediumint(9) NOT NULL,
            gorev_icerigi text NOT NULL,
            baslangic_tarihi date NOT NULL,
            sorumlu_kisi bigint(20) UNSIGNED NOT NULL,
            hedef_bitis_tarihi date NOT NULL,
            ilerleme_durumu int DEFAULT 0,
            gercek_bitis_tarihi date,
            durum enum('aktif', 'tamamlandi') DEFAULT 'aktif',
            olusturan bigint(20) UNSIGNED NOT NULL,
            olusturma_tarihi datetime DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY aksiyon_id (aksiyon_id),
            KEY sorumlu_kisi (sorumlu_kisi),
            KEY olusturan (olusturan),
            KEY durum (durum),
            KEY hedef_bitis_tarihi (hedef_bitis_tarihi)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    private function create_user_activities_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'bkm_user_activities';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(50) NOT NULL,
            description text,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $this->charset_collate;";
        
        dbDelta($sql);
    }
    
    public function insert_default_data() {
        global $wpdb;
        
        try {
            // Varsayılan kategoriler
            $this->insert_default_categories();
            
            // Varsayılan performans verileri
            $this->insert_default_performances();
            
            return true;
        } catch (Exception $e) {
            error_log("BKM Default Data Error: " . $e->getMessage());
            return false;
        }
    }
    
    private function insert_default_categories() {
        global $wpdb;
        
        $kategoriler = array(
            array('kategori_adi' => 'Teknik', 'aciklama' => 'Teknik konular ve sistem işlemleri'),
            array('kategori_adi' => 'İdari', 'aciklama' => 'İdari işlemler ve prosedürler'),
            array('kategori_adi' => 'Operasyonel', 'aciklama' => 'Günlük operasyonel faaliyetler'),
            array('kategori_adi' => 'Kalite', 'aciklama' => 'Kalite kontrol ve iyileştirme')
        );
        
        foreach ($kategoriler as $kategori) {
            // Zaten var mı kontrol et
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}bkm_kategoriler WHERE kategori_adi = %s",
                $kategori['kategori_adi']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $wpdb->prefix . 'bkm_kategoriler',
                    $kategori,
                    array('%s', '%s')
                );
            }
        }
    }
    
    private function insert_default_performances() {
        global $wpdb;
        
        $performanslar = array(
            array('performans_adi' => 'Yüksek', 'aciklama' => 'Yüksek performans seviyesi'),
            array('performans_adi' => 'Orta', 'aciklama' => 'Orta performans seviyesi'),
            array('performans_adi' => 'Düşük', 'aciklama' => 'Düşük performans seviyesi'),
            array('performans_adi' => 'Kritik', 'aciklama' => 'Kritik seviye - acil müdahale gerekli')
        );
        
        foreach ($performanslar as $performans) {
            // Zaten var mı kontrol et
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}bkm_performanslar WHERE performans_adi = %s",
                $performans['performans_adi']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $wpdb->prefix . 'bkm_performanslar',
                    $performans,
                    array('%s', '%s')
                );
            }
        }
    }
    
    public function check_tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'bkm_kategoriler',
            $wpdb->prefix . 'bkm_performanslar',
            $wpdb->prefix . 'bkm_aksiyonlar',
            $wpdb->prefix . 'bkm_gorevler',
            $wpdb->prefix . 'bkm_user_activities'
        );
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    public function repair_tables() {
        if (!$this->check_tables_exist()) {
            return $this->create_tables();
        }
        return true;
    }
}