<?php
/**
 * Test sayfası - Kullanıcı yetkileri kontrolü
 * Bu dosyayı WordPress'te çalıştırarak mevcut durumu görebilirsiniz
 */

// WordPress yükle
require_once('../../../../wp-config.php');

// Eğer login değilse redirect et
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kullanıcı Yetkileri Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        .test-section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #007cba; }
        .success { color: #28a745; }
        .danger { color: #dc3545; }
        .warning { color: #fd7e14; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .role-admin { background: #d4edda; }
        .role-editor { background: #fff3cd; }
        .role-other { background: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Kullanıcı Yetkileri Test Sayfası</h1>
        
        <div class="test-section">
            <h3>Mevcut Kullanıcı Bilgileri</h3>
            <table>
                <tr><th>Özellik</th><th>Değer</th></tr>
                <tr><td>Kullanıcı Adı</td><td><?php echo $current_user->display_name; ?></td></tr>
                <tr><td>Kullanıcı ID</td><td><?php echo $current_user->ID; ?></td></tr>
                <tr><td>E-posta</td><td><?php echo $current_user->user_email; ?></td></tr>
                <tr><td>Roller</td><td><?php echo implode(', ', $current_user->roles); ?></td></tr>
                <tr><td>İlk Rol</td><td class="role-<?php echo $current_user->roles[0]; ?>"><?php echo $current_user->roles[0]; ?></td></tr>
            </table>
        </div>

        <div class="test-section">
            <h3>WordPress Capabilities Testi</h3>
            <table>
                <tr><th>Capability</th><th>Durum</th><th>Açıklama</th></tr>
                <tr>
                    <td>manage_options</td>
                    <td class="<?php echo current_user_can('manage_options') ? 'success' : 'danger'; ?>">
                        <?php echo current_user_can('manage_options') ? '✅ VAR' : '❌ YOK'; ?>
                    </td>
                    <td>WordPress admin paneline erişim</td>
                </tr>
                <tr>
                    <td>create_users</td>
                    <td class="<?php echo current_user_can('create_users') ? 'success' : 'danger'; ?>">
                        <?php echo current_user_can('create_users') ? '✅ VAR' : '❌ YOK'; ?>
                    </td>
                    <td>Yeni kullanıcı ekleme yetkisi</td>
                </tr>
                <tr>
                    <td>edit_users</td>
                    <td class="<?php echo current_user_can('edit_users') ? 'success' : 'danger'; ?>">
                        <?php echo current_user_can('edit_users') ? '✅ VAR' : '❌ YOK'; ?>
                    </td>
                    <td>Kullanıcıları düzenleme yetkisi</td>
                </tr>
                <tr>
                    <td>delete_users</td>
                    <td class="<?php echo current_user_can('delete_users') ? 'success' : 'danger'; ?>">
                        <?php echo current_user_can('delete_users') ? '✅ VAR' : '❌ YOK'; ?>
                    </td>
                    <td>Kullanıcıları silme yetkisi</td>
                </tr>
                <tr>
                    <td>list_users</td>
                    <td class="<?php echo current_user_can('list_users') ? 'success' : 'danger'; ?>">
                        <?php echo current_user_can('list_users') ? '✅ VAR' : '❌ YOK'; ?>
                    </td>
                    <td>Kullanıcı listesini görme yetkisi</td>
                </tr>
            </table>
        </div>

        <div class="test-section">
            <h3>Rol Tabanlı Buton Kontrolü</h3>
            <?php $is_admin = in_array('administrator', $current_user->roles); ?>
            <?php $is_editor = in_array('editor', $current_user->roles); ?>
            
            <p><strong>Rol Durumu:</strong></p>
            <ul>
                <li>Admin mi?: <?php echo $is_admin ? '<span class="success">✅ EVET</span>' : '<span class="danger">❌ HAYIR</span>'; ?></li>
                <li>Editor mi?: <?php echo $is_editor ? '<span class="warning">⚠️ EVET</span>' : '<span class="success">✅ HAYIR</span>'; ?></li>
            </ul>
            
            <p><strong>Beklenen Buton Davranışı:</strong></p>
            <?php if ($is_admin): ?>
                <div class="success">
                    ✅ <strong>ADMİN KULLANICISI:</strong> Düzenle ve Sil butonları GÖRÜNMELİ
                </div>
                <button style="background: #007cba; color: white; padding: 8px 15px; border: none; border-radius: 4px; margin: 5px;">
                    ✏️ Düzenle (Admin)
                </button>
                <button style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 4px; margin: 5px;">
                    🗑️ Sil (Admin)
                </button>
            <?php elseif ($is_editor): ?>
                <div class="warning">
                    ⚠️ <strong>EDITÖR KULLANICISI:</strong> Düzenle ve Sil butonları GÖRÜNMEMELİ
                </div>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0;">
                    🚫 Editör kullanıcısı sadece yeni kullanıcı ekleyebilir. Düzenleme/silme yetkisi bulunmamaktadır.
                </div>
            <?php else: ?>
                <div class="danger">
                    ❌ <strong>DİĞER ROLLER:</strong> Hiçbir buton görünmemeli
                </div>
            <?php endif; ?>
        </div>

        <div class="test-section">
            <h3>CSS Class Test</h3>
            <p>Body class'ında olması gereken değerler:</p>
            <ul>
                <li><code>user-role-<?php echo implode(' user-role-', $current_user->roles); ?></code></li>
                <li><code>user-<?php echo $current_user->roles[0] ?? 'guest'; ?></code></li>
            </ul>
        </div>

        <div class="test-section">
            <h3>Kullanıcı Listesi</h3>
            <?php 
            $users = get_users(array('meta_key' => 'wp_capabilities', 'meta_compare' => 'EXISTS'));
            ?>
            <p><strong>Toplam <?php echo count($users); ?> kullanıcı bulundu:</strong></p>
            <table>
                <tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Buton Durumu</th></tr>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user->display_name; ?></td>
                    <td><?php echo $user->user_email; ?></td>
                    <td class="role-<?php echo $user->roles[0]; ?>"><?php echo implode(', ', $user->roles); ?></td>
                    <td>
                        <?php if ($is_admin): ?>
                            <button style="background: #007cba; color: white; padding: 4px 8px; border: none; border-radius: 3px; margin: 2px; font-size: 12px;">✏️ Düzenle</button>
                            <?php if ($user->ID != $current_user->ID): ?>
                            <button style="background: #dc3545; color: white; padding: 4px 8px; border: none; border-radius: 3px; margin: 2px; font-size: 12px;">🗑️ Sil</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #666; font-style: italic;">Yetki yok</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div style="margin: 30px 0; text-align: center;">
            <a href="frontend/ayarlar.php" style="background: #007cba; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">
                🔙 Ayarlar Sayfasına Geri Dön
            </a>
        </div>
    </div>
</body>
</html>
