<?php
// Mevcut aksiyonlardaki tanımlayan_id problemini düzelt
require_once('../../../wp-config.php');

global $wpdb;
$actions_table = $wpdb->prefix . 'bkm_actions';

// tanımlayan_id NULL veya 0 olan kayıtları bul ve admin kullanıcısının ID'si ile güncelle
$admin_user = get_users(array('role' => 'administrator', 'number' => 1));
if ($admin_user) {
    $admin_id = $admin_user[0]->ID;
    
    $affected_rows = $wpdb->query($wpdb->prepare("
        UPDATE $actions_table 
        SET tanımlayan_id = %d 
        WHERE tanımlayan_id IS NULL OR tanımlayan_id = 0 OR tanımlayan_id = ''
    ", $admin_id));
    
    echo "Güncellenen kayıt sayısı: " . $affected_rows . "\n";
    echo "Admin ID kullanıldı: " . $admin_id . "\n";
    
    // Kontrol için birkaç kayıt göster
    $sample_records = $wpdb->get_results("
        SELECT a.id, a.tanımlayan_id, u.display_name as tanımlayan_name 
        FROM $actions_table a
        LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID
        LIMIT 5
    ");
    
    echo "\nÖrnek kayıtlar:\n";
    foreach($sample_records as $record) {
        echo "ID: " . $record->id . " | tanımlayan_id: " . $record->tanımlayan_id . " | tanımlayan_name: " . ($record->tanımlayan_name ?: 'NULL') . "\n";
    }
} else {
    echo "Admin kullanıcı bulunamadı!";
}
?>
