<?php
// WordPress'i yükle
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',  // Plugin klasöründen 4 seviye yukarı
    __DIR__ . '/../../../wp-load.php',     // Plugin klasöründen 3 seviye yukarı
    __DIR__ . '/../../wp-load.php',        // Plugin klasöründen 2 seviye yukarı
    __DIR__ . '/../wp-load.php',           // Plugin klasöründen 1 seviye yukarı
    __DIR__ . '/wp-load.php'               // Aynı klasörde
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('WordPress yüklenemedi. wp-load.php dosyası bulunamadı.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Not Tablosu Veritabanı Kontrolü</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0f0f0; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        pre { white-space: pre-wrap; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Not Tablosu Veritabanı Kontrolü</h1>
        
        <?php
        global $wpdb;
        
        // Tablo isimlerini tanımla
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $users_table = $wpdb->prefix . 'users';
        
        echo '<div class="section info">';
        echo '<h2>📊 Genel Bilgiler</h2>';
        echo '<p><strong>WordPress DB Prefix:</strong> ' . $wpdb->prefix . '</p>';
        echo '<p><strong>Not Tablosu:</strong> ' . $notes_table . '</p>';
        echo '<p><strong>Görev Tablosu:</strong> ' . $tasks_table . '</p>';
        echo '<p><strong>Kullanıcı Tablosu:</strong> ' . $users_table . '</p>';
        echo '</div>';
        
        // Tablo var mı kontrol et
        echo '<div class="section">';
        echo '<h2>🏗️ Tablo Varlığı Kontrolü</h2>';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'") == $notes_table;
        if ($table_exists) {
            echo '<div class="success">✅ Not tablosu mevcut: ' . $notes_table . '</div>';
        } else {
            echo '<div class="error">❌ Not tablosu bulunamadı: ' . $notes_table . '</div>';
            echo '</div></div></body></html>';
            exit;
        }
        
        $tasks_exists = $wpdb->get_var("SHOW TABLES LIKE '$tasks_table'") == $tasks_table;
        if ($tasks_exists) {
            echo '<div class="success">✅ Görev tablosu mevcut: ' . $tasks_table . '</div>';
        } else {
            echo '<div class="warning">⚠️ Görev tablosu bulunamadı: ' . $tasks_table . '</div>';
        }
        echo '</div>';
        
        // Tablo yapısını göster
        echo '<div class="section">';
        echo '<h2>🏗️ Not Tablosu Yapısı</h2>';
        $columns = $wpdb->get_results("DESCRIBE $notes_table");
        if ($columns) {
            echo '<table>';
            echo '<tr><th>Sütun</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            foreach ($columns as $column) {
                echo '<tr>';
                echo '<td>' . $column->Field . '</td>';
                echo '<td>' . $column->Type . '</td>';
                echo '<td>' . $column->Null . '</td>';
                echo '<td>' . $column->Key . '</td>';
                echo '<td>' . $column->Default . '</td>';
                echo '<td>' . $column->Extra . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="error">❌ Tablo yapısı alınamadı</div>';
        }
        echo '</div>';
        
        // Not sayısını göster
        echo '<div class="section">';
        echo '<h2>📊 Not İstatistikleri</h2>';
        
        $total_notes = $wpdb->get_var("SELECT COUNT(*) FROM $notes_table");
        $main_notes = $wpdb->get_var("SELECT COUNT(*) FROM $notes_table WHERE parent_note_id IS NULL OR parent_note_id = 0");
        $reply_notes = $wpdb->get_var("SELECT COUNT(*) FROM $notes_table WHERE parent_note_id IS NOT NULL AND parent_note_id > 0");
        
        echo '<p><strong>Toplam Not Sayısı:</strong> ' . $total_notes . '</p>';
        echo '<p><strong>Ana Not Sayısı:</strong> ' . $main_notes . '</p>';
        echo '<p><strong>Cevap Not Sayısı:</strong> ' . $reply_notes . '</p>';
        echo '</div>';
        
        // Son eklenen notları göster
        echo '<div class="section">';
        echo '<h2>🕐 Son Eklenen Notlar (Son 10)</h2>';
        
        $recent_notes = $wpdb->get_results("
            SELECT n.*, u.display_name as user_name, t.baslik as task_title
            FROM $notes_table n
            LEFT JOIN $users_table u ON n.user_id = u.ID
            LEFT JOIN $tasks_table t ON n.task_id = t.id
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        
        if ($recent_notes) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Görev</th><th>Kullanıcı</th><th>İçerik</th><th>Parent ID</th><th>Tarih</th></tr>';
            foreach ($recent_notes as $note) {
                echo '<tr>';
                echo '<td>' . $note->id . '</td>';
                echo '<td>' . ($note->task_title ?: 'ID: ' . $note->task_id) . '</td>';
                echo '<td>' . ($note->user_name ?: 'ID: ' . $note->user_id) . '</td>';
                echo '<td>' . substr($note->content, 0, 50) . '...</td>';
                echo '<td>' . ($note->parent_note_id ?: '-') . '</td>';
                echo '<td>' . $note->created_at . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="warning">⚠️ Hiç not bulunamadı</div>';
        }
        echo '</div>';
        
        // Test için uygun notları göster
        echo '<div class="section">';
        echo '<h2>🎯 Test İçin Uygun Ana Notlar</h2>';
        
        $test_notes = $wpdb->get_results("
            SELECT n.*, u.display_name as user_name, t.baslik as task_title
            FROM $notes_table n
            LEFT JOIN $users_table u ON n.user_id = u.ID
            LEFT JOIN $tasks_table t ON n.task_id = t.id
            WHERE (n.parent_note_id IS NULL OR n.parent_note_id = 0)
            ORDER BY n.created_at DESC
            LIMIT 5
        ");
        
        if ($test_notes) {
            echo '<p>Bu ana notlara cevap verebilirsiniz:</p>';
            echo '<table>';
            echo '<tr><th>Note ID</th><th>Task ID</th><th>Görev</th><th>İçerik</th><th>Test URL</th></tr>';
            foreach ($test_notes as $note) {
                $test_url = "test-note-reply-debug.html?task_id=" . $note->task_id . "&parent_note_id=" . $note->id;
                echo '<tr>';
                echo '<td>' . $note->id . '</td>';
                echo '<td>' . $note->task_id . '</td>';
                echo '<td>' . ($note->task_title ?: 'Bilinmiyor') . '</td>';
                echo '<td>' . substr($note->content, 0, 40) . '...</td>';
                echo '<td><a href="' . $test_url . '" target="_blank">Test Et</a></td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="warning">⚠️ Test için uygun ana not bulunamadı</div>';
        }
        echo '</div>';
        
        // Manuel test komutu
        echo '<div class="section info">';
        echo '<h2>🧪 Manuel Test Komutu</h2>';
        echo '<div class="code">';
        echo '<pre>';
        echo "// Test için WordPress konsol'da çalıştırabilirsiniz:\n";
        echo '$wpdb->insert(\'' . $notes_table . '\', array(' . "\n";
        echo '    \'task_id\' => 1,' . "\n";
        echo '    \'user_id\' => ' . get_current_user_id() . ',' . "\n";
        echo '    \'content\' => \'Test ana notu\',' . "\n";
        echo '    \'parent_note_id\' => null,' . "\n";
        echo '    \'created_at\' => current_time(\'mysql\')' . "\n";
        echo '));' . "\n\n";
        echo '// Cevap notu eklemek için:' . "\n";
        echo '$wpdb->insert(\'' . $notes_table . '\', array(' . "\n";
        echo '    \'task_id\' => 1,' . "\n";
        echo '    \'user_id\' => ' . get_current_user_id() . ',' . "\n";
        echo '    \'content\' => \'Test cevap notu\',' . "\n";
        echo '    \'parent_note_id\' => 1, // Ana notun ID\'si' . "\n";
        echo '    \'created_at\' => current_time(\'mysql\')' . "\n";
        echo '));';
        echo '</pre>';
        echo '</div>';
        echo '</div>';
        ?>
        
        <div class="section">
            <h2>🔧 Debug İpuçları</h2>
            <ul>
                <li><strong>WordPress Error Logging:</strong> wp-config.php'de <code>define('WP_DEBUG_LOG', true);</code> aktif olmalı</li>
                <li><strong>Log Dosyası:</strong> /wp-content/debug.log dosyasını kontrol edin</li>
                <li><strong>AJAX URL:</strong> <?php echo admin_url('admin-ajax.php'); ?></li>
                <li><strong>Nonce:</strong> Giriş yapmış kullanıcının nonce'unu almanız gerekiyor</li>
                <li><strong>Browser Console:</strong> F12 açıp JavaScript hatalarını kontrol edin</li>
            </ul>
        </div>
    </div>
</body>
</html>
