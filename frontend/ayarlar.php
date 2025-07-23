<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'frontend/login.php';
    return;
}

// Bu kontrol kaldırıldı - editörlerin de ayarlar sayfasına erişebilmesi için
// if (!current_user_can('manage_options')) {
//     echo '<div class="bkm-error">Bu sayfaya erişim yetkiniz bulunmamaktadır.</div>';
//     return;
// }

// Handle logout
if (isset($_GET['bkm_logout'])) {
    wp_logout();
    global $wp;
    wp_safe_redirect(home_url(add_query_arg(array(), $wp->request)));
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();

// Get data for settings
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performances';

// Get categories
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");

// Get performances
$performances = $wpdb->get_results("SELECT * FROM $performance_table ORDER BY name ASC");

// Get users
$users = get_users(array(
    'meta_key' => 'wp_capabilities',
    'meta_compare' => 'EXISTS'
));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - BKM Aksiyon Takip</title>
      <!-- WordPress Styles -->
    <?php wp_head(); ?>
    
    <!-- Plugin Styles -->
    <link rel="stylesheet" href="<?php echo BKM_AKSIYON_TAKIP_PLUGIN_URL . 'assets/css/frontend.css'; ?>">
      <!-- jQuery First -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>    <!-- Minimal CSS -->
    <style>
        /* SADECE Admin olmayan kullanıcılar için butonları gizle */
        body:not(.user-administrator) .admin-only-button,
        body:not(.user-administrator) .bkm-btn-danger,
        body:not(.user-administrator) button[onclick*="editUser"],
        body:not(.user-administrator) button[onclick*="deleteUser"],
        body:not(.user-administrator) button[onclick*="edit"],
        body:not(.user-administrator) button[onclick*="delete"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
        }
        
        /* SADECE Administrator için butonları AÇIKÇA göster */
        body.user-administrator .admin-only-button,
        body.user-administrator .bkm-btn-danger {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            height: auto !important;
            width: auto !important;
            margin: revert !important;
            padding: revert !important;
            border: revert !important;
        }
        
        /* Debug kutularının stili */
        .debug-box {
            border-radius: 8px;
            margin: 15px 0;
            padding: 20px;
        }
    </style>
</head>
<body class="bkm-settings-page user-<?php echo $current_user->roles[0] ?? 'guest'; ?>">    <div class="bkm-container">
        
        <!-- GLOBAL DEBUG BİLGİLERİ -->
        <div style="background: #f8f9fa; border: 2px solid #007cba; color: #333; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #007cba;">🔍 SİSTEM DEBUG BİLGİLERİ</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <strong>Kullanıcı Bilgileri:</strong><br>
                    • Ad: <?php echo $current_user->display_name ?? 'N/A'; ?><br>
                    • ID: <?php echo $current_user->ID ?? 'N/A'; ?><br>
                    • E-posta: <?php echo $current_user->user_email ?? 'N/A'; ?><br>
                    • Roller: <?php echo is_array($current_user->roles) ? implode(', ', $current_user->roles) : 'YOK'; ?><br>
                </div>
                <div>
                    <strong>Rol Kontrolleri:</strong><br>
                    • Admin mi?: <?php echo (is_array($current_user->roles) && in_array('administrator', $current_user->roles)) ? '✅ EVET' : '❌ HAYIR'; ?><br>
                    • Editor mi?: <?php echo (is_array($current_user->roles) && in_array('editor', $current_user->roles)) ? '✅ EVET' : '❌ HAYIR'; ?><br>
                    • Body Class: user-<?php echo $current_user->roles[0] ?? 'guest'; ?><br>
                </div>
            </div>
            <div style="margin-top: 15px; padding: 10px; background: #e9ecef; border-radius: 4px;">
                <strong>WordPress Capabilities:</strong><br>
                • manage_options: <?php echo current_user_can('manage_options') ? '✅ YES' : '❌ NO'; ?> | 
                • edit_users: <?php echo current_user_can('edit_users') ? '✅ YES' : '❌ NO'; ?> | 
                • delete_users: <?php echo current_user_can('delete_users') ? '✅ YES' : '❌ NO'; ?> | 
                • create_users: <?php echo current_user_can('create_users') ? '✅ YES' : '❌ NO'; ?>
            </div>
        </div>
        
        <!-- Header -->
        <div class="bkm-header">
            <div class="bkm-header-content">                <div class="bkm-header-left">
                    <h1>⚙️ Sistem Ayarları</h1>
                    <p>Kategoriler, performanslar ve kullanıcıları yönetin</p>
                    <!-- Debug: Kullanıcı Rolü -->
                    <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
                        <strong>� DEBUG BİLGİLERİ:</strong><br>
                        Kullanıcı Adı: <?php echo $current_user->display_name; ?><br>
                        Kullanıcı ID: <?php echo $current_user->ID; ?><br>
                        Roller: <?php echo implode(', ', $current_user->roles); ?><br>
                        Admin mi?: <?php echo in_array('administrator', $current_user->roles) ? 'EVET' : 'HAYIR'; ?><br>
                        Editor mi?: <?php echo in_array('editor', $current_user->roles) ? 'EVET' : 'HAYIR'; ?>
                    </div>
                </div>
                <div class="bkm-header-right">
                    <button class="bkm-btn bkm-btn-secondary" onclick="window.location.href='?page=dashboard'">
                        ← Ana Sayfaya Dön
                    </button>
                    <div class="bkm-user-info">
                        <span>👤 <?php echo esc_html($current_user->display_name); ?></span>
                        <a href="?bkm_logout=1" class="bkm-logout-link">🚪 Çıkış</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="bkm-settings-container">
            
            <!-- Settings Navigation Tabs -->
            <div class="bkm-settings-tabs">
                <button class="settings-tab active" data-tab="categories" onclick="switchSettingsTab('categories')">
                    🏷️ Kategoriler
                </button>
                <button class="settings-tab" data-tab="performances" onclick="switchSettingsTab('performances')">
                    📊 Performanslar
                </button>
                <button class="settings-tab" data-tab="users" onclick="switchSettingsTab('users')">
                    👥 Kullanıcılar
                </button>
            </div>
            
            <!-- Categories Tab -->
            <div id="settings-tab-categories" class="bkm-settings-tab-content active">
                <div class="bkm-management-grid">
                    <!-- Add Category Form -->
                    <div class="bkm-management-form">
                        <h4>Yeni Kategori Ekle</h4>
                        <form id="bkm-category-form-element">
                            <div class="bkm-field">
                                <label for="category_name">Kategori Adı <span class="required">*</span>:</label>
                                <input type="text" name="name" id="category_name" required placeholder="Kategori adını girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="category_description">Açıklama:</label>
                                <textarea name="description" id="category_description" rows="3" placeholder="Kategori açıklamasını girin (isteğe bağlı)"></textarea>
                            </div>
                            <div class="bkm-form-actions">
                                <button type="submit" class="bkm-btn bkm-btn-primary">✅ Kategori Ekle</button>
                                <button type="button" class="bkm-btn bkm-btn-secondary" onclick="clearCategoryForm()">🔄 Temizle</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Categories List -->
                    <div class="bkm-management-list">
                        <h4>Mevcut Kategoriler</h4>
                        <div id="categories-list" class="bkm-items-list">
                            <?php foreach ($categories as $category): ?>
                                <div class="bkm-item" data-id="<?php echo $category->id; ?>">
                                    <div class="bkm-item-content">
                                        <strong><?php echo esc_html($category->name); ?></strong>
                                        <?php if ($category->description): ?>
                                            <p><?php echo esc_html($category->description); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bkm-item-actions">
                                        <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editCategory(<?php echo $category->id; ?>, '<?php echo esc_js($category->name); ?>', '<?php echo esc_js($category->description); ?>')">
                                            ✏️ Düzenle
                                        </button>
                                        <button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deleteCategory(<?php echo $category->id; ?>)">
                                            🗑️ Sil
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performances Tab -->
            <div id="settings-tab-performances" class="bkm-settings-tab-content">
                <div class="bkm-management-grid">
                    <!-- Add Performance Form -->
                    <div class="bkm-management-form">
                        <h4>Yeni Performans Ekle</h4>
                        <form id="bkm-performance-form-element">
                            <div class="bkm-field">
                                <label for="performance_name">Performans Adı <span class="required">*</span>:</label>
                                <input type="text" name="name" id="performance_name" required placeholder="Performans adını girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="performance_description">Açıklama:</label>
                                <textarea name="description" id="performance_description" rows="3" placeholder="Performans açıklamasını girin (isteğe bağlı)"></textarea>
                            </div>
                            <div class="bkm-form-actions">
                                <button type="submit" class="bkm-btn bkm-btn-primary">✅ Performans Ekle</button>
                                <button type="button" class="bkm-btn bkm-btn-secondary" onclick="clearPerformanceForm()">🔄 Temizle</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Performances List -->
                    <div class="bkm-management-list">
                        <h4>Mevcut Performanslar</h4>
                        <div id="performances-list" class="bkm-items-list">
                            <?php foreach ($performances as $performance): ?>
                                <div class="bkm-item" data-id="<?php echo $performance->id; ?>">
                                    <div class="bkm-item-content">
                                        <strong><?php echo esc_html($performance->name); ?></strong>
                                        <?php if ($performance->description): ?>
                                            <p><?php echo esc_html($performance->description); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bkm-item-actions">
                                        <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editPerformance(<?php echo $performance->id; ?>, '<?php echo esc_js($performance->name); ?>', '<?php echo esc_js($performance->description); ?>')">
                                            ✏️ Düzenle
                                        </button>
                                        <button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deletePerformance(<?php echo $performance->id; ?>)">
                                            🗑️ Sil
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
              <!-- Users Tab -->
            <div id="settings-tab-users" class="bkm-settings-tab-content">
                <?php if (in_array('administrator', $current_user->roles) || in_array('editor', $current_user->roles)): ?>
                <div class="bkm-management-grid">
                    <!-- Add User Form -->
                    <div class="bkm-management-form">
                        <h4>Yeni Kullanıcı Ekle</h4>
                        <form id="bkm-user-form-element">
                            <div class="bkm-field">
                                <label for="user_username">Kullanıcı Adı <span class="required">*</span>:</label>
                                <input type="text" name="username" id="user_username" required placeholder="Kullanıcı adını girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="user_email">E-posta <span class="required">*</span>:</label>
                                <input type="email" name="email" id="user_email" required placeholder="E-posta adresini girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="user_first_name">Ad:</label>
                                <input type="text" name="first_name" id="user_first_name" placeholder="Adını girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="user_last_name">Soyad:</label>
                                <input type="text" name="last_name" id="user_last_name" placeholder="Soyadını girin" />
                            </div>
                            <div class="bkm-field">
                                <label for="user_role">Rol <span class="required">*</span>:</label>
                                <select name="role" id="user_role" required>
                                    <option value="">Rol Seçin</option>
                                    <option value="administrator">Yönetici</option>
                                    <option value="editor">Editör</option>
                                    <option value="author">Yazar</option>
                                    <option value="contributor">Katkıda Bulunan</option>
                                    <option value="subscriber">Abone</option>
                                </select>
                            </div>
                            <div class="bkm-field">
                                <label for="user_password">Şifre <span class="required">*</span>:</label>
                                <input type="password" name="password" id="user_password" required placeholder="Şifre girin" />
                            </div>
                            <div class="bkm-form-actions">
                                <button type="submit" class="bkm-btn bkm-btn-primary">✅ Kullanıcı Ekle</button>
                                <button type="button" class="bkm-btn bkm-btn-secondary" onclick="clearUserForm()">🔄 Temizle</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Users List -->
                    <div class="bkm-management-list">
                        <h4>Mevcut Kullanıcılar</h4>
                        <div id="users-list" class="bkm-items-list">
                            <?php foreach ($users as $user): ?>
                                <div class="bkm-item" data-id="<?php echo $user->ID; ?>">
                                    <div class="bkm-item-content">
                                        <strong><?php echo esc_html($user->display_name); ?></strong>
                                        <p>
                                            <span class="bkm-user-email">📧 <?php echo esc_html($user->user_email); ?></span><br>
                                            <span class="bkm-user-role">👤 <?php echo esc_html(implode(', ', $user->roles)); ?></span><br>
                                            <span class="bkm-user-registered">📅 <?php echo date('d.m.Y', strtotime($user->user_registered)); ?></span>
                                        </p>                                    </div>                                    <div class="bkm-item-actions">
                                        <?php 
                                        // Basit rol kontrolü - sadece administrator rolü butonları görebilir
                                        $current_user_roles = $current_user->roles ?? array();
                                        $is_administrator = in_array('administrator', $current_user_roles);
                                        $is_editor = in_array('editor', $current_user_roles);
                                        ?>
                                        
                                        <?php if ($is_administrator): ?>
                                            <!-- SADECE ADMİNLER İÇİN BUTONLAR -->
                                            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin-bottom: 10px; border-radius: 4px;">
                                                <strong>👑 Yönetici Kullanıcısı:</strong> Tüm kullanıcı yönetimi yetkileriniz bulunmaktadır.
                                            </div>                                            <button class="bkm-btn bkm-btn-small bkm-btn-info admin-only-button" onclick="alert('✏️ Kullanıcı düzenleme özelliği yakında aktif olacak!')">
                                                ✏️ Düzenle
                                            </button>
                                            <?php if ($user->ID != $current_user->ID): ?>
                                                <button class="bkm-btn bkm-btn-small bkm-btn-danger admin-only-button" onclick="alert('🗑️ Kullanıcı silme özelliği yakında aktif olacak!')">
                                                    🗑️ Sil
                                                </button>
                                            <?php else: ?>
                                                <small style="color: #666; font-style: italic;">Kendi hesabınızı silemezsiniz</small>
                                            <?php endif; ?>
                                            
                                        <?php elseif ($is_editor): ?>
                                            <!-- EDITÖRLER İÇİN BİLGİ MESAJI -->
                                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 4px;">
                                                <strong>📝 Editör Kullanıcısı:</strong> Sadece yeni kullanıcı ekleyebilirsiniz. Mevcut kullanıcıları düzenleme/silme yetkiniz bulunmamaktadır.
                                            </div>
                                            
                                        <?php else: ?>
                                            <!-- DİĞER ROLLER İÇİN BİLGİ MESAJI -->
                                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;">
                                                <strong>🚫 Yetkisiz Erişim:</strong> Kullanıcı yönetimi için gerekli yetki bulunmamaktadır.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <div class="bkm-no-permission">
                        <p>🚫 Bu sayfaya erişim yetkiniz bulunmamaktadır. Kullanıcı yönetimi için Yönetici veya Editör rolüne sahip olmalısınız.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- MANUEL YETKİ SIFIRLAMA -->
    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 5px;">
        <h4>🔧 Rol Yetkilerini Sıfırla</h4>
        <p>Eğer butonlar hala görünüyorsa, aşağıdaki butona tıklayarak rol yetkilerini sıfırlayın:</p>
        <?php
        if (isset($_POST['reset_roles'])) {
            $plugin_instance = BKM_Aksiyon_Takip::get_instance();
            $plugin_instance->setup_role_capabilities();
            echo '<div style="background: #28a745; color: white; padding: 10px; border-radius: 3px; margin: 10px 0;">✅ Rol yetkileri başarıyla sıfırlandı! Sayfayı yenileyin.</div>';
        }
        ?>
        <form method="post" style="margin: 10px 0;">
            <button type="submit" name="reset_roles" value="1" style="background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                🔄 Rol Yetkilerini Şimdi Sıfırla
            </button>
        </form>
    </div>
            
        </div> <!-- End settings container -->
        
    </div> <!-- End bkm-container -->    <script>
        console.log('🔧 Ayarlar sayfası yüklendi - ROL TABANLI KONTROL AKTIF');
        
        // Sayfa yüklendiğinde rol tabanlı güvenlik kontrolü
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 DOM yüklendi, rol kontrolü başlatılıyor...');
            
            // Kullanıcı rolünü body class'ından al
            var bodyClasses = document.body.className;
            var isAdmin = bodyClasses.includes('user-administrator');
            
            console.log('🔍 Kullanıcı rol analizi:');
            console.log('   - Body classes:', bodyClasses);
            console.log('   - Admin kullanıcısı mı?', isAdmin);
              // Admin olmayan kullanıcılar için butonları gizle
            if (!isAdmin) {
                console.log('⚠️ Admin olmayan kullanıcı tespit edildi - güvenlik önlemleri aktifleştiriliyor...');
                
                // Tüm tehlikeli butonları bul
                var dangerousButtons = document.querySelectorAll(
                    '.admin-only-button, .bkm-btn-danger, ' +
                    'button[onclick*="edit"], button[onclick*="delete"], ' +
                    'button[onclick*="editUser"], button[onclick*="deleteUser"]'
                );
                
                console.log('🔒 Bulunan tehlikeli buton sayısı:', dangerousButtons.length);
                
                // Butonları tek tek kontrol et ve gizle
                dangerousButtons.forEach(function(button, index) {
                    var buttonText = button.textContent || button.innerText || 'Bilinmeyen buton';
                    console.log('   🚫 Buton ' + (index + 1) + ' gizleniyor: "' + buttonText.trim() + '"');
                    
                    // Butonu tamamen DOM'dan kaldır
                    button.style.display = 'none';
                    button.style.visibility = 'hidden';
                    button.style.opacity = '0';
                    button.style.pointerEvents = 'none';
                    button.remove();
                });
                
                console.log('✅ Toplam ' + dangerousButtons.length + ' tehlikeli buton gizlendi/kaldırıldı');
                
            } else {
                console.log('👑 Admin kullanıcısı tespit edildi - tüm butonlar erişilebilir kalacak');
                
                // Admin için butonları sayalım (ama kaldırmayalım!)
                var adminButtons = document.querySelectorAll('.admin-only-button, .bkm-btn-danger');
                console.log('🔓 Admin için kullanılabilir buton sayısı:', adminButtons.length);
                
                // Admin butonlarının görünür olduğundan emin olalım
                adminButtons.forEach(function(button, index) {
                    var buttonText = button.textContent || button.innerText || 'Bilinmeyen buton';
                    console.log('   ✅ Admin buton ' + (index + 1) + ' korunuyor: "' + buttonText.trim() + '"');
                    
                    // Emin olmak için CSS'i override edelim
                    button.style.display = 'inline-block';
                    button.style.visibility = 'visible';
                    button.style.opacity = '1';
                    button.style.pointerEvents = 'auto';
                });
            }
        });
        
        // Tab switching function
        function switchSettingsTab(tabName) {
            console.log('📑 Tab değiştiriliyor:', tabName);
        }
        
        // Güvenlik fonksiyonları - Yetki kontrolü ile
        function editUser() { 
            var isAdmin = document.body.className.includes('user-administrator');
            if (!isAdmin) {
                console.warn('🚫 YETKİSİZ ERİŞİM: editUser fonksiyonu admin olmayan kullanıcı tarafından çağrıldı');
                alert('🚫 Bu işlem için yönetici yetkisi gereklidir!');
                return false;
            }
            console.log('✅ editUser çağrıldı - admin yetkisi mevcut'); 
            return false; 
        }
        
        function deleteUser() { 
            var isAdmin = document.body.className.includes('user-administrator');
            if (!isAdmin) {
                console.warn('🚫 YETKİSİZ ERİŞİM: deleteUser fonksiyonu admin olmayan kullanıcı tarafından çağrıldı');
                alert('🚫 Bu işlem için yönetici yetkisi gereklidir!');
                return false;
            }
            console.log('✅ deleteUser çağrıldı - admin yetkisi mevcut'); 
            return false; 
        }
        
        function editCategory() { return false; }
        function deleteCategory() { return false; }
        function editPerformance() { return false; }
        function deletePerformance() { return false; }
    </script>

    <?php wp_footer(); ?>
</body>
</html>
