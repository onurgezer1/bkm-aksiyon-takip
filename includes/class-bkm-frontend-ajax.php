<?php

class BKM_Frontend_Ajax {
    
    public function __construct() {
        // Görev işlemleri
        add_action('wp_ajax_bkm_complete_task', array($this, 'complete_task'));
        add_action('wp_ajax_bkm_update_task', array($this, 'update_task'));
        add_action('wp_ajax_bkm_delete_task', array($this, 'delete_task'));
        add_action('wp_ajax_bkm_update_task_progress', array($this, 'update_task_progress'));
        
        // Aksiyon işlemleri
        add_action('wp_ajax_bkm_get_aksiyon_details', array($this, 'get_aksiyon_details'));
        add_action('wp_ajax_bkm_update_aksiyon_progress', array($this, 'update_aksiyon_progress'));
        
        // İstatistik ve veri çekme
        add_action('wp_ajax_bkm_get_user_stats', array($this, 'get_user_stats'));
        add_action('wp_ajax_bkm_get_user_data', array($this, 'get_user_data'));
        add_action('wp_ajax_bkm_get_recent_activities', array($this, 'get_recent_activities'));
        
        // Filtreleme ve arama
        add_action('wp_ajax_bkm_filter_aksiyonlar', array($this, 'filter_aksiyonlar'));
        add_action('wp_ajax_bkm_filter_gorevler', array($this, 'filter_gorevler'));
        add_action('wp_ajax_bkm_search_content', array($this, 'search_content'));
        
        // Bildirim işlemleri
        add_action('wp_ajax_bkm_mark_notification_read', array($this, 'mark_notification_read'));
        add_action('wp_ajax_bkm_get_notifications', array($this, 'get_notifications'));
        
        // Dosya yükleme
        add_action('wp_ajax_bkm_upload_attachment', array($this, 'upload_attachment'));
        add_action('wp_ajax_bkm_delete_attachment', array($this, 'delete_attachment'));
        
        // Export işlemleri
        add_action('wp_ajax_bkm_export_user_data', array($this, 'export_user_data'));
        add_action('wp_ajax_bkm_export_report', array($this, 'export_report'));
    }
    
    // Görev tamamlama
    public function complete_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Görevin kullanıcıya ait olup olmadığını kontrol et
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        if ($task->durum === 'tamamlandi') {
            wp_send_json_error(array('message' => 'Bu görev zaten tamamlanmış!'));
        }
        
        // Görevi tamamla
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            array(
                'durum' => 'tamamlandi',
                'ilerleme_durumu' => 100,
                'gercek_bitis_tarihi' => current_time('Y-m-d'),
                'guncelleme_tarihi' => current_time('mysql')
            ),
            array('id' => $task_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Aktivite log'u ekle
            $this->add_activity_log($user_id, 'task_completed', "Görev tamamlandı: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla tamamlandı!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev tamamlanırken bir hata oluştu!'));
        }
    }
    
    // Görev güncelleme
    public function update_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Yetki kontrolü
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array();
        
        if (isset($_POST['gorev_icerigi'])) {
            $update_data['gorev_icerigi'] = sanitize_textarea_field($_POST['gorev_icerigi']);
        }
        
        if (isset($_POST['hedef_bitis_tarihi'])) {
            $update_data['hedef_bitis_tarihi'] = sanitize_text_field($_POST['hedef_bitis_tarihi']);
        }
        
        if (isset($_POST['ilerleme_durumu'])) {
            $update_data['ilerleme_durumu'] = intval($_POST['ilerleme_durumu']);
        }
        
        $update_data['guncelleme_tarihi'] = current_time('mysql');
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            $update_data,
            array('id' => $task_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'task_updated', "Görev güncellendi: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla güncellendi!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev güncellenirken bir hata oluştu!'));
        }
    }
    
    // Görev silme
    public function delete_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Görevin silinip silinemeyeceğini kontrol et
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı!'));
        }
        
        // Yetki kontrolü: sadece yöneticiler veya görev oluşturanlar silebilir
        if (!current_user_can('manage_options') && $task->olusturan != $user_id) {
            wp_send_json_error(array('message' => 'Bu görevi silme yetkiniz yok!'));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'bkm_gorevler',
            array('id' => $task_id),
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'task_deleted', "Görev silindi: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla silindi!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev silinirken bir hata oluştu!'));
        }
    }
    
    // Görev ilerleme güncelleme
    public function update_task_progress() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $progress = intval($_POST['progress']);
        $user_id = get_current_user_id();
        
        // Progress validasyonu
        if ($progress < 0 || $progress > 100) {
            wp_send_json_error(array('message' => 'İlerleme değeri 0-100 arasında olmalıdır!'));
        }
        
        // Yetki kontrolü
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array(
            'ilerleme_durumu' => $progress,
            'guncelleme_tarihi' => current_time('mysql')
        );
        
        // %100 olduğunda otomatik tamamla
        if ($progress == 100 && $task->durum !== 'tamamlandi') {
            $update_data['durum'] = 'tamamlandi';
            $update_data['gercek_bitis_tarihi'] = current_time('Y-m-d');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            $update_data,
            array('id' => $task_id),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'İlerleme başarıyla güncellendi!',
                'progress' => $progress,
                'completed' => $progress == 100
            ));
        } else {
            wp_send_json_error(array('message' => 'İlerleme güncellenirken bir hata oluştu!'));
        }
    }
    
    // Aksiyon detayları
    public function get_aksiyon_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        $user_id = get_current_user_id();
        
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
            AND (a.aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, a.aksiyon_sorumlusu))
        ", $aksiyon_id, $user_id, $user_id));
        
        if (!$aksiyon) {
            wp_send_json_error(array('message' => 'Aksiyon bulunamadı veya erişim yetkiniz yok!'));
        }
        
        // İlgili görevleri al
        $gorevler = $wpdb->get_results($wpdb->prepare("
            SELECT g.*, u.display_name as sorumlu_adi
            FROM {$wpdb->prefix}bkm_gorevler g
            LEFT JOIN {$wpdb->users} u ON g.sorumlu_kisi = u.ID
            WHERE g.aksiyon_id = %d
            ORDER BY g.olusturma_tarihi DESC
        ", $aksiyon_id));
        
        ob_start();
        include BKM_PLUGIN_PATH . 'frontend/templates/aksiyon-details-modal.php';
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    // Aksiyon ilerleme güncelleme
    public function update_aksiyon_progress() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        $progress = intval($_POST['progress']);
        $user_id = get_current_user_id();
        
        // Progress validasyonu
        if ($progress < 0 || $progress > 100) {
            wp_send_json_error(array('message' => 'İlerleme değeri 0-100 arasında olmalıdır!'));
        }
        
        // Yetki kontrolü
        $aksiyon = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE id = %d 
            AND (aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu))
        ", $aksiyon_id, $user_id, $user_id));
        
        if (!$aksiyon) {
            wp_send_json_error(array('message' => 'Aksiyon bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array(
            'ilerleme_durumu' => $progress,
            'guncelleme_tarihi' => current_time('mysql')
        );
        
        // %100 olduğunda kapanma tarihini set et
        if ($progress == 100 && empty($aksiyon->kapanma_tarihi)) {
            $update_data['kapanma_tarihi'] = current_time('Y-m-d');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_aksiyonlar',
            $update_data,
            array('id' => $aksiyon_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'aksiyon_updated', "Aksiyon ilerlemesi güncellendi: #" . $aksiyon->sira_no);
            
            wp_send_json_success(array(
                'message' => 'Aksiyon ilerlemesi güncellendi!',
                'progress' => $progress
            ));
        } else {
            wp_send_json_error(array('message' => 'İlerleme güncellenirken bir hata oluştu!'));
        }
    }
    
    // Kullanıcı istatistikleri
    public function get_user_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $stats = $this->calculate_user_stats($user_id);
        
        wp_send_json_success($stats);
    }
    
    // Kullanıcı verilerini al
    public function get_user_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'all');
        
        $data = array();
        
        switch ($data_type) {
            case 'aksiyonlar':
                $data['aksiyonlar'] = $this->get_user_actions($user_id);
                break;
            case 'gorevler':
                $data['gorevler'] = $this->get_user_tasks($user_id);
                break;
            case 'stats':
                $data['stats'] = $this->calculate_user_stats($user_id);
                break;
            default:
                $data['aksiyonlar'] = $this->get_user_actions($user_id);
                $data['gorevler'] = $this->get_user_tasks($user_id);
                $data['stats'] = $this->calculate_user_stats($user_id);
        }
        
        wp_send_json_success($data);
    }
    
    // Son aktiviteler
    public function get_recent_activities() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 10);
        
        $activities = $this->get_user_activities($user_id, $limit);
        
        wp_send_json_success(array('activities' => $activities));
    }
    
    // Arama işlemi
    public function search_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $search_type = sanitize_text_field($_POST['search_type'] ?? 'all');
        $user_id = get_current_user_id();
        
        if (strlen($search_term) < 2) {
            wp_send_json_error(array('message' => 'Arama terimi en az 2 karakter olmalıdır!'));
        }
        
        global $wpdb;
        
        $results = array();
        
        if ($search_type === 'all' || $search_type === 'aksiyonlar') {
            $aksiyonlar = $wpdb->get_results($wpdb->prepare("
                SELECT a.*, k.kategori_adi
                FROM {$wpdb->prefix}bkm_aksiyonlar a
                LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
                WHERE (a.aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, a.aksiyon_sorumlusu))
                AND (a.aksiyon_aciklamasi LIKE %s OR a.tespit_nedeni LIKE %s OR a.notlar LIKE %s)
                ORDER BY a.olusturma_tarihi DESC
                LIMIT 20
            ", $user_id, $user_id, '%' . $search_term . '%', '%' . $search_term . '%', '%' . $search_term . '%'));
            
            $results['aksiyonlar'] = $aksiyonlar;
        }
        
        if ($search_type === 'all' || $search_type === 'gorevler') {
            $gorevler = $wpdb->get_results($wpdb->prepare("
                SELECT g.*, a.aksiyon_aciklamasi
                FROM {$wpdb->prefix}bkm_gorevler g
                LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON g.aksiyon_id = a.id
                WHERE g.sorumlu_kisi = %d
                AND g.gorev_icerigi LIKE %s
                ORDER BY g.olusturma_tarihi DESC
                LIMIT 20
            ", $user_id, '%' . $search_term . '%'));
            
            $results['gorevler'] = $gorevler;
        }
        
        wp_send_json_success($results);
    }
    
    // Bildirim işaretleme
    public function mark_notification_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        // Bildirim sistemini basit tutuyoruz - user meta kullanarak
        $read_notifications = get_user_meta($user_id, 'bkm_read_notifications', true) ?: array();
        
        if (!in_array($notification_id, $read_notifications)) {
            $read_notifications[] = $notification_id;
            update_user_meta($user_id, 'bkm_read_notifications', $read_notifications);
        }
        
        wp_send_json_success(array('message' => 'Bildirim okundu olarak işaretlendi!'));
    }
    
    // Veri export
    public function export_user_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'excel');
        $data_range = sanitize_text_field($_POST['data_range'] ?? 'all');
        
        // Export dosyası oluştur
        $file_url = $this->create_export_file($user_id, $export_type, $data_range);
        
        if ($file_url) {
            wp_send_json_success(array(
                'message' => 'Export dosyası oluşturuldu!',
                'download_url' => $file_url
            ));
        } else {
            wp_send_json_error(array('message' => 'Export dosyası oluşturulamadı!'));
        }
    }
    
    // Helper metodlar
    private function add_activity_log($user_id, $type, $description) {
        global $wpdb;
        
        // Basit aktivite log'u - gerçek uygulamada ayrı tablo olabilir
        $activities = get_user_meta($user_id, 'bkm_activities', true) ?: array();
        
        $activity = array(
            'id' => uniqid(),
            'type' => $type,
            'description' => $description,
            'created_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        array_unshift($activities, $activity);
        
        // Son 100 aktiviteyi tut
        $activities = array_slice($activities, 0, 100);
        
        update_user_meta($user_id, 'bkm_activities', $activities);
    }
    
    private function calculate_user_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Aksiyon istatistikleri
        $stats['total_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu)
        ", $user_id, $user_id));
        
        $stats['completed_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE (aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu))
            AND kapanma_tarihi IS NOT NULL AND kapanma_tarihi != ''
        ", $user_id, $user_id));
        
        $stats['pending_actions'] = $stats['total_actions'] - $stats['completed_actions'];
        
        // Görev istatistikleri
        $stats['total_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler WHERE sorumlu_kisi = %d
        ", $user_id));
        
        $stats['completed_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi'
        ", $user_id));
        
        $stats['active_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif'
        ", $user_id));
        
        $stats['overdue_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif' 
            AND hedef_bitis_tarihi < CURDATE()
        ", $user_id));
        
        // Bu ay tamamlanma oranı
        $monthly_total = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $monthly_completed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi' 
            AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $stats['monthly_completion_rate'] = $monthly_total > 0 ? round(($monthly_completed / $monthly_total) * 100) : 0;
        
        // Ortalama tamamlama süresi (gün)
        $avg_completion = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(DATEDIFF(gercek_bitis_tarihi, baslangic_tarihi)) 
            FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi' 
            AND gercek_bitis_tarihi IS NOT NULL
        ", $user_id));
        
        $stats['avg_completion_days'] = $avg_completion ? round($avg_completion, 1) : 0;
        
        return $stats;
    }
    
    private function get_user_actions($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*, 
                   k.kategori_adi, 
                   p.performans_adi
            FROM {$wpdb->prefix}bkm_aksiyonlar a
            LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
            LEFT JOIN {$wpdb->prefix}bkm_performanslar p ON a.performans_id = p.id
            WHERE a.aksiyonu_tanimlayan = %d 
               OR FIND_IN_SET(%d, a.aksiyon_sorumlusu)
            ORDER BY a.acilma_tarihi DESC
        ", $user_id, $user_id));
    }
    
    private function get_user_tasks($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT g.*, a.aksiyon_aciklamasi 
            FROM {$wpdb->prefix}bkm_gorevler g
            LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON g.aksiyon_id = a.id
            WHERE g.sorumlu_kisi = %d
            ORDER BY g.hedef_bitis_tarihi ASC, g.durum ASC
        ", $user_id));
    }
    
    private function get_user_activities($user_id, $limit = 10) {
        $activities = get_user_meta($user_id, 'bkm_activities', true) ?: array();
        return array_slice($activities, 0, $limit);
    }
    
    private function create_export_file($user_id, $export_type, $data_range) {
        // Export dosyası oluşturma (CSV örneği)
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/bkm-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'bkm-export-' . $user_id . '-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $export_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            return false;
        }
        
        // CSV başlıkları
        fputcsv($file, array(
            'Tip', 'Sıra No', 'Başlık', 'Durum', 'Açılma Tarihi', 
            'Hedef Tarih', 'Tamamlama Tarihi', 'İlerleme (%)', 'Kategori'
        ));
        
        // Kullanıcı verilerini al ve yazma
        $aksiyonlar = $this->get_user_actions($user_id);
        foreach ($aksiyonlar as $aksiyon) {
            fputcsv($file, array(
                'Aksiyon',
                $aksiyon->sira_no,
                wp_trim_words($aksiyon->aksiyon_aciklamasi, 10),
                empty($aksiyon->kapanma_tarihi) ? 'Açık' : 'Kapalı',
                $aksiyon->acilma_tarihi,
                $aksiyon->hedef_tarih,
                $aksiyon->kapanma_tarihi,
                $aksiyon->ilerleme_durumu,
                $aksiyon->kategori_adi
            ));
        }
        
        $gorevler = $this->get_user_tasks($user_id);
        foreach ($gorevler as $gorev) {
            fputcsv($file, array(
                'Görev',
                '',
                wp_trim_words($gorev->gorev_icerigi, 10),
                $gorev->durum === 'tamamlandi' ? 'Tamamlandı' : 'Aktif',
                $gorev->baslangic_tarihi,
                $gorev->hedef_bitis_tarihi,
                $gorev->gercek_bitis_tarihi,
                $gorev->ilerleme_durumu,
                ''
            ));
        }
        
        fclose($file);
        
        // Dosya URL'sini döndür
        $file_url = $upload_dir['baseurl'] . '/bkm-exports/' . $filename;
        
        // 24 saat sonra dosyayı sil
        wp_schedule_single_event(time() + (24 * HOUR_IN_SECONDS), 'bkm_delete_export_file', array($filepath));
        
        return $file_url;
    }
}

// AJAX handler'ını başlat
new BKM_Frontend_Ajax();

// Export dosya silme hook'u
add_action('bkm_delete_export_file', function($filepath) {
    if (file_exists($filepath)) {
        unlink($filepath);
    }
});