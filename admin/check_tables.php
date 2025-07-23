<?php
// WordPress environment'ını yükle
require_once('bkm-aksiyon-takip/wp-config-path.php'); // WordPress config yolu

global $wpdb;

echo "WordPress veritabanı bağlantısı kontrol ediliyor...\n";
echo "Veritabanı adı: " . DB_NAME . "\n";
echo "Tablo öneki: " . $wpdb->prefix . "\n\n";

// Mevcut tabloları listele
$tables = $wpdb->get_results("SHOW TABLES");
echo "Mevcut tablolar:\n";
foreach ($tables as $table) {
    $table_name = array_values((array)$table)[0];
    if (strpos($table_name, $wpdb->prefix . 'bkm_') !== false) {
        echo "- " . $table_name . "\n";
    }
}

// Özel olarak kontrol edilecek tablolar
$required_tables = [
    $wpdb->prefix . 'bkm_actions',
    $wpdb->prefix . 'bkm_categories', 
    $wpdb->prefix . 'bkm_performances',
    $wpdb->prefix . 'bkm_tasks',
    $wpdb->prefix . 'bkm_task_notes'
];

echo "\nGerekli tabloların durumu:\n";
foreach ($required_tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    echo "- $table: " . ($exists ? "VAR" : "EKSİK") . "\n";
}
?>
