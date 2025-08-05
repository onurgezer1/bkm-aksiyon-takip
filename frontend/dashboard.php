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

// Handle logout
if (isset($_GET['bkm_logout'])) {
    wp_logout();
    global $wp; // Global $wp nesnesini tanımla
    wp_safe_redirect(home_url(add_query_arg(array(), $wp->request)));
    exit;
}

// User is logged in, show dashboard
global $wpdb;

// Check if WordPress database is available
if (!$wpdb) {
    echo '<div class="bkm-error">Veritabanı bağlantısı mevcut değil. Lütfen WordPress yöneticinizle iletişime geçin.</div>';
    return;
}

$current_user = wp_get_current_user();

// Check if user has permission to view
if (!current_user_can('read')) {
    echo '<div class="bkm-error">Bu sayfaya erişim yetkiniz bulunmamaktadır.</div>';
    return;
}

// Force database schema update for troubleshooting
if (defined('WP_DEBUG') && WP_DEBUG) {
    global $bkm_aksiyon_takip;
    if ($bkm_aksiyon_takip && method_exists($bkm_aksiyon_takip, 'force_database_schema_update')) {
        $bkm_aksiyon_takip->force_database_schema_update();
        error_log('🔧 BKM Debug: Forced comprehensive database schema update from dashboard.php');
    }
}

// Get data
$actions_table = $wpdb->prefix . 'bkm_actions';
$tasks_table = $wpdb->prefix . 'bkm_tasks';
$notes_table = $wpdb->prefix . 'bkm_task_notes';
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performances';

// Check if required tables exist
$required_tables = array(
    'Aksiyonlar' => $actions_table,
    'Görevler' => $tasks_table,
    'Kategoriler' => $categories_table,
    'Performans' => $performance_table
);

$missing_tables = array();
foreach ($required_tables as $table_label => $table_name) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        $missing_tables[] = $table_label;
    }
}

if (!empty($missing_tables)) {
    echo '<div class="bkm-error">';
    echo '<h3>Veritabanı Tabloları Eksik</h3>';
    echo '<p>Aşağıdaki tablolar bulunamadı: <strong>' . implode(', ', $missing_tables) . '</strong></p>';
    echo '<p>Lütfen plugin\'i devre dışı bırakıp tekrar aktifleştirin veya sistem yöneticinizle iletişime geçin.</p>';
    echo '<style>.bkm-error { background: #ffebcd; border: 1px solid #ff9800; padding: 15px; margin: 15px 0; border-radius: 5px; }</style>';
    echo '</div>';
    return;
}

// Determine SQL query based on user role - CONSISTENT WITH AJAX
$user_roles = $current_user->roles;

// Force array if user_roles is not an array
if (!is_array($user_roles)) {
    $user_roles = array();
}

// More robust role checking - same as AJAX
$is_admin = in_array('administrator', $user_roles) || current_user_can('manage_options');
$is_editor = in_array('editor', $user_roles) || current_user_can('edit_others_posts');
$is_contributor = in_array('contributor', $user_roles);
$current_user_id = $current_user->ID;


// Debug: Test mode to see all actions regardless of user permissions
$debug_show_all_actions = defined('BKM_DEBUG_SHOW_ALL_ACTIONS') && BKM_DEBUG_SHOW_ALL_ACTIONS; // Can be enabled by adding define('BKM_DEBUG_SHOW_ALL_ACTIONS', true); to wp-config.php

if ($debug_show_all_actions || $is_admin || $is_editor) {
    $actions_query = "SELECT a.*, 
                            COALESCE(u.display_name, 'Bilinmiyor') as tanımlayan_name,
                            c.name as kategori_name,
                            p.name as performans_name
                     FROM $actions_table a
                     LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
                     LEFT JOIN $categories_table c ON a.kategori_id = c.id
                     LEFT JOIN $performance_table p ON a.performans_id = p.id
                     ORDER BY a.created_at DESC";

} else {
    // Non-admins see actions they created OR are responsible for
    $actions_query = $wpdb->prepare(
        "SELECT a.*, 
                COALESCE(u.display_name, 'Bilinmiyor') as tanımlayan_name,
                c.name as kategori_name,
                p.name as performans_name
         FROM $actions_table a
         LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
         LEFT JOIN $categories_table c ON a.kategori_id = c.id
         LEFT JOIN $performance_table p ON a.performans_id = p.id
         WHERE (a.tanımlayan_id = %d OR a.sorumlu_ids LIKE %s)
         ORDER BY a.created_at DESC",
        $current_user_id,
        '%' . $wpdb->esc_like($current_user_id) . '%'
    );

}

$actions = $wpdb->get_results($actions_query);

// Get all users for JavaScript cache  
$all_users = $wpdb->get_results("
    SELECT u.ID, u.display_name, u.user_login, 
           m1.meta_value as first_name, 
           m2.meta_value as last_name
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->usermeta} m1 ON u.ID = m1.user_id AND m1.meta_key = 'first_name'
    LEFT JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'last_name'
    ORDER BY u.display_name
");
$users_for_js = array();
foreach ($all_users as $user) {
    $full_name = trim($user->first_name . ' ' . $user->last_name);
    $display_name = !empty($full_name) ? $full_name : $user->display_name;
    
    $users_for_js[$user->ID] = array(
        'id' => $user->ID,
        'display_name' => $display_name,
        'user_login' => $user->user_login,
        'first_name' => $user->first_name ?: '',
        'last_name' => $user->last_name ?: ''
    );
}



// Define display_notes function once at the top
function display_notes($notes, $parent_id = null, $level = 0, $is_admin_param = false, $task = null) {
    global $wpdb, $current_user_id;
    error_log("Displaying notes, is_admin_param: " . ($is_admin_param ? 'true' : 'false') . ", parent_id: " . $parent_id . ", note count: " . count($notes) . ", task_id: " . ($task ? $task->id : 'null'));
    foreach ($notes as $note) {
        if ($note->parent_note_id == $parent_id) {
            $reply_class = ($note->parent_note_id ? ' bkm-note-reply' : '');
            echo '<div class="bkm-note-item' . $reply_class . '" data-level="' . $level . '" data-note-id="' . $note->id . '">';
            echo '<div class="bkm-note-content">';
            echo '<p><strong>' . esc_html($note->user_name) . ':</strong> ' . esc_html($note->content) . '</p>';
            echo '<div class="bkm-note-meta">' . date('d.m.Y H:i', strtotime($note->created_at)) . '</div>';
            // All logged-in users can reply to notes
            if ($task) {
                echo '<button class="bkm-btn bkm-btn-small" onclick="toggleReplyForm(' . esc_js($task->id) . ', ' . esc_js($note->id) . ')">Notu Cevapla</button>';
                echo '<div id="reply-form-' . esc_attr($task->id) . '-' . esc_attr($note->id) . '" class="bkm-note-form" style="display: none;">';
                echo '<form class="bkm-reply-form" data-task-id="' . esc_attr($task->id) . '" data-parent-id="' . esc_attr($note->id) . '">';
                echo '<textarea name="note_content" rows="3" placeholder="Cevabınızı buraya yazın..." required></textarea>';
                echo '<div class="bkm-form-actions">';
                echo '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">Cevap Gönder</button>';
                echo '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleReplyForm(' . esc_js($task->id) . ', ' . esc_js($note->id) . ')">İptal</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            display_notes($notes, $note->id, $level + 1, $is_admin_param, $task); // Pass task recursively
        }
    }
}

// Handle task actions
if (isset($_POST['task_action']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action')) {
    if ($_POST['task_action'] === 'complete_task') {
        $task_id = intval($_POST['task_id']);
        
        // Check if user owns this task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d AND sorumlu_id = %d",
            $task_id, $current_user->ID
        ));
        
        if ($task) {
            $wpdb->update(
                $tasks_table,
                array(
                    'tamamlandi' => 1,
                    'ilerleme_durumu' => 100,
                    'gercek_bitis_tarihi' => current_time('mysql')
                ),
                array('id' => $task_id),
                array('%d', '%d', '%s'),
                array('%d')
            );
            
            // Send email notification
            $plugin = BKM_Aksiyon_Takip::get_instance();
            $notification_data = array(
                'content' => $task->content,
                'sorumlu' => $current_user->display_name,
                'tamamlanma_tarihi' => current_time('mysql')
            );
            
            $plugin->send_email_notification('task_completed', $notification_data);
            
            // Update action progress based on tasks average
            $plugin->update_action_progress($task->action_id);
            
            // Redirect to prevent form resubmission
            global $wp;
            wp_safe_redirect(home_url(add_query_arg(array('success' => 'task_completed'), $wp->request)));
            exit;
        }
    }
}

// Handle add task - DISABLED: Now using AJAX
/*
// OLD POST-based task adding - replaced with AJAX
if (isset($_POST['add_task']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action') && current_user_can('edit_posts')) {
    // ... old code moved to ajax_add_task() in bkm-aksiyon-takip.php
}
*/

// Handle add note - DISABLED: Now using AJAX
/*
if (isset($_POST['note_action']) && wp_verify_nonce($_POST['bkm_frontend_nonce'], 'bkm_frontend_action')) {
    if ($_POST['note_action'] === 'add_note' || $_POST['note_action'] === 'reply_note') {
        $task_id = intval($_POST['task_id']);
        $content = sanitize_textarea_field($_POST['note_content']);
        $parent_note_id = isset($_POST['parent_note_id']) ? intval($_POST['parent_note_id']) : null;
        
        error_log("Note action: " . $_POST['note_action'] . ", task_id: $task_id, parent_note_id: $parent_note_id, content: $content");

        // Check if user is authorized to add note
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tasks_table WHERE id = %d",
            $task_id
        ));
        
        if ($task && (($task->sorumlu_id == $current_user_id && $_POST['note_action'] === 'add_note') || $is_admin)) {
            if (!empty($content)) {
                $result = $wpdb->insert(
                    $notes_table,
                    array(
                        'task_id' => $task_id,
                        'user_id' => $current_user_id,
                        'content' => $content,
                        'parent_note_id' => $parent_note_id
                    ),
                    array('%d', '%d', '%s', '%d')
                );
                
                if ($result !== false) {
                    // Send email notification
                    $plugin = BKM_Aksiyon_Takip::get_instance();
                    $notification_data = array(
                        'content' => $content,
                        'action_id' => $task->action_id,
                        'task_id' => $task_id,
                        'sorumlu' => $current_user->display_name,
                        'sorumlu_emails' => array(get_user_by('ID', $task->sorumlu_id)->user_email)
                    );
                    
                    $plugin->send_email_notification($_POST['note_action'] === 'add_note' ? 'note_added' : 'note_replied', $notification_data);
                    
                    // Redirect to prevent form resubmission
                    global $wp;
                    wp_safe_redirect(home_url(add_query_arg(array('success' => 'note_added'), $wp->request)));
                    exit;
                } else {
                    echo '<div class="bkm-error">Not eklenirken bir hata oluştu.</div>';
                }
            } else {
                echo '<div class="bkm-error">Not içeriği boş olamaz.</div>';
            }
        } else {
            echo '<div class="bkm-error">Bu göreve not ekleme veya cevap yazma yetkiniz yok.</div>';
        }
    }
*/

// Display success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'task_completed') {
        echo '<div class="bkm-success">Görev başarıyla tamamlandı!</div>';
    } elseif ($_GET['success'] === 'task_added') {
        echo '<div class="bkm-success">Görev başarıyla eklendi!</div>';
    } elseif ($_GET['success'] === 'note_added') {
        echo '<div class="bkm-success">Not başarıyla eklendi!</div>';
    }
}

// Get users for task assignment - include all roles
$users = get_users(array(
    'role__in' => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
    'orderby' => 'meta_value',
    'meta_key' => 'first_name',
    'order' => 'ASC'
));

// Debug: Log user count
error_log('BKM: Kullanıcı sayısı: ' . count($users));
if (count($users) == 0) {
    error_log('BKM: Hiç kullanıcı bulunamadı. Tüm kullanıcıları getirmeyi deneyelim.');
    $users = get_users(); // Fallback to all users
}

// Get categories and performance data for action form
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");
$performances = $wpdb->get_results("SELECT * FROM $performance_table ORDER BY name ASC");
?>

<!-- BKM Plugin CSS Override - WordPress Tema Çakışmalarını Çöz -->
<style>
.bkm-frontend-container { 
    background: #f8f9fa !important; 
    padding: 20px !important; 
    margin: 0 auto !important; 
    max-width: 1200px !important; 
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}
.bkm-dashboard-header { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; 
    color: #fff !important; 
    padding: 30px !important; 
    border-radius: 8px !important; 
    margin-bottom: 20px !important; 
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}
.bkm-dashboard-header h1 {
    margin: 0 !important;
    color: #fff !important;
    font-size: 28px !important;
    font-weight: 600 !important;
}
.bkm-actions-section {
    background: #fff !important;
    padding: 30px !important;
    border-radius: 8px !important;
}
.bkm-table { 
    width: 100% !important; 
    background: #fff !important; 
    border-collapse: collapse !important; 
    border-radius: 8px !important; 
    overflow: hidden !important; 
    margin: 20px 0 !important;
}
.bkm-table th, .bkm-table td { 
    padding: 12px 15px !important; 
    border-bottom: 1px solid #e9ecef !important; 
    text-align: left !important;
}
.bkm-table th { 
    background: #f8f9fa !important; 
    font-weight: 600 !important; 
    color: #495057 !important;
}
.bkm-table tbody tr:hover {
    background: #f8f9fa !important;
}
.bkm-btn { 
    padding: 12px 24px !important; 
    border-radius: 8px !important; 
    border: none !important; 
    cursor: pointer !important; 
    font-size: 14px !important; 
    font-weight: 500 !important;
    text-decoration: none !important;
    display: inline-block !important;
}
.bkm-btn-primary { 
    background: #007cba !important; 
    color: #fff !important; 
}
.bkm-btn-success { 
    background: #28a745 !important; 
    color: #fff !important; 
}
.bkm-btn-warning { 
    background: #ffc107 !important; 
    color: #212529 !important; 
}
.bkm-btn-danger {
    background: #dc3545 !important;
    color: #fff !important;
}
.bkm-btn-secondary {
    background: #6c757d !important;
    color: #fff !important;
}
.bkm-btn-small {
    padding: 6px 12px !important;
    font-size: 12px !important;
    margin-right: 4px !important;
    margin-bottom: 4px !important;
    display: inline-block !important;
    visibility: visible !important;
}
.bkm-section-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 25px !important;
    padding-bottom: 15px !important;
    border-bottom: 2px solid #e9ecef !important;
}
.bkm-section-header h2 {
    margin: 0 !important;
    color: #2c3e50 !important;
    font-size: 22px !important;
    font-weight: 600 !important;
}
.bkm-action-buttons {
    display: flex !important;
    gap: 10px !important;
}
.bkm-note-form-row {
    display: flex !important;
    gap: 15px !important;
    margin-bottom: 15px !important;
}
.bkm-note-textarea {
    flex: 2 !important;
}
.bkm-note-progress {
    flex: 1 !important;
    min-width: 180px !important;
}
.bkm-note-progress label {
    display: block !important;
    margin-bottom: 5px !important;
    font-weight: 500 !important;
    color: #495057 !important;
}
.bkm-note-progress input {
    width: 100% !important;
    padding: 8px 12px !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    font-size: 14px !important;
}
.bkm-note-progress small {
    display: block !important;
    margin-top: 5px !important;
    color: #6c757d !important;
    font-size: 12px !important;
}
.bkm-note-form {
    background: #f8f9fa !important;
    padding: 15px !important;
    border-radius: 8px !important;
    border: 1px solid #e9ecef !important;
    margin-top: 10px !important;
}
.bkm-note-textarea label {
    display: block !important;
    margin-bottom: 5px !important;
    font-weight: 500 !important;
    color: #495057 !important;
}
.bkm-note-textarea textarea {
    width: 100% !important;
    padding: 8px 12px !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    resize: vertical !important;
}
.bkm-progress-bar.progress-updated {
    animation: progressPulse 2s ease-in-out !important;
}
@keyframes progressPulse {
    0% { background-color: #007cba; }
    50% { background-color: #28a745; }
    100% { background-color: #007cba; }
}
.bkm-note-progress-update {
    background: #d4edda !important;
    color: #155724 !important;
    padding: 5px 10px !important;
    border-radius: 4px !important;
    font-size: 12px !important;
    margin: 5px 0 !important;
    border: 1px solid #c3e6cb !important;
}
.bkm-task-item.completed {
    background: #f8f9fa !important;
    border-left: 4px solid #28a745 !important;
    opacity: 0.9 !important;
}
.bkm-task-item.completed .bkm-task-content p {
    text-decoration: line-through !important;
    color: #6c757d !important;
}
.bkm-task-item.completed .bkm-progress-bar {
    background: #28a745 !important;
}
.action-completed {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
}
.action-completed .bkm-progress-bar {
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%) !important;
}

/* Yeni durum renkleri */
.bkm-status.status-open {
    background: #dc3545 !important;
    color: #fff !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
}

.bkm-status.status-progress {
    background: #ffc107 !important;
    color: #212529 !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
}

.bkm-status.status-completed {
    background: #28a745 !important;
    color: #fff !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
}

/* Sorumlu kişiler için kompakt görünüm */
.bkm-responsible-users-compact {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 3px !important;
}

.bkm-badge-user-small {
    background: #e9ecef !important;
    color: #495057 !important;
    padding: 2px 6px !important;
    border-radius: 3px !important;
    font-size: 11px !important;
    font-weight: 500 !important;
    white-space: nowrap !important;
}

/* Şık sorumlu kişiler tasarımı */
.bkm-responsible-users-elegant {
    display: flex !important;
    align-items: center !important;
    flex-wrap: wrap !important;
    gap: 8px !important;
    max-width: 200px !important;
}

.bkm-user-chip {
    display: flex !important;
    align-items: center !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: #fff !important;
    padding: 4px 8px 4px 4px !important;
    border-radius: 16px !important;
    font-size: 11px !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
}

.bkm-user-chip:hover {
    transform: translateY(-1px) !important;
}

.bkm-user-avatar {
    background: rgba(255,255,255,0.2) !important;
    width: 20px !important;
    height: 20px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 10px !important;
    font-weight: 600 !important;
    margin-right: 6px !important;
    color: #fff !important;
}

.bkm-user-name {
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 80px !important;
}

.bkm-user-separator {
    color: #6c757d !important;
    font-size: 12px !important;
    opacity: 0.6 !important;
}

/* Şık durum tasarımı */
.bkm-status-elegant {
    display: flex !important;
    align-items: center !important;
    padding: 6px 12px !important;
    border-radius: 20px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    transition: all 0.2s ease !important;
    min-width: 110px !important;
    justify-content: center !important;
}

.bkm-status-elegant:hover {
    transform: translateY(-1px) !important;
}

.bkm-status-elegant.status-open {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%) !important;
    color: #fff !important;
}

.bkm-status-elegant.status-progress {
    background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%) !important;
    color: #2c2c54 !important;
}

.bkm-status-elegant.status-completed {
    background: linear-gradient(135deg, #5f27cd 0%, #00d2d3 100%) !important;
    color: #fff !important;
}

.bkm-status-icon {
    margin-right: 6px !important;
    font-size: 10px !important;
}

.bkm-status-text {
    font-weight: 600 !important;
}

/* Filtre Paneli Stilleri */
.bkm-filter-panel {
    background: #fff !important;
    border: 1px solid #e9ecef !important;
    border-radius: 8px !important;
    margin-bottom: 20px !important;
    overflow: hidden !important;
}

.bkm-filter-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: #fff !important;
    padding: 15px 20px !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.bkm-filter-header h3 {
    margin: 0 !important;
    color: #fff !important;
    font-size: 16px !important;
    font-weight: 600 !important;
}

.bkm-filter-controls {
    display: flex !important;
    gap: 8px !important;
}

.bkm-filter-content {
    padding: 20px !important;
}

.bkm-filter-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
    gap: 15px !important;
    margin-bottom: 20px !important;
}

.bkm-filter-group {
    display: flex !important;
    flex-direction: column !important;
}

.bkm-filter-group label {
    font-weight: 600 !important;
    color: #495057 !important;
    margin-bottom: 5px !important;
    font-size: 14px !important;
}

.bkm-filter-select {
    padding: 8px 12px !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    background: #fff !important;
    color: #495057 !important;
    transition: all 0.2s ease !important;
}

.bkm-filter-select:focus {
    border-color: #667eea !important;
    outline: none !important;
}

.bkm-active-filters {
    background: #f8f9fa !important;
    padding: 15px !important;
    border-radius: 6px !important;
    border: 1px solid #e9ecef !important;
}

.bkm-active-filters h4 {
    margin: 0 0 10px 0 !important;
    color: #495057 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
}

.bkm-filter-tag {
    display: inline-flex !important;
    align-items: center !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: #fff !important;
    padding: 4px 8px !important;
    border-radius: 16px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    margin: 2px !important;
    gap: 5px !important;
}

.bkm-filter-tag-close {
    background: rgba(255,255,255,0.2) !important;
    border: none !important;
    color: #fff !important;
    width: 16px !important;
    height: 16px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    font-size: 10px !important;
    line-height: 1 !important;
}

.bkm-filter-tag-close:hover {
    background: rgba(255,255,255,0.3) !important;
}

.bkm-btn-info {
    background: #17a2b8 !important;
    color: #fff !important;
}

.bkm-btn-info:hover {
    background: #138496 !important;
}

/* Tablo satırlarının gizlenme animasyonu */
.bkm-table tbody tr.filtered-out {
    display: none !important;
}

.bkm-reports-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    min-height: 100vh;
    height: auto;
    background: rgba(0,0,0,0.12);
    z-index: 10000;
    overflow-y: scroll;
    overflow-x: hidden;
    display: none;
}
.bkm-reports-modal-content {
    background: #fff;
    max-width: 1200px;
    margin: 40px auto;
    border-radius: 10px;
    padding: 32px;
    position: relative;
    height: auto;
    min-height: unset;
    overflow: visible;
}

.bkm-reports-panel-static {
    width: 100%;
    max-width: 1200px;
    margin: 40px auto;
    background: #fff;
    border-radius: 10px;
    padding: 32px;
    position: relative;
    display: none;
    z-index: 10;
}

.bkm-chart-container canvas {
    width: 100% !important;
    max-width: 600px;
    height: 260px !important;
    max-height: 260px !important;
    margin: 0 auto;
    display: block;
}

/* Task Edit and History Modal Styles */
.bkm-modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.6) !important;
    z-index: 10000 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.bkm-modal-content {
    background: #fff !important;
    border-radius: 8px !important;
    max-width: 600px !important;
    width: 90% !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
}

.bkm-modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: #fff !important;
    padding: 20px !important;
    border-radius: 8px 8px 0 0 !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.bkm-modal-header h3 {
    margin: 0 !important;
    font-size: 18px !important;
    font-weight: 600 !important;
}

.bkm-modal-close {
    background: none !important;
    border: none !important;
    color: #fff !important;
    font-size: 24px !important;
    cursor: pointer !important;
    padding: 0 !important;
    width: 30px !important;
    height: 30px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 4px !important;
    transition: background-color 0.2s !important;
}

.bkm-modal-close:hover {
    background: rgba(255, 255, 255, 0.2) !important;
}

.bkm-modal-body {
    padding: 20px !important;
}

.bkm-modal-footer {
    padding: 15px 20px !important;
    background: #f8f9fa !important;
    border-radius: 0 0 8px 8px !important;
    text-align: right !important;
}

.bkm-form-row {
    display: flex !important;
    gap: 15px !important;
    margin-bottom: 15px !important;
}

.bkm-form-row .bkm-form-group {
    flex: 1 !important;
    margin-bottom: 0 !important;
}

.bkm-form-group {
    margin-bottom: 15px !important;
}

.bkm-form-group label {
    display: block !important;
    margin-bottom: 5px !important;
    font-weight: 600 !important;
    color: #333 !important;
}

.bkm-form-group input,
.bkm-form-group textarea,
.bkm-form-group select {
    width: 100% !important;
    padding: 8px 12px !important;
    border: 1px solid #ddd !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    font-family: inherit !important;
    box-sizing: border-box !important;
}

.bkm-form-group textarea {
    resize: vertical !important;
    min-height: 60px !important;
}

.bkm-form-actions {
    display: flex !important;
    gap: 10px !important;
    justify-content: flex-end !important;
    margin-top: 20px !important;
}

/* Task History Styles */
.bkm-history-timeline {
    max-height: 400px !important;
    overflow-y: auto !important;
}

.bkm-history-entry {
    border-left: 3px solid #667eea !important;
    padding-left: 15px !important;
    margin-bottom: 20px !important;
    background: #f8f9fa !important;
    padding: 15px !important;
    border-radius: 0 6px 6px 0 !important;
}

.bkm-history-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 8px !important;
}

.bkm-history-date {
    color: #666 !important;
    font-size: 12px !important;
}

.bkm-history-reason,
.bkm-history-changes {
    margin-bottom: 5px !important;
    font-size: 14px !important;
}

.bkm-history-reason strong,
.bkm-history-changes strong {
    color: #333 !important;
}

/* Button Styles for New Actions */
.bkm-btn-warning {
    background: #ffc107 !important;
    color: #212529 !important;
    border: 1px solid #ffc107 !important;
}

.bkm-btn-warning:hover {
    background: #e0a800 !important;
    border-color: #e0a800 !important;
}

.bkm-btn-danger {
    background: #dc3545 !important;
    color: #fff !important;
    border: 1px solid #dc3545 !important;
}

.bkm-btn-danger:hover {
    background: #c82333 !important;
    border-color: #c82333 !important;
}

/* Task approval status indicators */
.bkm-task-item[data-approval-status="pending"] {
    border-left: 4px solid #ffc107 !important;
}

.bkm-task-item[data-approval-status="approved"] {
    border-left: 4px solid #28a745 !important;
}

.bkm-task-item[data-approval-status="rejected"] {
    border-left: 4px solid #dc3545 !important;
}

</style>

<div class="bkm-frontend-container">
    <div class="bkm-dashboard">
        <!-- Header -->
        <div class="bkm-dashboard-header">
            <h1>Aksiyon Takip Sistemi</h1>
            <div class="bkm-user-info">
                <?php 
                $full_name = trim($current_user->first_name . ' ' . $current_user->last_name);
                $display_text = !empty($full_name) ? $full_name : $current_user->display_name;
                ?>
                Hoş geldiniz, <strong><?php echo esc_html($display_text); ?></strong>
                <?php if ($is_admin || $is_editor): ?>
                    <button class="bkm-btn bkm-btn-warning bkm-btn-small" onclick="toggleSettingsPanel()">
                        ⚙️ Ayarlar
                    </button>
                <?php endif; ?>
                <a href="?bkm_logout=1" class="bkm-logout">Çıkış</a>
            </div>
        </div>
        

        
        <!-- Actions Table -->
        <div class="bkm-actions-section">
            <div class="bkm-section-header">
                <h2>Aksiyonlar</h2>
                <div class="bkm-action-buttons">
                    <button class="bkm-btn bkm-btn-info" onclick="toggleFilterPanel()">
                        🔍 Filtrele
                    </button>
                    <button class="bkm-btn bkm-btn-secondary" onclick="refreshActions()" title="Aksiyon listesini yenile">
                        🔄 Yenile
                    </button>
                    <?php if ($is_admin || $is_editor): ?>
                        <button class="bkm-btn bkm-btn-success" onclick="toggleActionForm()">
                            ➕ Yeni Aksiyon
                        </button>
                    <?php endif; ?>
                    <?php if (current_user_can('edit_posts')): ?>
                        <button class="bkm-btn bkm-btn-primary" onclick="toggleTaskForm()">
                            📋 Görev Ekle
                        </button>
                    <?php endif; ?>
                    <?php if ($is_admin || $is_editor): ?>
                        <div class="bkm-dropdown" style="position:relative; display:inline-block;">
                            <button class="bkm-btn bkm-btn-info" onclick="toggleReportsDropdown(event)">
                                📊 Raporlar
                            </button>
                            <div id="bkm-reports-dropdown" class="bkm-reports-dropdown" style="display:none; position:static; width:100%; max-width:1200px; margin:40px auto; background:#fff; border-radius:10px; z-index:1000; padding:32px;">
                                <button onclick="toggleReportsDropdown(event)" class="bkm-btn bkm-btn-secondary bkm-btn-small" style="position:absolute; top:16px; right:16px;">❌ Kapat</button>
                                <?php include BKM_AKSIYON_TAKIP_PLUGIN_DIR . 'admin/pages/reports.php'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Settings Panel (hidden by default) -->
            <?php if ($is_admin || $is_editor): ?>
                <div id="bkm-settings-panel" class="bkm-settings-panel" style="display: none;">
                    <div class="bkm-settings-header">
                        <h3>⚙️ Sistem Ayarları</h3>
                        <button class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleSettingsPanel()">
                            ❌ Kapat
                        </button>
                    </div>
                    
                    <!-- Settings Tabs -->
                    <div class="bkm-settings-tabs">
                        <button class="settings-tab" data-tab="categories" onclick="switchSettingsTab('categories')">
                            🏷️ Kategoriler
                        </button>
                        <button class="settings-tab" data-tab="performances" onclick="switchSettingsTab('performances')">
                            📊 Performanslar
                        </button>
                        <button class="settings-tab active" data-tab="users" onclick="switchSettingsTab('users')">
                            👥 Kullanıcılar
                        </button>
                        <button class="settings-tab" data-tab="company" onclick="switchSettingsTab('company')">
                            🏢 Firma Ayarları
                        </button>
                    </div>
                    
                    <!-- Categories Tab -->
                    <div id="settings-tab-categories" class="bkm-settings-tab-content">
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
                                <h4 id="categories-header"></h4>
                                <div id="categories-list" class="bkm-items-list"></div>
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
                                <div id="performances-list" class="bkm-items-list"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users Tab -->
                    <div id="settings-tab-users" class="bkm-settings-tab-content active">
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
                                            <option value="contributor">Katılımcı</option>
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
                                <h4 id="users-header">Mevcut Kullanıcılar</h4>
                                
                                <!-- Kullanıcı listesi güncelleme butonu -->
                                <div style="margin: 10px 0;">
                                    <button onclick="loadUsersList()" class="bkm-btn bkm-btn-small" style="background: #007cba;">
                                        🔄 Kullanıcı Listesini Güncelle
                                    </button>
                                </div>
                                
                                <div id="users-list" class="bkm-items-list">
                                    <!-- Kullanıcılar AJAX ile yüklenecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Company Settings Tab -->
                    <div id="settings-tab-company" class="bkm-settings-tab-content">
                        <div class="bkm-management-grid">
                            <!-- Company Settings Form -->
                            <div class="bkm-management-form bkm-company-form">
                                <h4>🏢 Firma Bilgileri</h4>
                                <form id="bkm-company-form-element" enctype="multipart/form-data">
                                    <div class="bkm-field">
                                        <label for="company_name">Firma Adı <span class="required">*</span>:</label>
                                        <input type="text" name="company_name" id="company_name" required 
                                               placeholder="Firma adını girin"
                                               value="<?php echo esc_attr(get_option('bkm_company_name', '')); ?>" />
                                    </div>
                                    
                                    <div class="bkm-field">
                                        <label for="company_logo">Firma Logosu:</label>
                                        <div class="bkm-logo-upload-area">
                                            <input type="file" name="company_logo" id="company_logo" 
                                                   accept="image/*" class="bkm-file-input" />
                                            <div class="bkm-logo-preview" id="logo-preview">
                                                <?php 
                                                $current_logo = get_option('bkm_company_logo', '');
                                                if ($current_logo): ?>
                                                    <img src="<?php echo esc_url($current_logo); ?>" alt="Mevcut Logo" />
                                                    <button type="button" class="bkm-btn bkm-btn-danger bkm-btn-small bkm-remove-logo" 
                                                            onclick="removeCompanyLogo()">🗑️ Logoyu Kaldır</button>
                                                <?php else: ?>
                                                    <div class="bkm-logo-placeholder">
                                                        <i class="dashicons dashicons-camera"></i>
                                                        <p>Logo yüklemek için dosya seçin</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bkm-file-info">
                                                <small>Desteklenen formatlar: JPG, PNG, GIF (Max: 2MB)</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bkm-field">
                                        <label for="company_address">Firma Adresi:</label>
                                        <textarea name="company_address" id="company_address" rows="3" 
                                                  placeholder="Firma adresini girin (isteğe bağlı)"><?php echo esc_textarea(get_option('bkm_company_address', '')); ?></textarea>
                                    </div>
                                    
                                    <div class="bkm-field">
                                        <label for="company_phone">Telefon:</label>
                                        <input type="tel" name="company_phone" id="company_phone" 
                                               placeholder="Telefon numarasını girin"
                                               value="<?php echo esc_attr(get_option('bkm_company_phone', '')); ?>" />
                                    </div>
                                    
                                    <div class="bkm-field">
                                        <label for="company_email">E-posta:</label>
                                        <input type="email" name="company_email" id="company_email" 
                                               placeholder="Firma e-posta adresini girin"
                                               value="<?php echo esc_attr(get_option('bkm_company_email', '')); ?>" />
                                    </div>
                                    
                                    <div class="bkm-form-actions">
                                        <button type="submit" class="bkm-btn bkm-btn-primary">
                                            💾 Firma Bilgilerini Kaydet
                                        </button>
                                        <button type="button" class="bkm-btn bkm-btn-secondary" onclick="resetCompanyForm()">
                                            🔄 Sıfırla
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Company Info Display -->
                            <div class="bkm-management-list">
                                <h4>📋 Firma Bilgileri Özeti</h4>
                                <div class="bkm-company-info" id="company-info-display"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter Panel (hidden by default) -->
            <div id="bkm-filter-panel" class="bkm-filter-panel" style="display: none;">
                <div class="bkm-filter-header">
                    <h3>🔍 Aksiyon Filtreleri</h3>
                    <div class="bkm-filter-controls">
                        <button class="bkm-btn bkm-btn-warning bkm-btn-small" onclick="clearAllFilters()">
                            🗑️ Temizle
                        </button>
                        <button class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleFilterPanel()">
                            ❌ Kapat
                        </button>
                    </div>
                </div>
                
                <div class="bkm-filter-content">
                    <div class="bkm-filter-grid">
                        <!-- Tanımlayan Filtresi -->
                        <div class="bkm-filter-group">
                            <label for="filter-tanimlayan">👤 Tanımlayan:</label>
                            <select id="filter-tanimlayan" class="bkm-filter-select">
                                <option value="">Tümü</option>
                                <?php 
                                $tanimlayanlar = array();
                                foreach ($actions as $action) {
                                    if (!in_array($action->tanımlayan_name, $tanimlayanlar)) {
                                        $tanimlayanlar[] = $action->tanımlayan_name;
                                    }
                                }
                                sort($tanimlayanlar);
                                foreach ($tanimlayanlar as $tanimlayan): ?>
                                    <option value="<?php echo esc_attr($tanimlayan); ?>"><?php echo esc_html($tanimlayan); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sorumlu Kişiler Filtresi -->
                        <div class="bkm-filter-group">
                            <label for="filter-sorumlu">👥 Sorumlu Kişi:</label>
                            <select id="filter-sorumlu" class="bkm-filter-select">
                                <option value="">Tümü</option>
                                <?php 
                                $sorumlu_kişiler = array();
                                foreach ($actions as $action) {
                                    $sorumlu_ids = explode(',', $action->sorumlu_ids);
                                    foreach ($sorumlu_ids as $sorumlu_id) {
                                        $user = get_user_by('ID', trim($sorumlu_id));
                                        if ($user) {
                                            $full_name = trim($user->first_name . ' ' . $user->last_name);
                                            $display_name = !empty($full_name) ? $full_name : $user->display_name;
                                            if (!in_array($display_name, $sorumlu_kişiler)) {
                                                $sorumlu_kişiler[] = $display_name;
                                            }
                                        }
                                    }
                                }
                                sort($sorumlu_kişiler);
                                foreach ($sorumlu_kişiler as $sorumlu): ?>
                                    <option value="<?php echo esc_attr($sorumlu); ?>"><?php echo esc_html($sorumlu); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kategori Filtresi -->
                        <div class="bkm-filter-group">
                            <label for="filter-kategori">🏷️ Kategori:</label>
                            <select id="filter-kategori" class="bkm-filter-select">
                                <option value="">Tümü</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->name); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Önem Filtresi -->
                        <div class="bkm-filter-group">
                            <label for="filter-onem">⚡ Önem Derecesi:</label>
                            <select id="filter-onem" class="bkm-filter-select">
                                <option value="">Tümü</option>
                                <option value="1">Düşük</option>
                                <option value="2">Orta</option>
                                <option value="3">Yüksek</option>
                            </select>
                        </div>

                        <!-- Durum Filtresi -->
                        <div class="bkm-filter-group">
                            <label for="filter-durum">📊 Durum:</label>
                            <select id="filter-durum" class="bkm-filter-select">
                                <option value="">Tümü</option>
                                <option value="open">AÇIK</option>
                                <option value="active">DEVAM EDİYOR</option>
                                <option value="completed">TAMAMLANDI</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Aktif Filtreler -->
                    <div class="bkm-active-filters" id="active-filters" style="display: none;">
                        <h4>🏃‍♂️ Aktif Filtreler:</h4>
                        <div id="active-filters-list"></div>
                    </div>
                </div>
            </div>

            <!-- Add Task Form (hidden by default) -->
            <?php if (current_user_can('edit_posts')): ?>
                <div id="bkm-task-form" class="bkm-task-form" style="display: none;">
                    <h3>Yeni Görev Ekle</h3>
                    <form id="bkm-task-form-element">
                        <div class="bkm-form-grid">
                            <div class="bkm-field">
                                <label for="action_id">Aksiyon <span class="required">*</span>:</label>
                                <select name="action_id" id="action_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo $action->id; ?>">
                                            #<?php echo $action->id; ?> - <?php echo esc_html(mb_substr($action->tespit_konusu ?: $action->title ?: $action->aciklama, 0, 50)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="aciklama">Görev İçeriği <span class="required">*</span>:</label>
                                <textarea name="aciklama" id="aciklama" rows="3" required></textarea>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="baslangic_tarihi">Başlangıç Tarihi <span class="required">*</span>:</label>
                                <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" required />
                            </div>
                            
                            <div class="bkm-field">
                                <label for="sorumlu_id">Sorumlu <span class="required">*</span>:</label>
                                <select name="sorumlu_id" id="sorumlu_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($users as $user): 
                                        $full_name = trim($user->first_name . ' ' . $user->last_name);
                                        $display_text = !empty($full_name) ? $full_name : $user->display_name;
                                    ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($display_text); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="bitis_tarihi">Hedef Bitiş Tarihi <span class="required">*</span>:</label>
                                <input type="date" name="bitis_tarihi" id="bitis_tarihi" required />
                            </div>
                            
                            <div class="bkm-field">
                                <label for="ilerleme_durumu">İlerleme (%):</label>
                                <input type="number" name="ilerleme_durumu" id="ilerleme_durumu" min="0" max="100" value="0" />
                            </div>
                        </div>
                        
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-primary">Görev Ekle</button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="toggleTaskForm()">İptal</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Add Action Form (hidden by default) -->
            <?php if ($is_admin || $is_editor): ?>
                <div id="bkm-action-form" class="bkm-task-form" style="display: none;">
                    <h3>Yeni Aksiyon Ekle</h3>
                    
                    <form id="bkm-action-form-element">
                        <!-- İlk satır: Kategori -->
                        <div class="bkm-form-row">
                            <div class="bkm-field">
                                <label for="action_kategori_id">Kategori <span class="required">*</span>:</label>
                                <select name="kategori_id" id="action_kategori_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category->id; ?>"><?php echo esc_html($category->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- İkinci satır: Performans, Önem Derecesi, Hedef Tarih -->
                        <div class="bkm-form-grid-3">
                            <div class="bkm-field">
                                <label for="action_performans_id">Performans <span class="required">*</span>:</label>
                                <select name="performans_id" id="action_performans_id" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($performances as $performance): ?>
                                        <option value="<?php echo $performance->id; ?>"><?php echo esc_html($performance->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_onem_derecesi">Önem Derecesi <span class="required">*</span>:</label>
                                <select name="onem_derecesi" id="action_onem_derecesi" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="1">Düşük</option>
                                    <option value="2">Orta</option>
                                    <option value="3">Yüksek</option>
                                </select>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_hedef_tarih">Hedef Tarih <span class="required">*</span>:</label>
                                <input type="date" name="hedef_tarih" id="action_hedef_tarih" required />
                            </div>
                        </div>
                        
                        <!-- Üçüncü satır: Sorumlu Kişiler ve Tespit Konusu -->
                        <div class="bkm-form-grid-2">
                            <div class="bkm-field">
                                <label for="action_sorumlu_ids">Sorumlu Kişiler <span class="required">*</span>:</label>
                                <select name="sorumlu_ids[]" id="action_sorumlu_ids" multiple required size="5">
                                    <?php foreach ($users as $user): 
                                        $full_name = trim($user->first_name . ' ' . $user->last_name);
                                        $display_text = !empty($full_name) ? $full_name : $user->display_name;
                                    ?>
                                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($display_text); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Ctrl tuşu ile birden fazla seçim yapabilirsiniz</small>
                            </div>
                            
                            <div class="bkm-field">
                                <label for="action_tespit_konusu">Tespit Konusu <span class="required">*</span>:</label>
                                <textarea name="tespit_konusu" id="action_tespit_konusu" rows="5" required placeholder="Tespit edilen konuyu kısaca açıklayın..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Dördüncü satır: Açıklama (tam genişlik) -->
                        <div class="bkm-form-row">
                            <div class="bkm-field">
                                <label for="action_aciklama">Açıklama <span class="required">*</span>:</label>
                                <textarea name="aciklama" id="action_aciklama" rows="4" required placeholder="Aksiyonun detaylı açıklamasını yazın..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Form Actions (sağ alt) -->
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-success">
                                ✅ Aksiyon Ekle
                            </button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="toggleActionForm()">
                                ❌ İptal
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Actions Table -->
            <div class="bkm-actions-table">
                <table class="bkm-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanımlayan</th>
                            <th>Sorumlu Kişiler</th>
                            <th>Kategori</th>
                            <th>Tespit Konusu</th>
                            <th>Önem</th>
                            <th>İlerleme</th>
                            <th>Durum</th>
                            <th>Görevler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($actions): ?>
                            <?php foreach ($actions as $action): ?>
                                <?php
                                // Get tasks for this action
                                $action_tasks = $wpdb->get_results($wpdb->prepare(
                                    "SELECT t.*, 
                                            CASE 
                                                WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                                                THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                                                ELSE u.display_name
                                            END as sorumlu_name 
                                     FROM $tasks_table t 
                                     LEFT JOIN {$wpdb->users} u ON t.sorumlu_id = u.ID 
                                     LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                                     LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
                                     WHERE t.action_id = %d 
                                     ORDER BY t.created_at DESC",
                                    $action->id
                                ));
                                ?>
                                <?php
                                // Her aksiyon için durum hesaplaması
                                $status = isset($action->status) ? $action->status : '';
                                $ilerleme = intval($action->ilerleme_durumu);
                                if (empty($status)) {
                                    if ($ilerleme == 0) {
                                        $status = 'open';
                                    } elseif ($ilerleme >= 1 && $ilerleme <= 99) {
                                        $status = 'active';
                                    } elseif ($ilerleme == 100) {
                                        $status = 'completed';
                                    }
                                }

                                // Sorumlu kişi isimlerini hazırla
                                $sorumlu_ids = explode(',', $action->sorumlu_ids);
                                $sorumlu_names = array();
                                foreach ($sorumlu_ids as $sorumlu_id) {
                                    $user = get_user_by('ID', trim($sorumlu_id));
                                    if ($user) {
                                        $full_name = trim($user->first_name . ' ' . $user->last_name);
                                        $display_name = !empty($full_name) ? $full_name : $user->display_name;
                                        $sorumlu_names[] = $display_name;
                                    }
                                }
                                ?>
                                <tr data-tanimlayan="<?php echo esc_attr($action->tanımlayan_name); ?>" 
                                    data-kategori="<?php echo esc_attr($action->kategori_name); ?>" 
                                    data-onem="<?php echo $action->onem_derecesi; ?>" 
                                    data-ilerleme="<?php echo $action->ilerleme_durumu; ?>"
                                    data-sorumlu="<?php echo esc_attr(implode(',', $sorumlu_names)); ?>"
                                    data-durum="<?php echo esc_attr($status); ?>">
                                    <td><?php echo $action->id; ?></td>
                                    <td><?php echo esc_html($action->tanımlayan_name ?: 'Bilinmiyor'); ?></td>
                                    <td>
                                        <div class="bkm-responsible-users-elegant">
                                            <?php foreach ($sorumlu_names as $index => $name): ?>
                                                <div class="bkm-user-chip">
                                                    <span class="bkm-user-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></span>
                                                    <span class="bkm-user-name"><?php echo esc_html($name); ?></span>
                                                </div>
                                                <?php if ($index < count($sorumlu_names) - 1): ?>
                                                    <div class="bkm-user-separator">•</div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($action->kategori_name); ?></td>
                                    <td class="bkm-action-tespit">
                                        <?php echo esc_html(substr($action->tespit_konusu, 0, 100)); ?>
                                    </td>
                                    <td>
                                        <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                                            <?php 
                                            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                                            echo $priority_labels[$action->onem_derecesi];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="bkm-progress" data-action-id="<?php echo $action->id; ?>">
                                            <div class="bkm-progress-bar" style="width: <?php echo $action->ilerleme_durumu; ?>%"></div>
                                            <span class="bkm-progress-text"><?php echo $action->ilerleme_durumu; ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        // Map status to display values
                                        $status_config = array(
                                            'open' => array('icon' => '🔴', 'text' => 'AÇIK', 'class' => 'status-open'),
                                            'active' => array('icon' => '🟡', 'text' => 'DEVAM EDİYOR', 'class' => 'status-active'),
                                            'completed' => array('icon' => '🟢', 'text' => 'TAMAMLANDI', 'class' => 'status-completed')
                                        );
                                        
                                        $config = isset($status_config[$status]) ? $status_config[$status] : $status_config['open'];
                                        ?>
                                        <div class="bkm-status-elegant <?php echo $config['class']; ?> bkm-action-status" data-action-id="<?php echo $action->id; ?>">
                                            <span class="bkm-status-icon"><?php echo $config['icon']; ?></span>
                                            <span class="bkm-status-text"><?php echo $config['text']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bkm-action-buttons-cell">
                                            <?php if ($is_admin || $is_editor): ?>
                                                <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="toggleActionDetails(<?php echo $action->id; ?>)">
                                                    📋 Detaylar
                                                </button>
                                            <?php endif; ?>
                                            <button class="bkm-btn bkm-btn-small" onclick="toggleTasks(<?php echo $action->id; ?>)">
                                                📝 Görevler (<?php echo count($action_tasks); ?>)
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Action Details Row -->
                                <?php if ($is_admin || $is_editor): ?>
                                <tr id="details-<?php echo $action->id; ?>" class="bkm-action-details-row" style="display: none;">
                                    <td colspan="9">
                                        <div class="bkm-action-details-container">
                                            <h4>📋 Aksiyon Detayları</h4>
                                            
                                            <!-- Üst kısım: Genel Bilgiler ve Tarih Bilgileri yan yana -->
                                            <div class="bkm-details-grid-top">
                                                <div class="bkm-detail-section">
                                                    <h5>📊 Genel Bilgiler</h5>
                                                    <div class="bkm-detail-item">
                                                        <strong>Aksiyon ID:</strong> 
                                                        <span>#<?php echo $action->id; ?></span>
                                                    </div>
                                                    <div class="bkm-detail-item">
                                                        <strong>Tanımlayan:</strong> 
                                                        <span><?php echo esc_html($action->tanımlayan_name); ?></span>
                                                    </div>
                                                    <div class="bkm-detail-item">
                                                        <strong>Kategori:</strong> 
                                                        <span class="bkm-badge bkm-badge-category"><?php echo esc_html($action->kategori_name); ?></span>
                                                    </div>
                                                    <div class="bkm-detail-item">
                                                        <strong>Performans:</strong> 
                                                        <span class="bkm-badge bkm-badge-performance"><?php echo esc_html($action->performans_name); ?></span>
                                                    </div>
                                                    <div class="bkm-detail-item">
                                                        <strong>Önem Derecesi:</strong> 
                                                        <span class="bkm-priority priority-<?php echo $action->onem_derecesi; ?>">
                                                            <?php 
                                                            $priority_labels = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                                                            echo $priority_labels[$action->onem_derecesi];
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="bkm-detail-section">
                                                    <h5>📅 Tarih Bilgileri</h5>
                                                    <div class="bkm-detail-item">
                                                        <strong>Hedef Tarih:</strong> 
                                                        <span class="bkm-date"><?php echo date('d.m.Y', strtotime($action->hedef_tarih)); ?></span>
                                                    </div>
                                                    <div class="bkm-detail-item">
                                                        <strong>Oluşturulma:</strong> 
                                                        <span class="bkm-date"><?php echo date('d.m.Y H:i', strtotime($action->created_at)); ?></span>
                                                    </div>
                                                    <?php if ($action->kapanma_tarihi): ?>
                                                    <div class="bkm-detail-item">
                                                        <strong>Kapanma Tarihi:</strong> 
                                                        <span class="bkm-date"><?php echo date('d.m.Y H:i', strtotime($action->kapanma_tarihi)); ?></span>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="bkm-detail-item">
                                                        <strong>İlerleme Durumu:</strong> 
                                                        <div class="bkm-progress" data-action-id="<?php echo $action->id; ?>">
                                                            <div class="bkm-progress-bar" style="width: <?php echo $action->ilerleme_durumu; ?>%"></div>
                                                            <span class="bkm-progress-text"><?php echo $action->ilerleme_durumu; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Alt kısım: Sorumlu Kişiler, Tespit Konusu ve Açıklama yan yana -->
                                            <div class="bkm-details-grid-bottom">
                                                <div class="bkm-detail-section">
                                                    <h5>👥 Sorumlu Kişiler</h5>
                                                    <div class="bkm-detail-item">
                                                        <?php 
                                                        $sorumlu_ids = explode(',', $action->sorumlu_ids);
                                                        $sorumlu_names = array();
                                                        foreach ($sorumlu_ids as $sorumlu_id) {
                                                            $user = get_user_by('ID', trim($sorumlu_id));
                                                            if ($user) {
                                                                $full_name = trim($user->first_name . ' ' . $user->last_name);
                                                                $display_name = !empty($full_name) ? $full_name : $user->display_name;
                                                                $sorumlu_names[] = $display_name;
                                                            }
                                                        }
                                                        ?>
                                                        <div class="bkm-responsible-users">
                                                            <?php foreach ($sorumlu_names as $name): ?>
                                                                <span class="bkm-badge bkm-badge-user"><?php echo esc_html($name); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="bkm-detail-section">
                                                    <h5>🔍 Tespit Konusu</h5>
                                                    <div class="bkm-detail-content">
                                                        <?php echo nl2br(esc_html($action->tespit_konusu)); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="bkm-detail-section">
                                                    <h5>📝 Açıklama</h5>
                                                    <div class="bkm-detail-content">
                                                        <?php echo nl2br(esc_html($action->aciklama)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="bkm-details-actions">
                                                <button class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleActionDetails(<?php echo $action->id; ?>)">
                                                    ❌ Detayları Kapat
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- Tasks Row -->
                                <tr id="tasks-<?php echo $action->id; ?>" class="bkm-tasks-row" style="display: none;">
                                    <td colspan="9">
                                        <div class="bkm-tasks-container">
                                            <h4>Görevler</h4>
                                            <?php if ($action_tasks): ?>
                                                <div class="bkm-tasks-list">
                                                    <?php foreach ($action_tasks as $task): ?>
                                                        <?php
                                                        // Get notes for this task
                                                        $task_notes = $wpdb->get_results($wpdb->prepare(
                                                            "SELECT n.*, 
                                                                    CASE 
                                                                        WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                                                                        THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                                                                        ELSE u.display_name
                                                                    END as user_name 
                                                             FROM $notes_table n 
                                                             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID 
                                                             LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                                                             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
                                                             WHERE n.task_id = %d 
                                                             ORDER BY n.created_at ASC",
                                                            $task->id
                                                        ));
                                                        $has_notes = !empty($task_notes);
                                                        ?>
                                                        <div class="bkm-task-item <?php echo $task->tamamlandi ? 'completed' : ''; ?>" data-task-id="<?php echo $task->id; ?>">
                                                            <div class="bkm-task-content">
                                                                <p><strong><?php echo esc_html($task->content); ?></strong></p>
                                                                <div class="bkm-task-meta">
                                                                    <span>Sorumlu: <?php echo esc_html($task->sorumlu_name); ?></span>
                                                                    <span>Başlangıç: <?php echo date('d.m.Y', strtotime($task->baslangic_tarihi)); ?></span>
                                                                    <span>Hedef: <?php echo date('d.m.Y', strtotime($task->hedef_bitis_tarihi)); ?></span>
                                                                    <?php if ($task->gercek_bitis_tarihi): ?>
                                                                        <span>Bitiş: <?php echo date('d.m.Y H:i', strtotime($task->gercek_bitis_tarihi)); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="bkm-task-progress">
                                                                    <div class="bkm-progress">
                                                                        <div class="bkm-progress-bar" style="width: <?php echo $task->ilerleme_durumu; ?>%"></div>
                                                                        <span class="bkm-progress-text"><?php echo $task->ilerleme_durumu; ?>%</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="bkm-task-actions">
                                                                <?php if ($task->sorumlu_id == $current_user->ID && !$task->tamamlandi): ?>
                                                                    <form method="post" style="display: inline;">
                                                                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce_' . $task->id); ?>
                                                                        <input type="hidden" name="task_action" value="complete_task" />
                                                                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                        <button type="submit" class="bkm-btn bkm-btn-success bkm-btn-small"
                                                                                onclick="return confirm('Bu görevi tamamladınız mı?')">
                                                                            Tamamla
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php 
                                                                // Check if user can see notes section
                                                                // Participant users who rejected tasks cannot see notes
                                                                $can_see_notes = ($task->sorumlu_id == $current_user->ID || $is_admin || $is_editor);
                                                                $is_participant = ($task->sorumlu_id == $current_user->ID && !$is_admin && !$is_editor);
                                                                $task_is_rejected = (isset($task->approval_status) && $task->approval_status === 'rejected');
                                                                
                                                                if ($is_participant && $task_is_rejected) {
                                                                    $can_see_notes = false;
                                                                }
                                                                
                                                                if ($can_see_notes): ?>
                                                                    <button class="bkm-btn bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                                                        Not Ekle
                                                                    </button>
                                                                    <?php if ($has_notes): ?>
                                                                        <button class="bkm-btn bkm-btn-small" onclick="toggleNotes(<?php echo $task->id; ?>)">
                                                                            Notları Göster (<?php echo count($task_notes); ?>)
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Note Form (hidden by default) -->
                                                        <?php 
                                                        // Same logic for note form visibility
                                                        $can_see_notes = ($task->sorumlu_id == $current_user->ID || $is_admin || $is_editor);
                                                        $is_participant = ($task->sorumlu_id == $current_user->ID && !$is_admin && !$is_editor);
                                                        $task_is_rejected = (isset($task->approval_status) && $task->approval_status === 'rejected');
                                                        
                                                        if ($is_participant && $task_is_rejected) {
                                                            $can_see_notes = false;
                                                        }
                                                        
                                                        if ($can_see_notes): ?>
                                                            <div id="note-form-<?php echo $task->id; ?>" class="bkm-note-form" style="display: none;">
                                                                <form>
                                                                    <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                    <div class="bkm-note-form-row">
                                                                        <div class="bkm-note-textarea">
                                                                            <label for="note_content_<?php echo $task->id; ?>">Not İçeriği:</label>
                                                                            <textarea name="note_content" id="note_content_<?php echo $task->id; ?>" rows="3" placeholder="Notunuzu buraya yazın..." required></textarea>
                                                                        </div>
                                                                        <div class="bkm-note-progress">
                                                                            <label for="note_progress_<?php echo $task->id; ?>">İlerleme Durumu (%):</label>
                                                                            <input type="number" name="note_progress" id="note_progress_<?php echo $task->id; ?>" 
                                                                                   min="0" max="100" value="<?php echo $task->ilerleme_durumu; ?>" 
                                                                                   placeholder="0-100" />
                                                                            <small>Mevcut: <?php echo $task->ilerleme_durumu; ?>%</small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="bkm-form-actions">
                                                                        <button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">
                                                                            Not Ekle ve İlerlemeyi Güncelle
                                                                        </button>
                                                                        <button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                                                            İptal
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            
                                                            <!-- Notes Section (hidden by default) -->
                                                            <div id="notes-<?php echo $task->id; ?>" class="bkm-notes-section" style="display: none;">
                                                                <div class="bkm-notes-content">
                                                                    <?php if ($task_notes): ?>
                                                                        <?php display_notes($task_notes, null, 0, $is_admin, $task); ?>
                                                                    <?php else: ?>
                                                                        <p style="text-align: center; color: #9e9e9e; font-style: italic; margin: 20px 0; padding: 30px; border: 2px dashed #e0e0e0; border-radius: 12px;">📝 Bu görev için henüz not bulunmamaktadır.</p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p>Bu aksiyon için henüz görev bulunmamaktadır.</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">Henüz aksiyon bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTasks(actionId) {
    var tasksRow = document.getElementById('tasks-' + actionId);
    if (tasksRow.style.display === 'none' || tasksRow.style.display === '') {
        // Show tasks row and load tasks via AJAX
        tasksRow.style.display = 'table-row';
        loadTasksForAction(actionId);
    } else {
        tasksRow.style.display = 'none';
    }
}

// Load tasks for a specific action via AJAX
function loadTasksForAction(actionId) {    
    console.log('🧪 loadTasksForAction çağrıldı, actionId:', actionId);
    
    // Store action ID for use in other functions
    lastLoadedActionId = actionId;
    
    var tasksContainer = document.querySelector('#tasks-' + actionId + ' .bkm-tasks-container');
    if (!tasksContainer) {
        console.error('❌ Tasks container bulunamadı:', '#tasks-' + actionId + ' .bkm-tasks-container');
        return;
    }
    
    console.log('✅ Tasks container bulundu:', tasksContainer);
    
    // Show loading message
    tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px;">📋 Görevler yükleniyor...</div>';
    
    // Check if jQuery and bkmFrontend are available
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery mevcut değil');
        tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #d73027;">❌ jQuery yüklenmemiş. Lütfen sayfayı yenileyin.</div>';
        return;
    }
    
    if (typeof bkmFrontend === 'undefined') {
        console.error('❌ bkmFrontend mevcut değil');
        tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #d73027;">❌ Frontend ayarları yüklenmemiş. Lütfen sayfayı yenileyin.</div>';
        return;
    }
    
    console.log('🔗 AJAX parametreleri:', {
        url: bkmFrontend.ajax_url,
        action: 'bkm_get_tasks',
        action_id: actionId,
        nonce: bkmFrontend.nonce ? 'MEVCUT (' + bkmFrontend.nonce.substring(0, 6) + '...)' : 'EKSİK'
    });
    
    // AJAX request to get tasks
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        timeout: 15000,
        data: {
            action: 'bkm_get_tasks',
            action_id: actionId,
            nonce: bkmFrontend.nonce
        },
        success: function(response) {
            console.log('✅ AJAX yanıtı alındı:', response);
            
            // Ensure response is valid and has expected structure
            if (!response) {
                console.error('❌ Response is null or undefined');
                tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #d73027;">❌ Sunucudan yanıt alınamadı.</div>';
                return;
            }
            
            if (response.success === true) {
                // Handle successful response - updated to work with new response format
                var tasks = [];
                
                // Check if response.data has tasks property (new format)
                if (response.data && response.data.tasks && Array.isArray(response.data.tasks)) {
                    tasks = response.data.tasks;
                } 
                // Fallback to old format where data is directly the tasks array
                else if (response.data && Array.isArray(response.data)) {
                    tasks = response.data;
                } 
                // Last fallback
                else {
                    tasks = [];
                }
                
                console.log('📋 Bulunan görev sayısı:', tasks.length);
                console.log('📋 Task verileri:', tasks);
                
                displayTasksInContainer(tasksContainer, tasks, actionId);
            } else {
                // Handle error response
                console.error('❌ AJAX başarısız response:', response);
                var errorMsg = 'Görevler yüklenirken hata oluştu.';
                if (response.data && typeof response.data === 'string') {
                    errorMsg = response.data;
                } else if (response.message) {
                    errorMsg = response.message;
                }
                tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #d73027;">❌ ' + errorMsg + '</div>';
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ AJAX hatası:', {
                status: xhr.status,
                statusText: xhr.statusText,
                error: error,
                responseText: xhr.responseText
            });
            
            var errorMsg = 'Bağlantı hatası oluştu: ' + error;
            if (xhr.status === 403) {
                errorMsg = 'Bu işlemi yapmaya yetkiniz yok.';
            } else if (xhr.status === 500) {
                errorMsg = 'Sunucu hatası oluştu.';
            } else if (xhr.status === 0) {
                errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
            }
            
            tasksContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #d73027;">❌ ' + errorMsg + '</div>';
        }
    });
}

// Display tasks in the container
function displayTasksInContainer(container, tasks, actionId) {
    console.log('🎯 displayTasksInContainer çağrıldı:', {
        container: container ? 'MEVCUT' : 'EKSİK',
        tasksCount: tasks ? tasks.length : 'NULL',
        actionId: actionId,
        tasks: tasks
    });
    
    if (!container) {
        console.error('❌ Container mevcut değil');
        return;
    }
    
    if (!tasks || tasks.length === 0) {
        console.log('📝 Görev bulunamadı, empty message gösteriliyor');
        container.innerHTML = '<h4>Görevler</h4><p style="text-align: center; padding: 20px; color: #666; font-style: italic;">Bu aksiyon için henüz görev bulunmamaktadır.</p>';
        return;
    }
    
    console.log('📋 ' + tasks.length + ' görev bulundu, HTML oluşturuluyor...');
    
    var html = '<h4>Görevler (' + tasks.length + ')</h4><div class="bkm-tasks-list">';
    
    tasks.forEach(function(task, index) {
        console.log('🔄 Task işleniyor:', task);
        console.log('🔍 Task tamamlandi değeri:', task.tamamlandi, typeof task.tamamlandi);
        
        var progressValue = parseInt(task.ilerleme_durumu || task.progress || 0);
        // Fix: Properly check for completion status - task.tamamlandi can be "0" or "1" as string
        var isCompleted = (parseInt(task.tamamlandi) === 1) || task.completed_at || progressValue === 100;
        
        console.log('🎯 Task completion status:', {
            tamamlandi: task.tamamlandi,
            tamamlandi_int: parseInt(task.tamamlandi),
            completed_at: task.completed_at,
            progressValue: progressValue,
            isCompleted: isCompleted
        });
        
        // Use standardized content field from backend
        var taskContent = task.content || task.title || task.aciklama || 'Görev içeriği mevcut değil';
        
        // Get approval status for styling
        var approvalStatus = task.approval_status || 'pending';
        
        html += '<div class="bkm-task-item' + (isCompleted ? ' completed' : '') + '" data-task-id="' + task.id + '" data-approval-status="' + approvalStatus + '">';
        html += '<div class="bkm-task-content">';
        
        // Show approval status indicator
        var statusIndicator = '';
        switch(approvalStatus) {
            case 'approved':
                statusIndicator = '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 10px;">✅ Onaylandı</span>';
                break;
            case 'rejected':
                statusIndicator = '<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 10px;">❌ Reddedildi</span>';
                if (task.rejection_reason) {
                    statusIndicator += '<span style="color: #dc3545; font-size: 12px; font-style: italic;">(' + escapeHtml(task.rejection_reason) + ')</span>';
                }
                break;
            case 'pending':
            default:
                statusIndicator = '<span style="background: #ffc107; color: #212529; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 10px;">⏳ Onay Bekliyor</span>';
                break;
        }
        
        html += '<p>' + statusIndicator + '<strong>' + escapeHtml(taskContent) + '</strong></p>';
        
        if (task.description && task.description !== taskContent && task.description.trim()) {
            html += '<p style="margin-top: 5px; color: #666; font-size: 14px;">' + escapeHtml(task.description) + '</p>';
        }
        
        html += '<div class="bkm-task-meta" style="margin-top: 8px; font-size: 13px; color: #666;">';
        html += '<span style="margin-right: 15px;">👤 Sorumlu: <strong>' + escapeHtml(task.sorumlu_name || 'Belirtilmemiş') + '</strong></span>';
        
        if (task.baslangic_tarihi) {
            html += '<span style="margin-right: 15px;">📅 Başlangıç: ' + formatDate(task.baslangic_tarihi) + '</span>';
        }
        if (task.hedef_bitis_tarihi) {
            html += '<span style="margin-right: 15px;">🎯 Hedef: ' + formatDate(task.hedef_bitis_tarihi) + '</span>';
        }
        if (task.gercek_bitis_tarihi) {
            html += '<span style="margin-right: 15px;">✅ Bitiş: ' + formatDateTime(task.gercek_bitis_tarihi) + '</span>';
        }
        html += '</div>';
        
        // Progress bar - only show if not completed
        if (!isCompleted) {
            html += '<div class="bkm-task-progress" style="margin-top: 12px;">';
            html += '<div class="bkm-progress" style="background: #f0f0f0; border-radius: 10px; overflow: hidden; height: 20px; position: relative;">';
            html += '<div class="bkm-progress-bar" style="background: linear-gradient(90deg, #007cba, #0085d1); height: 100%; width: ' + progressValue + '%; transition: width 0.3s ease;"></div>';
            html += '<span class="bkm-progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: 700; color: #ffffff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">' + progressValue + '%</span>';
            html += '</div>';
            html += '</div>';
        } else {
            html += '<div class="bkm-task-progress" style="margin-top: 12px;">';
            html += '<div style="text-align: center; color: #28a745; font-weight: bold; font-size: 14px;">✅ Görev Tamamlandı</div>';
            html += '</div>';
        }
        
        html += '</div>';
        
        // Task actions
        html += '<div class="bkm-task-actions" style="margin-top: 15px; text-align: right;">';
        
        // Note buttons - Enhanced logic with better user feedback and participant restrictions
        var isParticipant = (task.sorumlu_id == <?php echo $current_user_id; ?> && !<?php echo $is_admin ? 'true' : 'false'; ?> && !<?php echo $is_editor ? 'true' : 'false'; ?>);
        var canSeeNotes = canPerformOperations;
        
        // Participant users who rejected tasks cannot see notes
        if (isParticipant && isRejected) {
            canSeeNotes = false;
        }
        
        // REJECTED TASKS: Don't show any note buttons
        if (isRejected) {
            console.log('❌ Task ' + task.id + ' is rejected - hiding all note buttons');
        } else if (canSeeNotes) {
            html += '<button class="bkm-btn bkm-btn-small" onclick="toggleNoteForm(' + task.id + ')" style="margin-right: 8px;">📝 Not Ekle</button>';
            console.log('✅ Added active note button for approved task ' + task.id);
        } else if (isPending) {
            html += '<button class="bkm-btn bkm-btn-small" style="background: #6c757d; color: white; margin-right: 8px; cursor: not-allowed;" disabled title="Görevi önce onaylamalısınız">📝 Not Ekle (Beklemede)</button>';
            console.log('⏳ Added disabled note button for pending task ' + task.id);
        } else {
            // Fallback for tasks with unknown approval status
            html += '<button class="bkm-btn bkm-btn-small" onclick="toggleNoteForm(' + task.id + ')" style="margin-right: 8px; background: #8e44ad; color: white;" title="Not ekle">📝 Not Ekle</button>';
            console.log('⚠️ Added fallback note button for task ' + task.id + ' with unknown approval status: ' + task.approval_status);
        }
        
        // Notes viewing - hidden for rejected tasks completely  
        if (!isRejected && (canSeeNotes || (!isParticipant || !isRejected))) {
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="toggleNotes(' + task.id + ')" style="margin-right: 8px;">💬 Notlar</button>';
        }
        
        // NEW SIMPLIFIED APPROACH: Always show action buttons for better debugging
        console.log('🔍 NEW SYSTEM: Task ' + task.id + ' button logic:', {
            approval_status: task.approval_status,
            sorumlu_id: task.sorumlu_id,
            current_user_id: <?php echo $current_user_id; ?>,
            user_is_admin: <?php echo $is_admin ? 'true' : 'false'; ?>,
            user_is_editor: <?php echo $is_editor ? 'true' : 'false'; ?>
        });
        
        // SIMPLIFIED APPROVAL BUTTONS - Show if task is pending
        var approvalStatus = task.approval_status || 'pending';
        // TASK STATE MANAGEMENT: Control access based on approval status
        var canPerformOperations = (task.approval_status === 'approved');
        var isPending = (task.approval_status === 'pending');
        var isRejected = (task.approval_status === 'rejected');
        
        // Enhanced debugging for task state
        console.log('🔍 Task ' + task.id + ' state analysis:', {
            approval_status: task.approval_status,
            canPerformOperations: canPerformOperations,
            isPending: isPending,
            isRejected: isRejected,
            sorumlu_id: task.sorumlu_id,
            current_user_id: <?php echo $current_user_id; ?>
        });
        
        // Show approval/rejection buttons only for pending tasks
        if (isPending) {
            console.log('✅ Adding approval buttons for pending task ' + task.id);
            html += '<button class="bkm-btn bkm-btn-small" style="background: #28a745; color: white; margin-right: 8px;" onclick="newApproveTask(' + task.id + ')">✅ Kabul Et</button>';
            html += '<button class="bkm-btn bkm-btn-small" style="background: #dc3545; color: white; margin-right: 8px;" onclick="newRejectTask(' + task.id + ')">❌ Reddet</button>';
        } else if (task.approval_status === 'approved') {
            html += '<span class="bkm-status-badge" style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">✅ Onaylandı</span>';
        } else if (isRejected) {
            html += '<span class="bkm-status-badge" style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-right: 8px;">❌ Reddedildi</span>';
            if (task.rejection_reason) {
                html += '<span class="bkm-rejection-reason" style="font-size: 12px; color: #666; font-style: italic;" title="Red Sebebi: ' + task.rejection_reason + '">(' + task.rejection_reason.substring(0, 30) + (task.rejection_reason.length > 30 ? '...' : '') + ')</span>';
            }
        }
        
        // REJECTED TASKS: Show only status and rejection reason, NO BUTTONS
        if (isRejected) {
            // Only show rejection status and reason, no other buttons or actions
            console.log('❌ Task ' + task.id + ' is rejected - hiding all action buttons');
        } else {
            // SIMPLIFIED HISTORY AND EDIT BUTTONS - Show for admins/editors only (not for participant users)
            <?php if ($is_editor || $is_admin || (defined('WP_DEBUG') && WP_DEBUG)): ?>
            html += '<button class="bkm-btn bkm-btn-small" style="background: #ffc107; color: #212529; margin-right: 8px;" onclick="newShowTaskHistory(' + task.id + ')">📋 Geçmiş</button>';
            // Only allow edit for admin/editor users, not participant users
            if (<?php echo $is_admin ? 'true' : 'false'; ?> || <?php echo $is_editor ? 'true' : 'false'; ?>) {
                html += '<button class="bkm-btn bkm-btn-small" style="background: #6c757d; color: white; margin-right: 8px;" onclick="newEditTask(' + task.id + ')">✏️ Düzenle</button>';
                console.log('✅ Added edit button for admin/editor user');
            } else {
                console.log('ℹ️ Edit button not added - participant users cannot edit tasks');
            }
            console.log('✅ Added history button for privileged user or debug mode');
            <?php else: ?>
            console.log('ℹ️ History and edit buttons not added - insufficient permissions');
            <?php endif; ?>
            
            // Complete button (only for approved tasks that are not completed)
            if (!isCompleted && canPerformOperations) {
                html += '<button class="bkm-btn bkm-btn-small bkm-btn-success" onclick="markTaskComplete(' + task.id + ')">✓ Tamamla</button>';
            } else if (isPending && !isCompleted) {
                html += '<span class="bkm-status-info" style="color: #856404; font-size: 12px; font-style: italic;">⏳ Görev onaylandıktan sonra işlem yapabilirsiniz</span>';
            }
        }
        
        html += '</div>';
        
        // Note Form (hidden by default) - Enhanced to show for all tasks but with restrictions
        html += '<div id="note-form-' + task.id + '" class="bkm-note-form" style="display: none;">';
        
        if (canPerformOperations) {
            // Full note form for approved tasks
            html += '<form class="bkm-task-note-form-element">';
            html += '<input type="hidden" name="task_id" value="' + task.id + '" />';
            html += '<div class="bkm-note-form-row">';
            html += '<div class="bkm-note-textarea">';
            html += '<label for="note_content_' + task.id + '">Not İçeriği:</label>';
            html += '<textarea name="note_content" id="note_content_' + task.id + '" rows="3" placeholder="Notunuzu buraya yazın..." required></textarea>';
            html += '</div>';
            html += '<div class="bkm-note-progress">';
            html += '<label for="note_progress_' + task.id + '">İlerleme Durumu (%):</label>';
            html += '<input type="number" name="note_progress" id="note_progress_' + task.id + '" ';
            html += 'min="0" max="100" value="' + (task.ilerleme_durumu || 0) + '" placeholder="0-100" />';
            html += '<small>Mevcut: ' + (task.ilerleme_durumu || 0) + '%</small>';
            html += '</div>';
            html += '</div>';
            html += '<div class="bkm-form-actions">';
            html += '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">📝 Not Ekle ve İlerlemeyi Güncelle</button>';
            html += '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleNoteForm(' + task.id + ')">İptal</button>';
            html += '</div>';
            html += '</form>';
            console.log('✅ Added full note form for approved task ' + task.id);
        } else {
            // Restricted message for non-approved tasks
            html += '<div class="bkm-task-restriction" style="background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 6px; border: 1px solid #ffeaa7; text-align: center;">';
            if (isPending) {
                html += '⏳ Bu göreve not ekleyebilmek için önce görevi <strong>onaylamalısınız</strong>.';
            } else if (isRejected) {
                html += '❌ Reddedilen görevlere not eklenemez.';
            } else {
                html += '❓ Bu görevin durumu belirsiz. Lütfen sayfayı yenileyin.';
            }
            html += '<br><button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" style="margin-top: 10px;" onclick="toggleNoteForm(' + task.id + ')">Kapat</button>';
            html += '</div>';
            console.log('⚠️ Added restriction message for task ' + task.id + ' in state: ' + task.approval_status);
        }
        
        html += '</div>';
        console.log('📝 Note form container added for task ' + task.id);
        
        // Notes Section (hidden by default) - show for all tasks
        html += '<div id="notes-' + task.id + '" class="bkm-notes-section" style="display: none;">';
        html += '<div class="bkm-notes-content">';
        html += '<p style="text-align: center; color: #9e9e9e; font-style: italic; margin: 20px 0; padding: 30px; border: 2px dashed #e0e0e0; border-radius: 12px;">📝 Bu görev için henüz not bulunmamaktadır.</p>';
        html += '</div>';
        html += '</div>';
        
        html += '</div>';
    });
    
    html += '</div>';
    
    console.log('✅ HTML oluşturuldu, container\'a yazılıyor...', html.substring(0, 200) + '...');
    container.innerHTML = html;
    console.log('🎉 Container güncellendi!');
}

// Helper functions for date formatting
function formatDate(dateString) {
    if (!dateString) return '';
    var date = new Date(dateString);
    return date.toLocaleDateString('tr-TR');
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    var date = new Date(dateString);
    return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
}

// HTML escape function (simple version)
function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Görev notları fonksiyonları frontend.js'te tanımlandı - çakışmayı önlemek için buradakiler kaldırıldı

// Mark task as complete
function markTaskComplete(taskId) {
    if (!confirm('Bu görevi tamamlandı olarak işaretlemek istediğinizden emin misiniz?')) {
        return;
    }
    
    console.log('🎯 Görev tamamlanıyor, Task ID:', taskId);
    
    if (typeof bkmFrontend === 'undefined') {
        alert('Sistem hatası: bkmFrontend objesi bulunamadı. Sayfayı yenileyin.');
        return;
    }
    
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'bkm_complete_task',
            task_id: taskId,
            nonce: bkmFrontend.nonce
        },
        beforeSend: function() {
            // Disable the button to prevent multiple clicks
            var btn = document.querySelector('[onclick="markTaskComplete(' + taskId + ')"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '⏳ Tamamlanıyor...';
            }
        },
        success: function(response) {
            console.log('✅ Task completion response:', response);
            if (response.success) {
                // Refresh the tasks list to show updated status
                var actionId = response.data.action_id;
                if (actionId) {
                    loadTasksForAction(actionId);
                    
                    // Also refresh the actions table to show updated progress if action progress was updated
                    if (response.data.action_progress_updated && typeof refreshActions === 'function') {
                        setTimeout(function() {
                            refreshActions();
                        }, 1000); // Small delay to ensure database update is complete
                    }
                }
                
                var message = response.data.message || 'Görev başarıyla tamamlandı!';
                if (response.data.action_progress_updated) {
                    message += ' (Aksiyon ilerlemesi: ' + response.data.new_action_progress + '%)';
                }
                
                // Show success notification if available
                if (typeof showNotification === 'function') {
                    showNotification(message, 'success');
                }
            } else {
                alert('Hata: ' + (response.data || 'Görev tamamlanamadı'));
                // Re-enable button on error
                var btn = document.querySelector('[onclick="markTaskComplete(' + taskId + ')"]');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '✓ Tamamla';
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Task completion error:', error);
            alert('Bağlantı hatası: ' + error);
            // Re-enable button on error
            var btn = document.querySelector('[onclick="markTaskComplete(' + taskId + ')"]');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '✓ Tamamla';
            }
        }
    });
}

function toggleNoteForm(taskId) {
    console.log('📝 toggleNoteForm çağrıldı, taskId:', taskId);
    var noteForm = document.getElementById('note-form-' + taskId);
    if (noteForm) {
        if (noteForm.style.display === 'none' || noteForm.style.display === '') {
            // Close other note forms first
            var otherNoteForms = document.querySelectorAll('.bkm-note-form');
            otherNoteForms.forEach(function(form) {
                if (form.id !== 'note-form-' + taskId) {
                    form.style.display = 'none';
                }
            });
            
            noteForm.style.display = 'block';
            // Focus on textarea
            var textarea = noteForm.querySelector('textarea[name="note_content"]');
            if (textarea) {
                textarea.focus();
            }
        } else {
            noteForm.style.display = 'none';
        }
    } else {
        console.error('❌ Not formu bulunamadı, ID:', 'note-form-' + taskId);
    }
}

function toggleReplyForm(taskId, noteId) {
    var replyForm = document.getElementById('reply-form-' + taskId + '-' + noteId);
    if (replyForm.style.display === 'none' || replyForm.style.display === '') {
        replyForm.style.display = 'block';
    } else {
        replyForm.style.display = 'none';
    }
}

// Kullanıcı listesini dinamik yükleme
function loadUsersList() {
    console.log('🔄 Kullanıcı listesi yükleniyor...');
    
    // jQuery kontrolü
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery bulunamadı!');
        var usersList = document.getElementById('users-list');
        if (usersList) {
            usersList.innerHTML = '<div class="bkm-no-items">❌ jQuery yüklenmemiş.</div>';
        }
        return;
    }
    
    // Yükleniyor mesajı göster
    var usersList = document.getElementById('users-list');
    if (usersList) {
        usersList.innerHTML = '<div style="text-align: center; padding: 20px;">⏳ Kullanıcılar yükleniyor...</div>';
    }
    
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    console.log('🔗 AJAX URL:', ajaxUrl);
    
    // AJAX isteği
    jQuery.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            action: 'bkm_get_users'
        },
        timeout: 30000, // 30 saniye timeout
        success: function(response) {
            console.log('✅ Kullanıcı listesi yanıtı:', response);
            
            if (response && response.success && response.data) {
                var users = Array.isArray(response.data) ? response.data : (response.data && response.data.users ? response.data.users : []);
                var html = '';
                
                if (users.length === 0) {
                    html = '<div class="bkm-no-items">⚠️ Kullanıcı bulunamadı.<br><small>WordPress\'te kayıtlı kullanıcı bulunmamaktadır.</small></div>';
                } else {
                    users.forEach(function(user) {
                        var userRoles = user.roles ? user.roles.join(', ') : 'Rol yok';
                        var registerDate = new Date(user.user_registered).toLocaleDateString('tr-TR');
                        var canDelete = user.ID != <?php echo $current_user->ID; ?>;
                        var firstName = user.first_name || '';
                        var lastName = user.last_name || '';
                        var fullName = (firstName + ' ' + lastName).trim();
                        var displayName = fullName || user.display_name || user.user_login || 'İsimsiz';
                        
                        html += '<div class="bkm-item" data-id="' + user.ID + '">';
                        html += '  <div class="bkm-item-content">';
                        html += '    <strong>' + displayName + '</strong>';
                        html += '    <p>';
                        html += '      <span class="bkm-user-email">📧 ' + (user.user_email || 'Email yok') + '</span><br>';
                        html += '      <span class="bkm-user-role">👤 ' + userRoles + '</span><br>';
                        html += '      <span class="bkm-user-registered">📅 ' + registerDate + '</span>';
                        html += '    </p>';
                        html += '  </div>';
                        html += '  <div class="bkm-item-actions">';
                        html += '    <button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editUser(' + user.ID + ', \'' + (user.user_login || '') + '\', \'' + (user.user_email || '') + '\', \'' + firstName + '\', \'' + lastName + '\', \'' + userRoles + '\')">';
                        html += '      ✏️ Düzenle';
                        html += '    </button>';
                        if (canDelete) {
                            html += '    <button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deleteUser(' + user.ID + ', \'' + displayName + '\')">';
                            html += '      🗑️ Sil';
                            html += '    </button>';
                        }
                        html += '  </div>';
                        html += '</div>';
                    });
                }
                
                if (usersList) {
                    usersList.innerHTML = html;
                }
                
                // Başlığı güncelle
                var usersHeader = document.querySelector('.bkm-management-list h4');
                if (usersHeader) {
                    usersHeader.textContent = 'Mevcut Kullanıcılar (' + users.length + ' kullanıcı)';
                }
                
                console.log('✅ ' + users.length + ' kullanıcı yüklendi');
            } else {
                console.error('❌ Kullanıcı listesi yüklenemedi:', response);
                if (usersList) {
                    var errorMsg = response && response.data && response.data.message ? response.data.message : 'Bilinmeyen hata';
                    usersList.innerHTML = '<div class="bkm-no-items">❌ ' + errorMsg + '</div>';
                }
                
                // Hata durumunda başlığı düzelt
                var usersHeader = document.querySelector('.bkm-management-list h4');
                if (usersHeader) {
                    usersHeader.textContent = 'Mevcut Kullanıcılar';
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('💥 AJAX hatası:', {
                status: status,
                error: error,
                responseText: xhr.responseText,
                readyState: xhr.readyState
            });
            
            var errorMessage = 'Bağlantı hatası';
            if (xhr.status === 0) {
                errorMessage = 'Bağlantı kurulamadı';
            } else if (xhr.status === 403) {
                errorMessage = 'Yetki hatası';
            } else if (xhr.status === 404) {
                errorMessage = 'AJAX endpoint bulunamadı';
            } else if (xhr.status === 500) {
                errorMessage = 'Sunucu hatası';
            }
            
            if (usersList) {
                usersList.innerHTML = '<div class="bkm-no-items">❌ ' + errorMessage + ' (' + xhr.status + ')</div>';
            }
            
            // Hata durumunda başlığı düzelt
            var usersHeader = document.querySelector('.bkm-management-list h4');
            if (usersHeader) {
                usersHeader.textContent = 'Mevcut Kullanıcılar';
            }
        }
    });
}

// Sayfa yüklendiğinde kullanıcıları yükle
jQuery(document).ready(function() {
    console.log('📄 Dashboard sayfa hazır');
    
    // Tab değişim kontrolü için event handler
    function setupUserTabHandler() {
        // Kullanıcı tab butonunu bul
        var userTabButton = jQuery('[data-tab="users"]');
        console.log('🔍 Kullanıcı tab butonu:', userTabButton.length);
        
        if (userTabButton.length > 0) {
            // Tab'e tıklandığında kullanıcıları yükle
            userTabButton.on('click', function() {
                console.log('👆 Kullanıcı tab\'ına tıklandı');
                setTimeout(function() {
                    loadUsersList();
                }, 200); // Tab'in açılması için bekle
            });
        } else {
            console.log('⚠️ Kullanıcı tab butonu bulunamadı');
        }
    }
    
    // İlk yükleme - kullanıcıları yükle
    setTimeout(function() {
        console.log('🚀 İlk kullanıcı yüklemesi başlatılıyor...');
        loadUsersList();
    }, 1000);
    
    // Tab handler'ı kur
    setupUserTabHandler();
    
    // Ayarlar paneli açıldığında da handler'ı kur
    jQuery(document).on('click', '.bkm-settings-toggle', function() {
        console.log('⚙️ Ayarlar paneli açıldı');
        setTimeout(setupUserTabHandler, 300);
    });
    
    // Manual test butonu (geliştirme için)
    if (typeof window.testLoadUsers === 'undefined') {
        window.testLoadUsers = function() {
            console.log('🧪 Manuel kullanıcı yükleme testi');
            loadUsersList();
        };
    }
});

// Performans yönetimi fonksiyonları
function clearPerformanceForm() {
    document.getElementById('performance-name').value = '';
    document.getElementById('performance-description').value = '';
    // Edit modunu sıfırla
    var form = document.getElementById('bkm-performance-form-element');
    if (form) {
        form.removeAttribute('data-edit-id');
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.textContent = '✅ Performans Ekle';
        }
    }
}

function editPerformance(id, name, description) {
    document.getElementById('performance-name').value = name;
    document.getElementById('performance-description').value = description;
    
    // Form'u edit moduna al
    var form = document.getElementById('bkm-performance-form-element');
    if (form) {
        form.setAttribute('data-edit-id', id);
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.textContent = '📝 Performans Güncelle';
        }
    }
}

function deletePerformance(id) {
    if (!confirm('Bu performans verisini silmek istediğinizden emin misiniz?')) {
        return;
    }
    
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'bkm_delete_performance',
            id: id
        },
        success: function(response) {
            if (response.success) {
                alert('✅ ' + response.data);
                loadPerformancesList();
            } else {
                alert('❌ ' + response.data);
            }
        },
        error: function() {
            alert('❌ Silme işlemi sırasında bir hata oluştu.');
        }
    });
}

function loadPerformancesList() {
    console.log('🔄 Performans listesi yükleniyor...');
    
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: {
            action: 'bkm_get_performances'
        },
        success: function(response) {
            if (response.success && response.data) {
                var performances = response.data;
                var html = '';
                
                if (performances.length === 0) {
                    html = '<div class="bkm-no-items">⚠️ Henüz performans verisi bulunmamaktadır.</div>';
                } else {
                    performances.forEach(function(performance) {
                        html += '<div class="bkm-item" data-id="' + performance.id + '">';
                        html += '<div class="bkm-item-content">';
                        html += '<strong>' + performance.name + '</strong>';
                        if (performance.description) {
                            html += '<p>' + performance.description + '</p>';
                        }
                        html += '</div>';
                        html += '<div class="bkm-item-actions">';
                        html += '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editPerformance(' + performance.id + ', \'' + performance.name.replace(/'/g, "\\'") + '\', \'' + (performance.description || '').replace(/'/g, "\\'") + '\')">';
                        html += '✏️ Düzenle</button>';
                        html += '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deletePerformance(' + performance.id + ')">';
                        html += '🗑️ Sil</button>';
                        html += '</div></div>';
                    });
                }
                
                var performancesList = document.getElementById('performances-list');
                if (performancesList) {
                    performancesList.innerHTML = html;
                }
            }
        },
        error: function() {
            var performancesList = document.getElementById('performances-list');
            if (performancesList) {
                performancesList.innerHTML = '<div class="bkm-no-items">❌ Performans listesi yüklenirken hata oluştu.</div>';
            }
        }
    });
}

// Performans form submit handler
jQuery(document).ready(function() {
    jQuery('#bkm-performance-form-element').submit(function(e) {
        e.preventDefault();
        
        var form = jQuery(this);
        var name = jQuery('#performance-name').val().trim();
        var description = jQuery('#performance-description').val().trim();
        var editId = form.attr('data-edit-id');
        
        if (!name) {
            alert('❌ Performans adı boş olamaz.');
            return;
        }
        
        var actionType = editId ? 'bkm_edit_performance' : 'bkm_add_performance';
        var data = {
            action: actionType,
            name: name,
            description: description
        };
        
        if (editId) {
            data.id = editId;
        }
        
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data);
                    clearPerformanceForm();
                    loadPerformancesList();
                } else {
                    alert('❌ ' + response.data);
                }
            },
            error: function() {
                alert('❌ İşlem sırasında bir hata oluştu.');
            }
        });
    });
    
    // Performans sekmesi açıldığında listeyi yükle
    jQuery('[data-tab="performance"]').on('click', function() {
        setTimeout(function() {
            loadPerformancesList();
        }, 200);
    });
});

// Kullanıcı ekleme formu submit handler'ında, başarıyla eklendikten sonra loadUsersList() çağır
jQuery(document).on('submit', '#bkm-user-form-element', function(e) {
    // ... mevcut kod ...
    // Başarıyla eklendiyse:
    loadUsersList();
});

// Sayfa yüklendiğinde otomatik olarak kullanıcıları yükle
jQuery(document).ready(function() {
    loadUsersList();
});

// Task Editing and Approval Functions
function editTask(taskId) {
    console.log('✏️ Edit task called for ID:', taskId);
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        alert('❌ Sistem hatası: JavaScript bağlantısı başarısız. Sayfayı yenileyin.');
        return;
    }
    
    // Get current task data
    jQuery.post(bkmFrontend.ajax_url, {
        action: 'bkm_get_tasks',
        action_id: 0, // Will get all tasks, we'll filter on frontend
        nonce: bkmFrontend.nonce
    }, function(response) {
        console.log('📬 Edit task response:', response);
        if (response && response.success && response.data) {
            var task = response.data.find(t => t.id == taskId);
            if (task) {
                showTaskEditModal(task);
            } else {
                alert('Görev bulunamadı.');
            }
        } else {
            var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
            alert('Görev bilgileri alınamadı: ' + errorMsg);
            console.error('Edit task failed:', response);
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Edit Task Error:', {xhr: xhr, status: status, error: error});
        alert('❌ Ağ hatası: Görev bilgileri alınamadı. (' + status + ')');
    });
}

function showTaskEditModal(task) {
    // Create modal HTML
    var modalHtml = `
        <div id="task-edit-modal" class="bkm-modal-overlay" onclick="closeTaskEditModal(event)">
            <div class="bkm-modal-content" onclick="event.stopPropagation()">
                <div class="bkm-modal-header">
                    <h3>✏️ Görev Düzenle</h3>
                    <button type="button" class="bkm-modal-close" onclick="closeTaskEditModal()">&times;</button>
                </div>
                <div class="bkm-modal-body">
                    <form id="task-edit-form">
                        <input type="hidden" name="task_id" value="${task.id}">
                        
                        <div class="bkm-form-group">
                            <label for="edit_task_title">Görev Başlığı:</label>
                            <input type="text" id="edit_task_title" name="title" value="${escapeHtml(task.title || '')}" required>
                        </div>
                        
                        <div class="bkm-form-group">
                            <label for="edit_task_content">Görev İçeriği:</label>
                            <textarea id="edit_task_content" name="content" rows="4" required>${escapeHtml(task.content || '')}</textarea>
                        </div>
                        
                        <div class="bkm-form-group">
                            <label for="edit_task_responsible">Sorumlu Kişi:</label>
                            <select id="edit_task_responsible" name="responsible" required>
                                <option value="">Sorumlu Seçin</option>
                                <!-- Will be populated by loadUsersForSelect -->
                            </select>
                        </div>
                        
                        <div class="bkm-form-row">
                            <div class="bkm-form-group">
                                <label for="edit_task_start_date">Başlangıç Tarihi:</label>
                                <input type="date" id="edit_task_start_date" name="start_date" value="${task.baslangic_tarihi || ''}">
                            </div>
                            <div class="bkm-form-group">
                                <label for="edit_task_target_date">Hedef Bitiş Tarihi:</label>
                                <input type="date" id="edit_task_target_date" name="target_date" value="${task.hedef_bitis_tarihi || ''}" required>
                            </div>
                        </div>
                        
                        <div class="bkm-form-group">
                            <label for="edit_task_progress">İlerleme Durumu (%):</label>
                            <input type="number" id="edit_task_progress" name="progress" min="0" max="100" value="${task.ilerleme_durumu || 0}">
                        </div>
                        
                        <div class="bkm-form-group">
                            <label for="edit_reason">Düzenleme Sebebi: <span style="color: red;">*</span></label>
                            <textarea id="edit_reason" name="edit_reason" rows="3" placeholder="Neden bu değişiklikleri yapıyorsunuz?" required></textarea>
                        </div>
                        
                        <div class="bkm-form-actions">
                            <button type="submit" class="bkm-btn bkm-btn-primary">💾 Değişiklikleri Kaydet</button>
                            <button type="button" class="bkm-btn bkm-btn-secondary" onclick="closeTaskEditModal()">İptal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Load users for select
    loadUsersForSelect('edit_task_responsible', task.sorumlu_id);
    
    // Bind form submit
    document.getElementById('task-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        submitTaskEdit();
    });
}

function loadUsersForSelect(selectId, selectedUserId) {
    jQuery.post(bkmFrontend.ajax_url, {
        action: 'bkm_get_users',
        nonce: bkmFrontend.nonce
    }, function(response) {
        if (response.success && response.data.users) {
            var select = document.getElementById(selectId);
            if (select) {
                // Clear existing options except first
                select.innerHTML = '<option value="">Sorumlu Seçin</option>';
                
                // Add user options
                response.data.users.forEach(function(user) {
                    var option = document.createElement('option');
                    option.value = user.ID;
                    option.textContent = user.display_name + ' (' + user.user_login + ')';
                    if (user.ID == selectedUserId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }
        }
    });
}

function submitTaskEdit() {
    var form = document.getElementById('task-edit-form');
    var formData = new FormData(form);
    
    // Add AJAX parameters
    formData.append('action', 'bkm_edit_task');
    formData.append('nonce', bkmFrontend.nonce);
    
    var submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Kaydediliyor...';
    
    jQuery.post({
        url: bkmFrontend.ajax_url,
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('✅ Görev başarıyla güncellendi!');
                closeTaskEditModal();
                // Refresh tasks
                refreshActions();
            } else {
                alert('❌ Hata: ' + (response.data || 'Görev güncellenemedi'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = '💾 Değişiklikleri Kaydet';
            }
        },
        error: function(xhr, status, error) {
            alert('❌ Bağlantı hatası: ' + error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '💾 Değişiklikleri Kaydet';
        }
    });
}

function closeTaskEditModal(event) {
    if (event && event.target !== event.currentTarget) return;
    var modal = document.getElementById('task-edit-modal');
    if (modal) {
        modal.remove();
    }
}

function showTaskHistory(taskId) {
    console.log('📋 Show task history for ID:', taskId);
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        alert('❌ Sistem hatası: JavaScript bağlantısı başarısız. Sayfayı yenileyin.');
        return;
    }
    
    jQuery.post(bkmFrontend.ajax_url, {
        action: 'bkm_get_task_history',
        task_id: taskId,
        nonce: bkmFrontend.nonce
    }, function(response) {
        console.log('📬 Task history response:', response);
        if (response && response.success) {
            displayTaskHistoryModal(response.data, taskId);
        } else {
            var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
            alert('❌ Görev geçmişi alınamadı: ' + errorMsg);
            console.error('Task history failed:', response);
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Task History Error:', {xhr: xhr, status: status, error: error});
        alert('❌ Ağ hatası: Görev geçmişi alınamadı. (' + status + ')');
    });
}

function displayTaskHistoryModal(history, taskId) {
    var modalHtml = `
        <div id="task-history-modal" class="bkm-modal-overlay" onclick="closeTaskHistoryModal(event)">
            <div class="bkm-modal-content" onclick="event.stopPropagation()" style="width: 85%; max-width: 1200px;">
                <div class="bkm-modal-header">
                    <h3>📋 Görev Geçmişi (ID: ${taskId})</h3>
                    <button type="button" class="bkm-modal-close" onclick="closeTaskHistoryModal()">&times;</button>
                </div>
                <div class="bkm-modal-body">
    `;
    
    if (history.length === 0) {
        modalHtml += '<p style="text-align: center; color: #666; padding: 20px;">Bu görev için henüz düzenleme geçmişi bulunmamaktadır.</p>';
    } else {
        modalHtml += '<div class="bkm-history-timeline">';
        history.forEach(function(entry) {
            modalHtml += `
                <div class="bkm-history-entry">
                    <div class="bkm-history-header">
                        <strong>${escapeHtml(entry.editor_name)}</strong>
                        <span class="bkm-history-date">${entry.created_date}</span>
                    </div>
                    <div class="bkm-history-reason">
                        <strong>Sebep:</strong> ${escapeHtml(entry.edit_reason)}
                    </div>
                    <div class="bkm-history-changes">
                        <strong>Değişen Alanlar:</strong> ${escapeHtml(entry.field_changes)}
                    </div>
                </div>
            `;
        });
        modalHtml += '</div>';
    }
    
    modalHtml += `
                </div>
                <div class="bkm-modal-footer">
                    <button type="button" class="bkm-btn bkm-btn-secondary" onclick="closeTaskHistoryModal()">Kapat</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// NEW SIMPLIFIED TASK ACTION FUNCTIONS
// IN-PAGE NOTIFICATION SYSTEM
function showInPageNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    var existing = document.querySelectorAll('.bkm-notification');
    existing.forEach(function(el) { el.remove(); });
    
    var notification = document.createElement('div');
    notification.className = 'bkm-notification bkm-notification-' + type;
    notification.innerHTML = '<div class="bkm-notification-content">' + message + '</div>';
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        padding: 15px 20px;
        border-radius: 8px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        cursor: pointer;
        transition: all 0.3s ease;
        transform: translateX(100%);
    `;
    
    // Type-specific colors
    var colors = {
        success: { bg: '#d4edda', text: '#155724', border: '#c3e6cb' },
        error: { bg: '#f8d7da', text: '#721c24', border: '#f5c6cb' },
        warning: { bg: '#fff3cd', text: '#856404', border: '#ffeaa7' },
        info: { bg: '#d1ecf1', text: '#0c5460', border: '#bee5eb' }
    };
    
    var color = colors[type] || colors.info;
    notification.style.backgroundColor = color.bg;
    notification.style.color = color.text;
    notification.style.border = '1px solid ' + color.border;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(function() {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Auto remove
    if (duration > 0) {
        setTimeout(function() {
            if (notification.parentNode) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(function() {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, duration);
    }
    
    // Click to remove
    notification.addEventListener('click', function() {
        notification.style.transform = 'translateX(100%)';
        setTimeout(function() {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    });
}

// MODAL SYSTEM
function showConfirmationModal(title, message, onConfirm, onCancel) {
    // Remove existing modals
    var existingModals = document.querySelectorAll('.bkm-modal');
    existingModals.forEach(function(modal) { modal.remove(); });
    
    var modal = document.createElement('div');
    modal.className = 'bkm-modal';
    modal.innerHTML = `
        <div class="bkm-modal-backdrop">
            <div class="bkm-modal-content">
                <div class="bkm-modal-header">
                    <h3>${title}</h3>
                    <button class="bkm-modal-close" onclick="closeBkmModal()">&times;</button>
                </div>
                <div class="bkm-modal-body">
                    <p>${message}</p>
                </div>
                <div class="bkm-modal-footer">
                    <button class="bkm-btn bkm-btn-secondary" onclick="closeBkmModal()">İptal</button>
                    <button class="bkm-btn bkm-btn-primary" id="bkm-confirm-btn">Onayla</button>
                </div>
            </div>
        </div>
    `;
    
    // Add styles
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(modal);
    
    // Add modal styles
    var style = document.createElement('style');
    style.textContent = `
        .bkm-modal-backdrop {
            background: rgba(0,0,0,0.5);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .bkm-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: bkm-modal-in 0.3s ease;
        }
        .bkm-modal-header {
            padding: 20px 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .bkm-modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .bkm-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bkm-modal-body {
            padding: 20px;
        }
        .bkm-modal-footer {
            padding: 0 20px 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .bkm-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .bkm-btn-primary {
            background: #007cba;
            color: white;
        }
        .bkm-btn-secondary {
            background: #6c757d;
            color: white;
        }
        @keyframes bkm-modal-in {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    // Event handlers
    document.getElementById('bkm-confirm-btn').addEventListener('click', function() {
        closeBkmModal();
        if (onConfirm) onConfirm();
    });
    
    // Close on backdrop click
    modal.querySelector('.bkm-modal-backdrop').addEventListener('click', function(e) {
        if (e.target === e.currentTarget) {
            closeBkmModal();
            if (onCancel) onCancel();
        }
    });
}

function closeBkmModal() {
    var modals = document.querySelectorAll('.bkm-modal');
    modals.forEach(function(modal) { modal.remove(); });
}

function newApproveTask(taskId) {
    console.log('🚀 NEW: Approve task called for ID:', taskId);
    
    showConfirmationModal(
        '✅ Görevi Kabul Et',
        'Bu görevi kabul etmek istediğinizden emin misiniz?',
        function() {
            // Confirmed - proceed with approval
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bkm_approve_task',
                    task_id: taskId,
                    nonce: '<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>'
                },
                success: function(response) {
                    console.log('✅ NEW: Approve response:', response);
                    if (response && response.success) {
                        showInPageNotification('✅ Görev başarıyla kabul edildi!', 'success');
                        // Reload tasks to show updated status
                        var actionId = getCurrentActionId();
                        if (actionId) {
                            loadTasksForAction(actionId);
                        }
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
                        showInPageNotification('❌ Hata: ' + errorMsg, 'error');
                        console.error('Approve error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ NEW: Approve AJAX error:', error, xhr.responseText);
                    showInPageNotification('❌ Bağlantı hatası: ' + error, 'error');
                }
            });
        }
    );
}

function showRejectTaskModal(taskId) {
    // Remove existing modals
    var existingModals = document.querySelectorAll('.bkm-modal');
    existingModals.forEach(function(modal) { modal.remove(); });
    
    var modal = document.createElement('div');
    modal.className = 'bkm-modal';
    modal.innerHTML = `
        <div class="bkm-modal-backdrop" style="background: rgba(0,0,0,0.5);">
            <div class="bkm-modal-content" style="background: white; border-radius: 8px; max-width: 600px; width: 95%; max-height: 85vh; overflow-y: auto; border: 1px solid #ddd;">
                <div class="bkm-modal-header" style="padding: 20px 24px; border-bottom: 1px solid #eee; position: sticky; top: 0; background: white; z-index: 1;">
                    <h3 style="margin: 0; color: #d32f2f; display: flex; align-items: center; gap: 8px;">❌ Görevi Reddet</h3>
                    <button class="bkm-modal-close" onclick="closeBkmModal()" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div class="bkm-modal-body" style="padding: 24px;">
                    <p style="margin: 0 0 15px 0; color: #333;">Bu görevi neden reddediyorsunuz? Lütfen sebebini açıklayın:</p>
                    <textarea id="rejection-reason" 
                             placeholder="Red sebebinizi buraya yazın..." 
                             style="width: 100%; height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical; font-size: 14px; box-sizing: border-box;"
                             required></textarea>
                    <div id="rejection-error" style="color: #d32f2f; margin-top: 10px; display: none; font-size: 14px;">
                        Red sebebi girmeniz zorunludur.
                    </div>
                </div>
                <div class="bkm-modal-footer" style="display: flex !important; gap: 12px; justify-content: flex-end; padding: 20px; border-top: 1px solid #eee; background: #f8f9fa; flex-wrap: nowrap;">
                    <button class="bkm-btn bkm-btn-secondary" onclick="closeBkmModal()" style="padding: 12px 24px; border: 1px solid #ddd; background: #fff; color: #333; border-radius: 6px; cursor: pointer; font-weight: 500; min-width: 80px; margin-right: 8px;">İptal</button>
                    <button class="bkm-btn bkm-btn-danger bkm-reject-confirm-button" id="bkm-reject-confirm-btn" style="padding: 14px 28px !important; background: #dc3545 !important; color: white !important; border: 2px solid #dc3545 !important; border-radius: 6px; cursor: pointer; font-weight: 600; min-width: 100px; font-size: 14px; display: inline-block !important; visibility: visible !important; opacity: 1 !important;">Onayla</button>
                </div>
            </div>
        </div>
    `;
    
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(modal);
    
    // Focus on textarea
    setTimeout(function() {
        document.getElementById('rejection-reason').focus();
    }, 100);
    
    // Enhanced button event handler with debugging
    setTimeout(function() {
        var confirmBtn = document.getElementById('bkm-reject-confirm-btn');
        console.log('🔧 Confirm button found:', confirmBtn);
        
        if (confirmBtn) {
            // Remove any existing listeners
            confirmBtn.removeEventListener('click', handleRejectConfirm);
            
            // Add new listener
            confirmBtn.addEventListener('click', handleRejectConfirm);
            console.log('✅ Confirm button event listener attached');
            
            // Make sure button is visible
            confirmBtn.style.display = 'inline-block';
            confirmBtn.style.visibility = 'visible';
            confirmBtn.style.opacity = '1';
        } else {
            console.error('❌ Confirm button not found!');
        }
    }, 200);
    
    function handleRejectConfirm() {
        console.log('🚀 Reject confirm button clicked');
        var reason = document.getElementById('rejection-reason').value.trim();
        var errorDiv = document.getElementById('rejection-error');
        
        if (!reason) {
            errorDiv.style.display = 'block';
            document.getElementById('rejection-reason').focus();
            return;
        }
        
        errorDiv.style.display = 'none';
        closeBkmModal();
        
        // Proceed with rejection
        submitTaskRejection(taskId, reason);
    }
    
    // Close on backdrop click
    modal.querySelector('.bkm-modal-backdrop').addEventListener('click', function(e) {
        if (e.target === e.currentTarget) {
            closeBkmModal();
        }
    });
    
    // Enter key in textarea submits (with Ctrl/Cmd)
    document.getElementById('rejection-reason').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            document.getElementById('bkm-reject-confirm-btn').click();
        }
    });
}

function submitTaskRejection(taskId, reason) {
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'bkm_reject_task',
            task_id: taskId,
            rejection_reason: reason,
            nonce: '<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>'
        },
        success: function(response) {
            console.log('✅ NEW: Reject response:', response);
            if (response && response.success) {
                showInPageNotification('✅ Görev başarıyla reddedildi!', 'success');
                // Reload tasks to show updated status
                var actionId = getCurrentActionId();
                if (actionId) {
                    loadTasksForAction(actionId);
                }
            } else {
                var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
                showInPageNotification('❌ Hata: ' + errorMsg, 'error');
                console.error('Reject error:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ NEW: Reject AJAX error:', error, xhr.responseText);
            showInPageNotification('❌ Bağlantı hatası: ' + error, 'error');
        }
    });
}

function newRejectTask(taskId) {
    console.log('🚀 NEW: Reject task called for ID:', taskId);
    showSimpleRejectModal(taskId);
}

function showSimpleRejectModal(taskId) {
    // Remove existing modals
    var existingModals = document.querySelectorAll('.bkm-modal, .bkm-reject-modal');
    existingModals.forEach(function(modal) { modal.remove(); });
    
    // Create a simple, guaranteed-to-work modal
    var modalHTML = `
        <div class="bkm-reject-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 8px; width: 90%; max-width: 600px; border: 2px solid #ddd; margin: 20px;">
                <!-- Header -->
                <div style="padding: 20px 24px; border-bottom: 1px solid #eee; background: #f8f9fa; position: relative;">
                    <h3 style="margin: 0; color: #d32f2f; font-size: 18px;">❌ Görevi Reddet</h3>
                    <button onclick="closeRejectModal()" style="position: absolute; top: 20px; right: 24px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                
                <!-- Body -->
                <div style="padding: 24px;">
                    <p style="margin: 0 0 15px 0; color: #333; font-size: 14px;">Bu görevi neden reddediyorsunuz? Lütfen sebebini açıklayın:</p>
                    <textarea id="simple-rejection-reason" 
                             placeholder="Red sebebinizi buraya yazın..." 
                             style="width: 100%; height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical; font-size: 14px; box-sizing: border-box;"
                             required></textarea>
                    <div id="simple-rejection-error" style="color: #d32f2f; margin-top: 10px; display: none; font-size: 14px;">
                        Red sebebi girmeniz zorunludur.
                    </div>
                </div>
                
                <!-- Footer with clearly visible buttons -->
                <div style="padding: 20px; border-top: 1px solid #eee; background: #f8f9fa; text-align: right;">
                    <button onclick="closeRejectModal()" style="padding: 12px 24px; margin-right: 12px; border: 1px solid #ddd; background: #fff; color: #333; border-radius: 6px; cursor: pointer; font-weight: 500;">İptal</button>
                    <button onclick="confirmRejectTask(${taskId})" style="padding: 14px 28px; background: #dc3545; color: white; border: 2px solid #dc3545; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">Onayla</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Focus on textarea
    setTimeout(function() {
        var textarea = document.getElementById('simple-rejection-reason');
        if (textarea) textarea.focus();
    }, 100);
}

function closeRejectModal() {
    var modal = document.querySelector('.bkm-reject-modal');
    if (modal) modal.remove();
}

function confirmRejectTask(taskId) {
    var reason = document.getElementById('simple-rejection-reason').value.trim();
    var errorDiv = document.getElementById('simple-rejection-error');
    
    if (!reason) {
        errorDiv.style.display = 'block';
        document.getElementById('simple-rejection-reason').focus();
        return;
    }
    
    errorDiv.style.display = 'none';
    closeRejectModal();
    
    // Proceed with rejection
    submitTaskRejection(taskId, reason);
}

function showEditTaskModal(taskId) {
    // First, get task details
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'bkm_get_task_details',
            task_id: taskId,
            nonce: '<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>'
        },
        success: function(response) {
            if (response && response.success && response.data) {
                var task = response.data.task;
                var users = response.data.users;
                showEditModalWithData(taskId, task, users);
            } else {
                showInPageNotification('❌ Görev bilgileri alınamadı', 'error');
            }
        },
        error: function() {
            showInPageNotification('❌ Bağlantı hatası', 'error');
        }
    });
}

function showEditModalWithData(taskId, task, users) {
    // Remove existing modals
    var existingModals = document.querySelectorAll('.bkm-modal');
    existingModals.forEach(function(modal) { modal.remove(); });
    
    var usersOptions = '';
    users.forEach(function(user) {
        var selected = user.id == task.responsible_id ? ' selected' : '';
        usersOptions += '<option value="' + user.id + '"' + selected + '>' + user.name + '</option>';
    });
    
    var modal = document.createElement('div');
    modal.className = 'bkm-modal';
    modal.innerHTML = `
        <div class="bkm-modal-backdrop" style="background: rgba(0,0,0,0.5);">
            <div class="bkm-modal-content" style="background: white; border-radius: 8px; max-width: 900px; width: 95%; max-height: 90vh; overflow-y: auto; border: 1px solid #ddd;">
                <div class="bkm-modal-header" style="padding: 20px 24px; border-bottom: 1px solid #eee; position: sticky; top: 0; background: white; z-index: 1;">
                    <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 8px;">✏️ Görevi Düzenle</h3>
                    <button class="bkm-modal-close" onclick="closeBkmModal()" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div class="bkm-modal-body" style="padding: 24px;">
                    <form id="edit-task-form">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Görev Başlığı:</label>
                                <input type="text" id="edit-task-title" value="${task.title || ''}" 
                                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;" />
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Sorumlu Kişi:</label>
                                <select id="edit-task-responsible" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                    <option value="">Seçiniz...</option>
                                    ${usersOptions}
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Görev İçeriği:</label>
                            <textarea id="edit-task-content" 
                                     style="width: 100%; height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; resize: vertical; font-size: 14px; box-sizing: border-box;"
                                     >${task.content || ''}</textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">İlerleme Durumu (%):</label>
                                <input type="number" id="edit-task-progress" value="${task.progress || 0}" 
                                       min="0" max="100"
                                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;" />
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Başlangıç Tarihi:</label>
                                <input type="date" id="edit-task-start-date" value="${task.start_date || ''}" 
                                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;" />
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Hedef Tarihi:</label>
                                <input type="date" id="edit-task-target-date" value="${task.target_date || ''}" 
                                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;" />
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Düzenleme Sebebi:</label>
                            <textarea id="edit-reason" 
                                     placeholder="Bu düzenlemeyi neden yaptığınızı açıklayın..."
                                     style="width: 100%; height: 100px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; resize: vertical; font-size: 14px; box-sizing: border-box;"
                                     required></textarea>
                        </div>
                        
                        <div id="edit-error" style="color: #d32f2f; margin-top: 10px; display: none; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                            Lütfen düzenleme sebebini belirtin.
                        </div>
                    </form>
                </div>
                <div class="bkm-modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; padding: 20px; border-top: 1px solid #eee; background: #f8f9fa;">
                    <button class="bkm-btn bkm-btn-secondary" onclick="closeBkmModal()" style="padding: 12px 24px; border: 1px solid #ddd; background: #fff; color: #333; border-radius: 6px; cursor: pointer; font-weight: 500; min-width: 100px;">İptal</button>
                    <button class="bkm-btn bkm-btn-primary" id="bkm-edit-submit-btn" style="padding: 12px 24px; background: #007cba; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; min-width: 150px;">Düzenlemeyi Kaydet</button>
                </div>
            </div>
        </div>
    `;
    
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(modal);
    
    // Focus on first input
    setTimeout(function() {
        document.getElementById('edit-task-title').focus();
    }, 100);
    
    // Submit handler
    document.getElementById('bkm-edit-submit-btn').addEventListener('click', function() {
        var editReason = document.getElementById('edit-reason').value.trim();
        var errorDiv = document.getElementById('edit-error');
        
        if (!editReason) {
            errorDiv.style.display = 'block';
            document.getElementById('edit-reason').focus();
            return;
        }
        
        errorDiv.style.display = 'none';
        
        var formData = {
            action: 'bkm_edit_task',
            task_id: taskId,
            title: document.getElementById('edit-task-title').value.trim(),
            content: document.getElementById('edit-task-content').value.trim(),
            progress: parseInt(document.getElementById('edit-task-progress').value) || 0,
            responsible_id: parseInt(document.getElementById('edit-task-responsible').value) || 0,
            start_date: document.getElementById('edit-task-start-date').value,
            target_date: document.getElementById('edit-task-target-date').value,
            edit_reason: editReason,
            nonce: '<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>'
        };
        
        closeBkmModal();
        
        // Submit edit
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response && response.success) {
                    showInPageNotification('✅ Görev başarıyla güncellendi!', 'success');
                    // Reload tasks
                    var actionId = getCurrentActionId();
                    if (actionId) {
                        loadTasksForAction(actionId);
                    }
                } else {
                    var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
                    showInPageNotification('❌ Hata: ' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                showInPageNotification('❌ Bağlantı hatası: ' + error, 'error');
            }
        });
    });
    
    // Close on backdrop click
    modal.querySelector('.bkm-modal-backdrop').addEventListener('click', function(e) {
        if (e.target === e.currentTarget) {
            closeBkmModal();
        }
    });
}

function newEditTask(taskId) {
    console.log('🚀 NEW: Edit task called for ID:', taskId);
    showEditTaskModal(taskId);
}

function newShowTaskHistory(taskId) {
    console.log('🚀 NEW: Show task history for ID:', taskId);
    
    // Simple AJAX call to get task history
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'bkm_get_task_history',
            task_id: taskId,
            nonce: '<?php echo wp_create_nonce('bkm_frontend_nonce'); ?>'
        },
        success: function(response) {
            console.log('✅ NEW: History response:', response);
            if (response && response.success && response.data) {
                var history = response.data;
                showTaskHistoryModal(taskId, history);
            } else {
                var errorMsg = response && response.data ? response.data : 'Bilinmeyen hata';
                showInPageNotification('❌ Görev geçmişi alınamadı: ' + errorMsg, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ NEW: History AJAX error:', error, xhr.responseText);
            showInPageNotification('❌ Bağlantı hatası: ' + error, 'error');
        }
    });
}

function showTaskHistoryModal(taskId, history) {
    // Remove existing modals
    var existingModals = document.querySelectorAll('.bkm-modal');
    existingModals.forEach(function(modal) { modal.remove(); });
    
    var historyHtml = '';
    
    if (history.length === 0) {
        historyHtml = '<p style="text-align: center; color: #666; padding: 20px;">Bu görev için henüz geçmiş kaydı bulunmamaktadır.</p>';
    } else {
        historyHtml = '<div style="max-height: 400px; overflow-y: auto;">';
        history.forEach(function(entry) {
            historyHtml += '<div style="border-left: 3px solid #007cba; padding: 15px; margin: 15px 0; background: #f8f9fa; border-radius: 4px;">';
            historyHtml += '<div style="font-weight: 600; color: #333; margin-bottom: 8px;">' + (entry.action_type || 'Düzenleme') + '</div>';
            historyHtml += '<div style="color: #666; font-size: 14px; margin-bottom: 5px;">👤 <strong>Kullanıcı:</strong> ' + (entry.editor_name || 'Bilinmiyor') + '</div>';
            historyHtml += '<div style="color: #666; font-size: 14px; margin-bottom: 8px;">📅 <strong>Tarih:</strong> ' + (entry.created_date || entry.created_at || 'Bilinmiyor') + '</div>';
            
            if (entry.edit_reason) {
                historyHtml += '<div style="color: #333; margin-bottom: 8px;"><strong>Sebep:</strong> ' + entry.edit_reason + '</div>';
            }
            
            if (entry.field_changes) {
                historyHtml += '<div style="color: #333; margin-bottom: 8px;"><strong>Değişen Alanlar:</strong> ' + entry.field_changes + '</div>';
            }
            
            // Enhanced old and new values display
            if (entry.old_values || entry.new_values) {
                var oldVals = entry.old_values || {};
                var newVals = entry.new_values || {};
                
                historyHtml += '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 8px 0;">';
                historyHtml += '<strong>Değişiklik Detayları:</strong><br>';
                
                // Display changes for each field
                Object.keys(newVals).forEach(function(field) {
                    var oldVal = oldVals[field] || 'Yok';
                    var newVal = newVals[field] || 'Yok';
                    if (oldVal !== newVal) {
                        var fieldName = field === 'content' ? 'İçerik' : 
                                       field === 'title' ? 'Başlık' :
                                       field === 'ilerleme_durumu' ? 'İlerleme' :
                                       field === 'hedef_bitis_tarihi' ? 'Hedef Tarihi' :
                                       field === 'baslangic_tarihi' ? 'Başlangıç Tarihi' :
                                       field === 'sorumlu_id' ? 'Sorumlu' : field;
                        
                        historyHtml += '<div style="margin: 5px 0;">';
                        historyHtml += '<strong>' + fieldName + ':</strong> ';
                        historyHtml += '<span style="color: #d32f2f; text-decoration: line-through;">' + oldVal + '</span> → ';
                        historyHtml += '<span style="color: #2e7d32; font-weight: bold;">' + newVal + '</span>';
                        historyHtml += '</div>';
                    }
                });
                historyHtml += '</div>';
            }
            
            if (entry.description && entry.description !== entry.edit_reason) {
                historyHtml += '<div style="color: #666; font-style: italic; margin-top: 8px;">' + entry.description + '</div>';
            }
            
            historyHtml += '</div>';
        });
        historyHtml += '</div>';
    }
    
    var modal = document.createElement('div');
    modal.className = 'bkm-modal';
    modal.innerHTML = `
        <div class="bkm-modal-backdrop">
            <div class="bkm-modal-content" style="max-width: 1400px; width: 95%;">
                <div class="bkm-modal-header">
                    <h3>📋 Görev Geçmişi (ID: ${taskId})</h3>
                    <button class="bkm-modal-close" onclick="closeBkmModal()">&times;</button>
                </div>
                <div class="bkm-modal-body">
                    ${historyHtml}
                </div>
                <div class="bkm-modal-footer">
                    <button class="bkm-btn bkm-btn-secondary" onclick="closeBkmModal()">Kapat</button>
                </div>
            </div>
        </div>
    `;
    
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    document.body.appendChild(modal);
    
    // Close on backdrop click
    modal.querySelector('.bkm-modal-backdrop').addEventListener('click', function(e) {
        if (e.target === e.currentTarget) {
            closeBkmModal();
        }
    });
}

// Helper function to get current action ID from URL or context
function getCurrentActionId() {
    // Try to get action ID from the last loadTasksForAction call or from global context
    if (typeof lastLoadedActionId !== 'undefined') {
        return lastLoadedActionId;
    }
    // Could also try to extract from URL or other context
    return null;
}

// Store the last loaded action ID for reference
var lastLoadedActionId = null;

function closeTaskHistoryModal(event) {
    if (event && event.target !== event.currentTarget) return;
    var modal = document.getElementById('task-history-modal');
    if (modal) {
        modal.remove();
    }
}

function approveTask(taskId) {
    console.log('🔄 approveTask called with ID:', taskId);
    
    if (!confirm('Bu görevi kabul etmek istediğinizden emin misiniz?')) {
        return;
    }
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        alert('❌ Sistem hatası: JavaScript bağlantısı başarısız. Sayfayı yenileyin.');
        return;
    }
    
    console.log('📡 Sending approve request...', {
        url: bkmFrontend.ajax_url,
        taskId: taskId,
        nonce: bkmFrontend.nonce
    });
    
    jQuery.post(bkmFrontend.ajax_url, {
        action: 'bkm_approve_task',
        task_id: taskId,
        nonce: bkmFrontend.nonce
    }, function(response) {
        console.log('📬 Approve response:', response);
        if (response && response.success) {
            alert('✅ Görev başarıyla kabul edildi!');
            // Refresh tasks for the current action
            location.reload();
        } else {
            var errorMsg = response && response.data ? response.data : 'Görev kabul edilemedi';
            alert('❌ Hata: ' + errorMsg);
            console.error('Approval failed:', response);
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Approval Error:', {xhr: xhr, status: status, error: error});
        console.error('Response text:', xhr.responseText);
        alert('❌ Ağ hatası: Görev onaylanamadı. Lütfen tekrar deneyin. (' + status + ')');
    });
}

function rejectTask(taskId) {
    console.log('🔄 rejectTask called with ID:', taskId);
    
    var reason = prompt('Lütfen red sebebinizi belirtiniz:');
    if (!reason || reason.trim() === '') {
        alert('Red sebebi girmeniz zorunludur.');
        return;
    }
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        alert('❌ Sistem hatası: JavaScript bağlantısı başarısız. Sayfayı yenileyin.');
        return;
    }
    
    console.log('📡 Sending reject request...', {
        url: bkmFrontend.ajax_url,
        taskId: taskId,
        reason: reason.trim(),
        nonce: bkmFrontend.nonce
    });
    
    jQuery.post(bkmFrontend.ajax_url, {
        action: 'bkm_reject_task',
        task_id: taskId,
        rejection_reason: reason.trim(),
        nonce: bkmFrontend.nonce
    }, function(response) {
        console.log('📬 Reject response:', response);
        if (response && response.success) {
            alert('❌ Görev başarıyla reddedildi.');
            // Refresh tasks for the current action
            location.reload();
        } else {
            var errorMsg = response && response.data ? response.data : 'Görev reddedilemedi';
            alert('❌ Hata: ' + errorMsg);
            console.error('Rejection failed:', response);
        }
    }).fail(function(xhr, status, error) {
        console.error('AJAX Rejection Error:', {xhr: xhr, status: status, error: error});
        console.error('Response text:', xhr.responseText);
        alert('❌ Ağ hatası: Görev reddedilemedi. Lütfen tekrar deneyin. (' + status + ')');
    });
}

function toggleReportsDropdown(e) {
    e.stopPropagation();
    var dropdown = document.getElementById('bkm-reports-dropdown');
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
        setTimeout(function() {
            dropdown.scrollIntoView({behavior: 'smooth'});
        }, 100);
    } else {
        dropdown.style.display = 'none';
    }
}

// Sayfa yüklendiğinde firma bilgilerini yükle
document.addEventListener('DOMContentLoaded', function() {
    if (typeof loadCompanyInfo === 'function') {
        loadCompanyInfo();
    }
    
    // User cache'ini global olarak ayarla
    window.usersCache = <?php echo json_encode($users_for_js); ?>;
    console.log('👥 Users cache ayarlandı:', window.usersCache);
});
</script>