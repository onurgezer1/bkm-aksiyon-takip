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
$current_user = wp_get_current_user();

// Check if user has permission to view
if (!current_user_can('read')) {
    echo '<div class="bkm-error">Bu sayfaya erişim yetkiniz bulunmamaktadır.</div>';
    return;
}

// Get data
$actions_table = $wpdb->prefix . 'bkm_actions';
$tasks_table = $wpdb->prefix . 'bkm_tasks';
$notes_table = $wpdb->prefix . 'bkm_task_notes';
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performances';

// Determine SQL query based on user role
$user_roles = $current_user->roles;
$is_admin = in_array('administrator', $user_roles);
$is_editor = in_array('editor', $user_roles);
$is_contributor = in_array('contributor', $user_roles);
$current_user_id = $current_user->ID;

error_log("Is admin (global): " . ($is_admin ? 'true' : 'false') . ", User roles: " . implode(', ', $user_roles) . ", User ID: " . $current_user_id);

if ($is_admin || $is_editor) {
    // Admins and editors see all actions
    $actions_query = "SELECT a.*, 
                            CASE 
                                WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                                THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                                WHEN u.display_name IS NOT NULL AND u.display_name != ''
                                THEN u.display_name
                                ELSE COALESCE(u.user_login, 'Bilinmiyor')
                            END as tanımlayan_name,
                            c.name as kategori_name,
                            p.name as performans_name
                     FROM $actions_table a
                     LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
                     LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                     LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
                     LEFT JOIN $categories_table c ON a.kategori_id = c.id
                     LEFT JOIN $performance_table p ON a.performans_id = p.id
                     ORDER BY a.created_at DESC";
} else {
    // Non-admins and non-editors see only their assigned actions
    $actions_query = $wpdb->prepare(
        "SELECT a.*, 
                CASE 
                    WHEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) != ''
                    THEN TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value))
                    WHEN u.display_name IS NOT NULL AND u.display_name != ''
                    THEN u.display_name
                    ELSE COALESCE(u.user_login, 'Bilinmiyor')
                END as tanımlayan_name,
                c.name as kategori_name,
                p.name as performans_name
         FROM $actions_table a
         LEFT JOIN {$wpdb->users} u ON a.tanımlayan_id = u.ID AND a.tanımlayan_id > 0
         LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
         LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
         LEFT JOIN $categories_table c ON a.kategori_id = c.id
         LEFT JOIN $performance_table p ON a.performans_id = p.id
         WHERE a.sorumlu_ids LIKE %s
         ORDER BY a.created_at DESC",
        '%' . $wpdb->esc_like($current_user_id) . '%'
    );
}

$actions = $wpdb->get_results($actions_query);

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
if (isset($_POST['task_action']) && (wp_verify_nonce($_POST['bkm_frontend_nonce_' . $_POST['task_id']] ?? '', 'bkm_frontend_action') || wp_verify_nonce($_POST['bkm_frontend_nonce_reject_' . $_POST['task_id']] ?? '', 'bkm_frontend_action'))) {
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
                    'status' => 'completed',
                    'gercek_bitis_tarihi' => current_time('mysql')
                ),
                array('id' => $task_id),
                array('%d', '%d', '%s', '%s'),
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
            $plugin->update_action_progress_from_tasks($task_id);
            
            // Redirect to prevent form resubmission
            global $wp;
            wp_safe_redirect(home_url(add_query_arg(array('success' => 'task_completed'), $wp->request)));
            exit;
        }
    } elseif ($_POST['task_action'] === 'reject_task') {
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
                    'status' => 'rejected',
                    'ilerleme_durumu' => 0
                ),
                array('id' => $task_id),
                array('%s', '%d'),
                array('%d')
            );
            
            // Send email notification
            $plugin = BKM_Aksiyon_Takip::get_instance();
            $notification_data = array(
                'content' => $task->content,
                'sorumlu' => $current_user->display_name,
                'task_id' => $task_id,
                'action_id' => $task->action_id
            );
            
            $plugin->send_email_notification('task_rejected', $notification_data);
            
            // Update action progress based on tasks average
            $plugin->update_action_progress_from_tasks($task_id);
            
            // Redirect to prevent form resubmission
            global $wp;
            wp_safe_redirect(home_url(add_query_arg(array('success' => 'task_rejected'), $wp->request)));
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
    } elseif ($_GET['success'] === 'task_rejected') {
        echo '<div class="bkm-success">Görev başarıyla reddedildi!</div>';
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
}
.bkm-table { 
    width: 100% !important; 
    background: #fff !important; 
    border-collapse: collapse !important; 
    border-radius: 8px !important; 
    overflow: hidden !important; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important; 
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
.bkm-btn-danger { 
    background: #dc3545 !important; 
    color: #fff !important; 
}
.bkm-btn-danger:hover {
    background: #c82333 !important;
}
.bkm-btn-warning { 
    background: #ffc107 !important; 
    color: #212529 !important; 
}
.bkm-btn-secondary {
    background: #6c757d !important;
    color: #fff !important;
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
    50% { background-color: #28a745; box-shadow: 0 0 10px rgba(40, 167, 69, 0.5); }
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
.bkm-task-item.rejected {
    background: #fff5f5 !important;
    border-left: 4px solid #dc3545 !important;
    opacity: 0.8 !important;
}
.bkm-task-item.rejected .bkm-task-content p {
    color: #721c24 !important;
    font-style: italic !important;
}
.bkm-task-item.rejected .bkm-progress-bar {
    background: #dc3545 !important;
}
.bkm-task-item.rejected::before {
    content: "❌ REDDEDILDI";
    position: absolute;
    top: 10px;
    right: 10px;
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
}
.bkm-task-item {
    position: relative;
}
.action-completed {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
}
.action-completed .bkm-progress-bar {
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%) !important;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3) !important;
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
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
}

.bkm-user-chip:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
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
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    transition: all 0.2s ease !important;
    min-width: 110px !important;
    justify-content: center !important;
}

.bkm-status-elegant:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
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
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1) !important;
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
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
                            <div id="bkm-reports-dropdown" class="bkm-reports-dropdown" style="display:none; position:static; width:100%; max-width:1200px; margin:40px auto; background:#fff; border-radius:10px; box-shadow:0 4px 24px rgba(0,0,0,0.12); z-index:1000; padding:32px;">
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
                                                                        WHEN u.display_name IS NOT NULL AND u.display_name != ''
                                                                        THEN u.display_name
                                                                        ELSE COALESCE(u.user_login, 'Bilinmiyor')
                                                                    END as user_name 
                                                             FROM $notes_table n 
                                                             LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID AND n.user_id > 0
                                                             LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = 'first_name'
                                                             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = 'last_name'
                                                             WHERE n.task_id = %d 
                                                             ORDER BY n.created_at ASC",
                                                            $task->id
                                                        ));
                                                        $has_notes = !empty($task_notes);
                                                        ?>
                                                        <?php
                                                        // Determine task status class and whether task is actionable
                                                        $task_status_class = '';
                                                        $is_task_actionable = true;
                                                        
                                                        if ($task->tamamlandi || $task->status === 'completed') {
                                                            $task_status_class = 'completed';
                                                            $is_task_actionable = false;
                                                        } elseif ($task->status === 'rejected') {
                                                            $task_status_class = 'rejected';
                                                            $is_task_actionable = false;
                                                        }
                                                        ?>
                                                        <div class="bkm-task-item <?php echo $task_status_class; ?>" data-task-id="<?php echo $task->id; ?>" data-task-status="<?php echo esc_attr($task->status); ?>">
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
                                                                <?php if ($task->sorumlu_id == $current_user->ID && $is_task_actionable): ?>
                                                                    <form method="post" style="display: inline;">
                                                                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce_' . $task->id); ?>
                                                                        <input type="hidden" name="task_action" value="complete_task" />
                                                                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                        <button type="submit" class="bkm-btn bkm-btn-success bkm-btn-small"
                                                                                onclick="return confirm('Bu görevi tamamladınız mı?')">
                                                                            ✅ Tamamla
                                                                        </button>
                                                                    </form>
                                                                    
                                                                    <form method="post" style="display: inline; margin-left: 5px;">
                                                                        <?php wp_nonce_field('bkm_frontend_action', 'bkm_frontend_nonce_reject_' . $task->id); ?>
                                                                        <input type="hidden" name="task_action" value="reject_task" />
                                                                        <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                        <button type="submit" class="bkm-btn bkm-btn-danger bkm-btn-small"
                                                                                onclick="return confirm('Bu görevi reddetmek istediğinizden emin misiniz?')">
                                                                            ❌ Reddet
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (($task->sorumlu_id == $current_user->ID && $is_task_actionable) || $is_admin || $is_editor): ?>
                                                                    <button class="bkm-btn bkm-btn-small" onclick="toggleNoteForm(<?php echo $task->id; ?>)">
                                                                        📝 Not Ekle
                                                                    </button>
                                                                    <?php if ($has_notes): ?>
                                                                        <button class="bkm-btn bkm-btn-small" onclick="toggleNotes(<?php echo $task->id; ?>)">
                                                                            💬 Notları Göster (<?php echo count($task_notes); ?>)
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Note Form (hidden by default) -->
                                                        <?php if (($task->sorumlu_id == $current_user->ID && $is_task_actionable) || $is_admin || $is_editor): ?>
                                                            <div id="note-form-<?php echo $task->id; ?>" class="bkm-note-form" style="display: none;">
                                                                <form>
                                                                    <input type="hidden" name="task_id" value="<?php echo $task->id; ?>" />
                                                                    <div class="bkm-note-form-row">
                                                                        <div class="bkm-note-textarea">
                                                                            <label for="note_content_<?php echo $task->id; ?>">Not İçeriği:</label>
                                                                            <textarea name="note_content" id="note_content_<?php echo $task->id; ?>" rows="3" placeholder="Notunuzu buraya yazın..." required></textarea>
                                                                        </div>
                                                                        <?php if ($is_task_actionable): ?>
                                                                        <div class="bkm-note-progress">
                                                                            <label for="note_progress_<?php echo $task->id; ?>">İlerleme Durumu (%):</label>
                                                                            <input type="number" name="note_progress" id="note_progress_<?php echo $task->id; ?>" 
                                                                                   min="0" max="100" value="<?php echo $task->ilerleme_durumu; ?>" 
                                                                                   placeholder="0-100" />
                                                                            <small>Mevcut: <?php echo $task->ilerleme_durumu; ?>%</small>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="bkm-form-actions">
                                                                        <button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">
                                                                            <?php echo $is_task_actionable ? 'Not Ekle ve İlerlemeyi Güncelle' : 'Not Ekle'; ?>
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
        tasksRow.style.display = 'table-row';
    } else {
        tasksRow.style.display = 'none';
    }
}

// Görev notları fonksiyonları frontend.js'te tanımlandı - çakışmayı önlemek için buradakiler kaldırıldı

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
});
</script>