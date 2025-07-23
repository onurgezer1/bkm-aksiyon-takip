<?php
// Debug için basit rol kontrol testi
if (!defined('ABSPATH')) {
    // WordPress yüklenmemişse manual yükle
    require_once('../../../../../../wp-config.php');
}

$current_user = wp_get_current_user();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rol Debug Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .debug-box { 
            background: #f0f0f0; 
            padding: 15px; 
            margin: 10px 0; 
            border-left: 4px solid #0073aa; 
        }
        .user-item { 
            border: 1px solid #ddd; 
            padding: 10px; 
            margin: 10px 0; 
            background: white; 
        }
    </style>
</head>
<body>
    <h1>🔍 Rol Debug Testi</h1>
    
    <div class="debug-box">
        <h3>Mevcut Kullanıcı Bilgileri:</h3>
        <strong>Kullanıcı Adı:</strong> <?php echo $current_user->display_name; ?><br>
        <strong>Kullanıcı ID:</strong> <?php echo $current_user->ID; ?><br>
        <strong>Login Adı:</strong> <?php echo $current_user->user_login; ?><br>
        <strong>E-posta:</strong> <?php echo $current_user->user_email; ?><br>
        <strong>Roller:</strong> <?php print_r($current_user->roles); ?><br>
        <strong>Administrator mi?:</strong> <?php echo in_array('administrator', $current_user->roles) ? 'EVET' : 'HAYIR'; ?><br>
        <strong>Editor mi?:</strong> <?php echo in_array('editor', $current_user->roles) ? 'EVET' : 'HAYIR'; ?><br>
    </div>

    <?php
    // Tüm kullanıcıları listele
    $users = get_users();
    ?>
    
    <h2>Tüm Kullanıcılar ve Buton Testi:</h2>
    
    <?php foreach ($users as $user): ?>
        <div class="user-item">
            <h4><?php echo $user->display_name; ?> (<?php echo $user->user_login; ?>)</h4>
            <p>Roller: <?php echo implode(', ', $user->roles); ?></p>
            
            <!-- Buton testi -->
            <div>
                <?php if (in_array('administrator', $current_user->roles)): ?>
                    <button style="background: green; color: white; padding: 5px 10px;">✏️ Düzenle (Admin)</button>
                    <?php if ($user->ID != $current_user->ID): ?>
                        <button style="background: red; color: white; padding: 5px 10px;">🗑️ Sil (Admin)</button>
                    <?php endif; ?>
                <?php elseif (in_array('editor', $current_user->roles)): ?>
                    <span style="color: #666; font-style: italic;">📝 Editör: Sadece kullanıcı ekleyebilirsiniz</span>
                <?php else: ?>
                    <span style="color: #999;">🚫 Bu işlemler için yetkiniz bulunmamaktadır</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="debug-box">
        <h3>PHP Bilgileri:</h3>
        <strong>PHP Sürümü:</strong> <?php echo phpversion(); ?><br>
        <strong>WordPress Sürümü:</strong> <?php echo get_bloginfo('version'); ?><br>
        <strong>Mevcut URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?><br>
    </div>

</body>
</html>
