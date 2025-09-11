<?php
/**
 * Demo Data Generator for BKM Aksiyon Takip
 * This file provides sample data for demonstration purposes
 * 
 * @package BKM_Aksiyon_Takip
 * @version 1.0.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate demo data for the plugin
 * This function creates sample actions, categories, and tasks for demonstration
 */
function bkm_create_demo_data() {
    global $wpdb;
    
    // Check if user has admin capabilities
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $categories_table = $wpdb->prefix . 'bkm_categories';
    $actions_table = $wpdb->prefix . 'bkm_actions';
    
    // Sample categories
    $demo_categories = array(
        'IT ve Teknoloji',
        'İnsan Kaynakları',
        'Satış ve Pazarlama',
        'Muhasebe ve Finans',
        'Operasyonlar',
        'Kalite Güvencesi'
    );
    
    // Insert demo categories
    foreach ($demo_categories as $category) {
        $wpdb->insert(
            $categories_table,
            array(
                'name' => sanitize_text_field($category),
                'description' => 'Demo kategori açıklaması: ' . $category,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
    
    // Sample actions
    $demo_actions = array(
        array(
            'baslik' => 'Yeni CRM sistemi implementasyonu',
            'aciklama' => 'Müşteri ilişkileri yönetimi için yeni CRM sistemi kurulumu ve personel eğitimi',
            'oncelik' => 'Yüksek',
            'kategori' => 1,
            'hedef_tarih' => date('Y-m-d', strtotime('+30 days'))
        ),
        array(
            'baslik' => 'Personel performans değerlendirmesi',
            'aciklama' => 'Q1 döneminde personel performans değerlendirmelerinin tamamlanması',
            'oncelik' => 'Orta',
            'kategori' => 2,
            'hedef_tarih' => date('Y-m-d', strtotime('+15 days'))
        ),
        array(
            'baslik' => 'Dijital pazarlama stratejisi geliştirilmesi',
            'aciklama' => 'Sosyal medya ve online pazarlama için yeni strateji oluşturulması',
            'oncelik' => 'Orta',
            'kategori' => 3,
            'hedef_tarih' => date('Y-m-d', strtotime('+45 days'))
        ),
        array(
            'baslik' => 'Mali raporlama sisteminin güncellenmesi',
            'aciklama' => 'Yeni mali raporlama standartlarına uygun sistem güncellemesi',
            'oncelik' => 'Yüksek',
            'kategori' => 4,
            'hedef_tarih' => date('Y-m-d', strtotime('+20 days'))
        ),
        array(
            'baslik' => 'İş güvenliği eğitimi organizasyonu',
            'aciklama' => 'Tüm personel için zorunlu iş güvenliği eğitiminin planlanması',
            'oncelik' => 'Düşük',
            'kategori' => 5,
            'hedef_tarih' => date('Y-m-d', strtotime('+60 days'))
        )
    );
    
    // Get current user ID for demo data
    $current_user_id = get_current_user_id();
    
    // Insert demo actions
    foreach ($demo_actions as $action) {
        $wpdb->insert(
            $actions_table,
            array(
                'baslik' => sanitize_text_field($action['baslik']),
                'aciklama' => sanitize_textarea_field($action['aciklama']),
                'oncelik' => sanitize_text_field($action['oncelik']),
                'kategori_id' => intval($action['kategori']),
                'tanımlayan_id' => $current_user_id,
                'hedef_tarih' => sanitize_text_field($action['hedef_tarih']),
                'acilma_tarihi' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );
    }
    
    return true;
}

/**
 * Remove demo data
 */
function bkm_remove_demo_data() {
    global $wpdb;
    
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $categories_table = $wpdb->prefix . 'bkm_categories';
    $actions_table = $wpdb->prefix . 'bkm_actions';
    
    // Remove demo actions
    $wpdb->query("DELETE FROM $actions_table WHERE aciklama LIKE '%Demo%' OR baslik LIKE '%Demo%'");
    
    // Remove demo categories
    $demo_categories = array('IT ve Teknoloji', 'İnsan Kaynakları', 'Satış ve Pazarlama', 'Muhasebe ve Finans', 'Operasyonlar', 'Kalite Güvencesi');
    foreach ($demo_categories as $category) {
        $wpdb->delete(
            $categories_table,
            array('name' => $category),
            array('%s')
        );
    }
    
    return true;
}

/**
 * Demo data admin notice
 */
function bkm_demo_data_admin_notice() {
    if (isset($_GET['bkm_demo']) && $_GET['bkm_demo'] == 'created') {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>BKM Aksiyon Takip:</strong> Demo veriler başarıyla oluşturuldu!</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'bkm_demo_data_admin_notice');
?>