<?php
// Test veritabanı bağlantısı ve tablo kontrolü
echo "<h1>BKM Performans Test</h1>\n";

// WordPress fonksiyonlarını simüle et
function wp_verify_nonce($nonce, $action) {
    return true; // Test için her zaman true
}

function wp_die($message) {
    die($message);
}

function wp_send_json_success($data) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'data' => $data));
    exit;
}

function wp_send_json_error($data) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'data' => $data));
    exit;
}

function current_user_can($capability) {
    return true; // Test için her zaman true
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

function sanitize_text_field($str) {
    return trim($str);
}

function sanitize_textarea_field($str) {
    return trim($str);
}

function get_current_user_id() {
    return 1; // Test kullanıcı ID'si
}

// Test veritabanı bağlantısı
$host = 'localhost';
$dbname = 'test_bkm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Veritabanı bağlantısı başarılı</p>\n";
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Veritabanı bağlantı hatası: " . $e->getMessage() . "</p>\n";
    echo "<p>Bu test için MySQL'de 'test_bkm' adında bir veritabanı oluşturun.</p>\n";
    exit;
}

// wpdb simülasyonu
class wpdb_simulation {
    private $pdo;
    public $prefix = 'wp_';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function insert($table, $data, $format = null) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($data);
        } catch(PDOException $e) {
            echo "<p style='color: red;'>❌ Insert hatası: " . $e->getMessage() . "</p>\n";
            return false;
        }
    }
    
    public function get_results($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch(PDOException $e) {
            echo "<p style='color: red;'>❌ Select hatası: " . $e->getMessage() . "</p>\n";
            return false;
        }
    }
}

$wpdb = new wpdb_simulation($pdo);

// Tabloları oluştur
$tables = [
    'wp_bkm_performance' => "
        CREATE TABLE IF NOT EXISTS wp_bkm_performance (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'wp_bkm_categories' => "
        CREATE TABLE IF NOT EXISTS wp_bkm_categories (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'wp_bkm_tasks' => "
        CREATE TABLE IF NOT EXISTS wp_bkm_tasks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action_id mediumint(9) NOT NULL,
            aciklama text NOT NULL,
            sorumlu_id mediumint(9) NOT NULL,
            baslangic_tarihi date,
            bitis_tarihi date,
            ilerleme_durumu int(3) DEFAULT 0,
            durum enum('beklemede','devam_ediyor','tamamlandi','iptal') DEFAULT 'beklemede',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_action_id (action_id),
            INDEX idx_sorumlu_id (sorumlu_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach($tables as $table_name => $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Tablo oluşturuldu/kontrol edildi: {$table_name}</p>\n";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>❌ Tablo oluşturma hatası ({$table_name}): " . $e->getMessage() . "</p>\n";
    }
}

// AJAX isteğini işle
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'bkm_add_performance':
            echo "<h2>Performans Ekleme Testi</h2>\n";
            
            $name = sanitize_text_field($_POST['name'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');
            
            if (empty($name)) {
                wp_send_json_error(array('message' => 'Performans adı gereklidir.'));
            }
            
            $table_name = $wpdb->prefix . 'bkm_performance';
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name' => $name,
                    'description' => $description,
                    'created_at' => current_time('mysql')
                )
            );
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Performans başarıyla eklendi!'));
            } else {
                wp_send_json_error(array('message' => 'Performans eklenirken veritabanı hatası oluştu.'));
            }
            break;
            
        case 'bkm_get_performances':
            echo "<h2>Performans Listesi Testi</h2>\n";
            
            $table_name = $wpdb->prefix . 'bkm_performance';
            $performances = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY name ASC");
            
            wp_send_json_success(array(
                'performances' => $performances,
                'message' => 'Performanslar başarıyla yüklendi.'
            ));
            break;
            
        case 'bkm_add_category':
            echo "<h2>Kategori Ekleme Testi</h2>\n";
            
            $name = sanitize_text_field($_POST['name'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');
            
            if (empty($name)) {
                wp_send_json_error(array('message' => 'Kategori adı gereklidir.'));
            }
            
            $table_name = $wpdb->prefix . 'bkm_categories';
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name' => $name,
                    'description' => $description,
                    'created_at' => current_time('mysql')
                )
            );
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Kategori başarıyla eklendi!'));
            } else {
                wp_send_json_error(array('message' => 'Kategori eklenirken veritabanı hatası oluştu.'));
            }
            break;
            
        case 'bkm_add_task':
            echo "<h2>Görev Ekleme Testi</h2>\n";
            
            $action_id = intval($_POST['action_id'] ?? 0);
            $aciklama = sanitize_textarea_field($_POST['aciklama'] ?? '');
            $sorumlu_id = intval($_POST['sorumlu_id'] ?? 0);
            $baslangic_tarihi = sanitize_text_field($_POST['baslangic_tarihi'] ?? '');
            $bitis_tarihi = sanitize_text_field($_POST['bitis_tarihi'] ?? '');
            
            echo "<p>Debug - Gelen veriler:</p>\n";
            echo "<ul>\n";
            echo "<li>action_id: $action_id</li>\n";
            echo "<li>aciklama: $aciklama</li>\n";
            echo "<li>sorumlu_id: $sorumlu_id</li>\n";
            echo "<li>baslangic_tarihi: $baslangic_tarihi</li>\n";
            echo "<li>bitis_tarihi: $bitis_tarihi</li>\n";
            echo "</ul>\n";
            
            if (empty($action_id) || empty($aciklama) || empty($sorumlu_id)) {
                wp_send_json_error(array('message' => 'Aksiyon, açıklama ve sorumlu kişi gereklidir.'));
            }
            
            $table_name = $wpdb->prefix . 'bkm_tasks';
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'action_id' => $action_id,
                    'aciklama' => $aciklama,
                    'sorumlu_id' => $sorumlu_id,
                    'baslangic_tarihi' => $baslangic_tarihi,
                    'bitis_tarihi' => $bitis_tarihi,
                    'created_at' => current_time('mysql')
                )
            );
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Görev başarıyla eklendi!'));
            } else {
                wp_send_json_error(array('message' => 'Görev eklenirken veritabanı hatası oluştu.'));
            }
            break;
            
        case 'bkm_get_categories':
            echo "<h2>Kategori Listesi Testi</h2>\n";
            
            $table_name = $wpdb->prefix . 'bkm_categories';
            $categories = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY name ASC");
            
            wp_send_json_success(array(
                'categories' => $categories,
                'message' => 'Kategoriler başarıyla yüklendi.'
            ));
            break;
            
        default:
            wp_send_json_error(array('message' => 'Geçersiz action: ' . $action));
    }
}

echo "<h2>Test Tamamlandı</h2>\n";
echo "<p>Bu dosya AJAX isteklerini simüle eder. test-performance-fix.html dosyasını kullanarak test edin.</p>\n";
?>
