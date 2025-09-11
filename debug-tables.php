<?php
// WordPress simülasyonu için minimal gerekli dosya

// Temel WordPress sabitleri
define('ABSPATH', dirname(__FILE__) . '/');
define('WPINC', 'wp-includes');

// Veritabanı bağlantısı simülasyonu
class WPDB_Mock {
    public $prefix = 'akst_';
    public $last_error = '';
    
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    
    public function get_var($query) {
        echo "Query: $query\n";
        return null; // Tablo yok
    }
    
    public function query($sql) {
        echo "Creating table with SQL:\n";
        echo $sql . "\n\n";
        return true;
    }
}

// Global değişken
$wpdb = new WPDB_Mock();

// dbDelta fonksiyonu simülasyonu
function dbDelta($sql) {
    global $wpdb;
    return $wpdb->query($sql);
}

// Performance tablosu oluşturma testi
$performance_table = $wpdb->prefix . 'bkm_performances';

echo "Testing table creation for: $performance_table\n\n";

if ($wpdb->get_var("SHOW TABLES LIKE '$performance_table'") != $performance_table) {
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $performance_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        value decimal(10,2) DEFAULT 0.00,
        description text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        created_by bigint(20) UNSIGNED DEFAULT 1,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    echo "Table doesn't exist, creating...\n";
    dbDelta($sql);
    echo "Table creation completed!\n";
} else {
    echo "Table already exists!\n";
}
?>
