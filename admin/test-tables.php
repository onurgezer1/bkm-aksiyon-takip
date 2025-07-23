<?php
/**
 * BKM Aksiyon Takip - Tablo Kontrolü ve Test
 * Bu dosyayı WordPress admin panelinden çalıştırarak tabloları test edebilirsiniz
 */

// WordPress ortamını yükle
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    die('Bu sayfaya erişim yetkiniz yok.');
}

global $wpdb;

echo "<h2>BKM Aksiyon Takip - Veritabanı Tablo Kontrolü</h2>";
echo "<hr>";

echo "<h3>Veritabanı Bilgileri:</h3>";
echo "Veritabanı Adı: " . DB_NAME . "<br>";
echo "Tablo Öneki: " . $wpdb->prefix . "<br><br>";

// Gerekli tablolar
$required_tables = array(
    'bkm_actions',
    'bkm_categories', 
    'bkm_performances',
    'bkm_tasks',
    'bkm_task_notes'
);

echo "<h3>Tablo Durumu:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Tablo Adı</th><th>Tam Adı</th><th>Durum</th><th>Kayıt Sayısı</th></tr>";

$missing_tables = array();

foreach ($required_tables as $table_name) {
    $full_table_name = $wpdb->prefix . $table_name;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
    
    echo "<tr>";
    echo "<td>$table_name</td>";
    echo "<td>$full_table_name</td>";
    
    if ($exists) {
        echo "<td style='color: green;'>✓ MEVCUT</td>";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "<td>$count kayıt</td>";
    } else {
        echo "<td style='color: red;'>✗ EKSİK</td>";
        echo "<td>-</td>";
        $missing_tables[] = $table_name;
    }
    
    echo "</tr>";
}

echo "</table>";

if (!empty($missing_tables)) {
    echo "<br><h3 style='color: red;'>UYARI: Eksik Tablolar Tespit Edildi!</h3>";
    echo "<p>Eksik tablolar: " . implode(', ', $missing_tables) . "</p>";
    echo "<p><strong>Çözüm:</strong> Plugin'i deaktive edip tekrar aktive edin.</p>";
    
    // Manuel tablo oluşturma butonu
    if (isset($_POST['create_tables'])) {
        echo "<h3>Tablolar Manuel Olarak Oluşturuluyor...</h3>";
        
        // Plugin instance'ını al ve tabloları oluştur
        if (class_exists('BKM_Aksiyon_Takip')) {
            $plugin = BKM_Aksiyon_Takip::get_instance();
            
            // Reflection kullanarak private metoda erişim
            $reflection = new ReflectionClass($plugin);
            $method = $reflection->getMethod('create_database_tables');
            $method->setAccessible(true);
            $method->invoke($plugin);
            
            echo "<p style='color: green;'>Tablolar oluşturuldu! Sayfayı yenileyin.</p>";
            echo "<script>setTimeout(function() { location.reload(); }, 2000);</script>";
        }
    } else {
        echo "<form method='post'>";
        echo "<button type='submit' name='create_tables' style='padding: 10px; background: #0073aa; color: white; border: none; cursor: pointer;'>Tabloları Manuel Oluştur</button>";
        echo "</form>";
    }
} else {
    echo "<br><h3 style='color: green;'>✓ Tüm Tablolar Mevcut</h3>";
    
    // Test AJAX endpoint'i
    echo "<h3>AJAX Test:</h3>";
    if (isset($_POST['test_ajax'])) {
        echo "<div style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
        echo "<h4>AJAX Endpoint Test Sonuçları:</h4>";
        
        // Test data
        $test_data = array(
            'action' => 'bkm_add_note',
            'task_id' => 1,
            'content' => 'Test notu - ' . date('Y-m-d H:i:s'),
            'nonce' => wp_create_nonce('bkm_frontend_nonce')
        );
        
        echo "<pre>";
        echo "Test Data: " . print_r($test_data, true);
        echo "</pre>";
        
        echo "<p><strong>Not:</strong> Gerçek test için frontend üzerinden AJAX çağrısı yapmanız gerekir.</p>";
        echo "</div>";
    } else {
        echo "<form method='post'>";
        echo "<button type='submit' name='test_ajax' style='padding: 10px; background: #28a745; color: white; border: none; cursor: pointer;'>AJAX Test Verilerini Göster</button>";
        echo "</form>";
    }
}

echo "<br><hr>";
echo "<p><small>Test zamanı: " . date('Y-m-d H:i:s') . "</small></p>";
?>
