<?php
/**
 * BKM Aksiyon Takip - Action Progress Debug Test
 * Bu dosya doğrudan çalıştırılarak backend fonksiyonlarını test eder
 */

// WordPress'i yükle
require_once('../../../wp-config.php');
require_once('../../../wp-load.php');

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 4px;'>";
    echo "⚠️ UYARI: WP_DEBUG aktif değil. wp-config.php dosyasında WP_DEBUG'ı true yapın.";
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BKM Action Progress - Backend Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        button { background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .status-open { color: #6c757d; }
        .status-active { color: #007bff; }
        .status-completed { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 BKM Action Progress - Backend Debug Test</h1>
        
        <?php
        global $wpdb;
        
        // BKM plugin sınıfına erişim
        $bkm_plugin = BKM_Aksiyon_Takip::get_instance();
        
        echo '<div class="section info">';
        echo '<h3>📊 Veritabanı Durum Kontrolü</h3>';
        
        // Actions tablosu kontrol
        $actions_table = $wpdb->prefix . 'bkm_actions';
        $tasks_table = $wpdb->prefix . 'bkm_tasks';
        $notes_table = $wpdb->prefix . 'bkm_task_notes';
        
        $actions_exists = $wpdb->get_var("SHOW TABLES LIKE '$actions_table'") === $actions_table;
        $tasks_exists = $wpdb->get_var("SHOW TABLES LIKE '$tasks_table'") === $tasks_table;
        $notes_exists = $wpdb->get_var("SHOW TABLES LIKE '$notes_table'") === $notes_table;
        
        echo "<p><strong>Tablo Durumları:</strong></p>";
        echo "<ul>";
        echo "<li>bkm_actions: " . ($actions_exists ? '✅ Mevcut' : '❌ Eksik') . "</li>";
        echo "<li>bkm_tasks: " . ($tasks_exists ? '✅ Mevcut' : '❌ Eksik') . "</li>";
        echo "<li>bkm_task_notes: " . ($notes_exists ? '✅ Mevcut' : '❌ Eksik') . "</li>";
        echo "</ul>";
        echo '</div>';
        
        if (!$actions_exists || !$tasks_exists || !$notes_exists) {
            echo '<div class="section error">';
            echo '<h3>❌ Kritik Hata</h3>';
            echo '<p>Gerekli veritabanı tabloları eksik. Plugin aktivasyonunu yeniden çalıştırın.</p>';
            echo '</div>';
            echo '</div></body></html>';
            exit;
        }
        
        // Mevcut aksiyonları listele
        $actions = $wpdb->get_results("SELECT * FROM $actions_table ORDER BY id DESC LIMIT 10");
        
        echo '<div class="section">';
        echo '<h3>📋 Son 10 Aksiyon</h3>';
        if (empty($actions)) {
            echo '<p class="warning">⚠️ Henüz aksiyon bulunamadı. Önce bazı aksiyonlar oluşturun.</p>';
        } else {
            echo '<table>';
            echo '<tr><th>ID</th><th>Başlık</th><th>İlerleme</th><th>Durum</th><th>Görev Sayısı</th><th>Action</th></tr>';
            
            foreach ($actions as $action) {
                $task_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tasks_table WHERE action_id = %d", $action->id));
                $status_class = 'status-' . ($action->status ?? 'active');
                
                echo '<tr>';
                echo '<td>' . $action->id . '</td>';
                echo '<td>' . esc_html($action->tespit_konusu ? substr($action->tespit_konusu, 0, 50) . '...' : $action->title ?? 'Başlıksız') . '</td>';
                echo '<td>' . $action->ilerleme_durumu . '%</td>';
                echo '<td class="' . $status_class . '">' . ($action->status ?? 'active') . '</td>';
                echo '<td>' . $task_count . '</td>';
                echo '<td><button onclick="testActionProgress(' . $action->id . ')">Test Et</button></td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</div>';
        
        // Test aksiyon görevlerini listele
        if (!empty($actions)) {
            $first_action = $actions[0];
            $tasks = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tasks_table WHERE action_id = %d ORDER BY id DESC", 
                $first_action->id
            ));
            
            echo '<div class="section">';
            echo '<h3>🎯 Aksiyon #' . $first_action->id . ' Görevleri</h3>';
            
            if (empty($tasks)) {
                echo '<p class="warning">⚠️ Bu aksiyonda henüz görev yok.</p>';
            } else {
                echo '<table>';
                echo '<tr><th>ID</th><th>İçerik</th><th>İlerleme</th><th>Test</th></tr>';
                
                foreach ($tasks as $task) {
                    echo '<tr>';
                    echo '<td>' . $task->id . '</td>';
                    echo '<td>' . esc_html(substr($task->content, 0, 50)) . '...</td>';
                    echo '<td>' . $task->ilerleme_durumu . '%</td>';
                    echo '<td><button onclick="testTaskProgressUpdate(' . $task->id . ')">İlerleme Test</button></td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Manuel aksiyon ilerleme hesaplama testi
                $total_progress = 0;
                $task_count = count($tasks);
                foreach ($tasks as $task) {
                    $total_progress += intval($task->ilerleme_durumu);
                }
                $calculated_average = $task_count > 0 ? round($total_progress / $task_count) : 0;
                
                echo '<div class="info">';
                echo '<h4>📊 Manuel İlerleme Hesaplama</h4>';
                echo '<p><strong>Görev Sayısı:</strong> ' . $task_count . '</p>';
                echo '<p><strong>Toplam İlerleme:</strong> ' . $total_progress . '</p>';
                echo '<p><strong>Hesaplanan Ortalama:</strong> ' . $calculated_average . '%</p>';
                echo '<p><strong>Mevcut Aksiyon İlerlemesi:</strong> ' . $first_action->ilerleme_durumu . '%</p>';
                
                if ($calculated_average != $first_action->ilerleme_durumu) {
                    echo '<p class="error"><strong>⚠️ UYARI:</strong> Hesaplanan ile mevcut ilerleme farklı! Güncelleme gerekli.</p>';
                    echo '<button onclick="fixActionProgress(' . $first_action->id . ')">🔧 Aksiyon İlerlemesini Düzelt</button>';
                } else {
                    echo '<p class="success">✅ Aksiyon ilerlemesi doğru hesaplanmış.</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h3>🧪 Manual Test Fonksiyonları</h3>
            <button onclick="testAllActionProgress()">🔧 Tüm Aksiyon İlerlemelerini Düzelt</button>
            <button onclick="simulateNoteAdd()">📝 Not Ekleme Simüle Et</button>
            <button onclick="checkDebugLogs()">📋 Debug Loglarını Kontrol Et</button>
        </div>
        
        <div class="section">
            <h3>📋 Test Sonuçları</h3>
            <div id="test-results">
                <p>Test sonuçları burada görünecek...</p>
            </div>
        </div>
    </div>

    <script>
        function showResult(message, type = 'info') {
            const resultDiv = document.getElementById('test-results');
            const timestamp = new Date().toLocaleTimeString();
            const typeClass = type === 'error' ? 'error' : type === 'success' ? 'success' : 'info';
            
            resultDiv.innerHTML += `
                <div class="${typeClass}" style="margin: 10px 0; padding: 10px; border-radius: 4px;">
                    <strong>[${timestamp}]</strong> ${message}
                </div>
            `;
            resultDiv.scrollTop = resultDiv.scrollHeight;
        }

        function testActionProgress(actionId) {
            showResult(`🧪 Aksiyon #${actionId} ilerleme testi başlatılıyor...`);
            
            // AJAX ile backend test fonksiyonunu çağır
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bkm_test_action_progress&action_id=${actionId}&nonce=<?php echo wp_create_nonce('bkm_test_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Aksiyon #${actionId} ilerleme testi tamamlandı: ${data.data.message}`, 'success');
                } else {
                    showResult(`❌ Aksiyon #${actionId} test hatası: ${data.data}`, 'error');
                }
            })
            .catch(error => {
                showResult(`❌ AJAX hatası: ${error}`, 'error');
            });
        }

        function testTaskProgressUpdate(taskId) {
            const newProgress = prompt('Yeni ilerleme yüzdesini girin (0-100):', '50');
            if (newProgress === null) return;
            
            const progress = parseInt(newProgress);
            if (isNaN(progress) || progress < 0 || progress > 100) {
                showResult('❌ Geçersiz ilerleme yüzdesi!', 'error');
                return;
            }
            
            showResult(`🧪 Görev #${taskId} ilerleme güncelleme testi (${progress}%) başlatılıyor...`);
            
            // Simulate note add with progress
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bkm_add_note&task_id=${taskId}&content=Test note for progress update&progress=${progress}&nonce=<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Görev #${taskId} ilerleme güncelleme başarılı`, 'success');
                    if (data.data.action_progress_updated) {
                        showResult(`🎯 Aksiyon ilerlemesi güncellendi: ${data.data.new_action_progress}%`, 'success');
                    }
                    // Sayfayı yenile
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showResult(`❌ Görev #${taskId} ilerleme güncelleme hatası: ${data.data}`, 'error');
                }
            })
            .catch(error => {
                showResult(`❌ AJAX hatası: ${error}`, 'error');
            });
        }

        function fixActionProgress(actionId) {
            showResult(`🔧 Aksiyon #${actionId} ilerleme düzeltme başlatılıyor...`);
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bkm_fix_single_action_progress&action_id=${actionId}&nonce=<?php echo wp_create_nonce('bkm_test_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Aksiyon #${actionId} ilerleme düzeltme tamamlandı: ${data.data.message}`, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showResult(`❌ Aksiyon #${actionId} düzeltme hatası: ${data.data}`, 'error');
                }
            })
            .catch(error => {
                showResult(`❌ AJAX hatası: ${error}`, 'error');
            });
        }

        function testAllActionProgress() {
            showResult('🔧 Tüm aksiyon ilerlemeleri düzeltiliyor...');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bkm_fix_action_statuses&nonce=<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Tüm aksiyon ilerlemeleri düzeltildi: ${data.data.message}`, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showResult(`❌ Toplu düzeltme hatası: ${data.data}`, 'error');
                }
            })
            .catch(error => {
                showResult(`❌ AJAX hatası: ${error}`, 'error');
            });
        }

        function simulateNoteAdd() {
            showResult('📝 Not ekleme simülasyonu başlatılıyor...');
            // Bu fonksiyon geliştirilecek
        }

        function checkDebugLogs() {
            showResult('📋 Debug logları kontrol ediliyor...');
            showResult('💡 WordPress debug.log dosyasını manuel olarak kontrol edin: /wp-content/debug.log', 'info');
        }

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            showResult('🚀 BKM Action Progress Backend Debug Test başlatıldı', 'success');
        });
    </script>
</body>
</html>
