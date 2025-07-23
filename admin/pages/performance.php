<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('edit_posts')) {
    wp_die(__('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'bkm-aksiyon-takip'));
}

global $wpdb;

$performance_table = $wpdb->prefix . 'bkm_performances';
$actions_table = $wpdb->prefix . 'bkm_actions';

// Check if performance table exists, if not create it
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $performance_table));
if ($table_exists != $performance_table) {
    // Ana plugin instance'ını al ve tablo oluşturma metodunu çağır
    $plugin_instance = BKM_Aksiyon_Takip::get_instance();
    if ($plugin_instance) {
        $plugin_instance->check_and_create_tables();
        echo '<div class="notice notice-success"><p>Veritabanı tabloları kontrol edildi ve eksik olanlar oluşturuldu!</p></div>';
    }
}

// Handle form submissions
if (isset($_POST['action']) && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_performance')) {
    
    if ($_POST['action'] === 'add_performance') {
        $name = sanitize_text_field($_POST['performance_name']);
        $description = sanitize_textarea_field($_POST['performance_description']);
        
        if (!empty($name)) {
            $result = $wpdb->insert(
                $performance_table,
                array(
                    'name' => $name,
                    'description' => $description
                ),
                array('%s', '%s')
            );
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Performans verisi başarıyla eklendi!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Performans verisi eklenirken bir hata oluştu.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Performans adı boş olamaz.</p></div>';
        }
    }
    
    elseif ($_POST['action'] === 'edit_performance') {
        $id = intval($_POST['performance_id']);
        $name = sanitize_text_field($_POST['performance_name']);
        $description = sanitize_textarea_field($_POST['performance_description']);
        
        if (!empty($name) && $id > 0) {
            $result = $wpdb->update(
                $performance_table,
                array(
                    'name' => $name,
                    'description' => $description
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Performans verisi başarıyla güncellendi!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Performans verisi güncellenirken bir hata oluştu.</p></div>';
            }
        }
    }
    
    elseif ($_POST['action'] === 'delete_performance') {
        $id = intval($_POST['performance_id']);
        
        if ($id > 0) {
            // Check if performance is used in any actions
            $usage_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $actions_table WHERE performans_id = %d", 
                $id
            ));
            
            if ($usage_count > 0) {
                echo '<div class="notice notice-error"><p>Bu performans verisi ' . $usage_count . ' adet aksiyonda kullanıldığı için silinemez.</p></div>';
            } else {
                $result = $wpdb->delete($performance_table, array('id' => $id), array('%d'));
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>Performans verisi başarıyla silindi!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Performans verisi silinirken bir hata oluştu.</p></div>';
                }
            }
        }
    }
}

// Get performance data with usage count
$performances = array();
$query_error = '';

try {
    $performances = $wpdb->get_results(
        "SELECT p.*, COUNT(a.id) as usage_count 
         FROM $performance_table p 
         LEFT JOIN $actions_table a ON p.id = a.performans_id 
         GROUP BY p.id 
         ORDER BY p.name"
    );
    
    if ($wpdb->last_error) {
        $query_error = $wpdb->last_error;
        $performances = array(); // Hata varsa boş array döndür
    }
} catch (Exception $e) {
    $query_error = $e->getMessage();
    $performances = array();
}

// Handle edit mode
$edit_performance = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_performance = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $performance_table WHERE id = %d", 
        $edit_id
    ));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!empty($query_error)): ?>
        <div class="notice notice-error">
            <p><strong>Veritabanı Hatası:</strong> <?php echo esc_html($query_error); ?></p>
            <p>Performans tablosu oluşturuluyor...</p>
        </div>
    <?php endif; ?>
    
    <div class="bkm-performance-page">
        <!-- Add/Edit Performance Form -->
        <div class="bkm-form-section">
            <h2><?php echo $edit_performance ? 'Performans Verisi Düzenle' : 'Yeni Performans Verisi Ekle'; ?></h2>
            
            <form method="post" action="" class="bkm-form">
                <?php wp_nonce_field('bkm_performance', 'bkm_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $edit_performance ? 'edit_performance' : 'add_performance'; ?>" />
                
                <?php if ($edit_performance): ?>
                    <input type="hidden" name="performance_id" value="<?php echo $edit_performance->id; ?>" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="performance_name">Performans Adı *</label>
                        </th>
                        <td>
                            <input type="text" name="performance_name" id="performance_name" 
                                   value="<?php echo $edit_performance ? esc_attr($edit_performance->name) : ''; ?>" 
                                   class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="performance_description">Açıklama</label>
                        </th>
                        <td>
                            <textarea name="performance_description" id="performance_description" 
                                      rows="3" cols="50"><?php echo $edit_performance ? esc_textarea($edit_performance->description) : ''; ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <div class="submit-section">
                    <?php submit_button($edit_performance ? 'Performans Güncelle' : 'Performans Ekle', 'primary'); ?>
                    
                    <?php if ($edit_performance): ?>
                        <a href="<?php echo admin_url('admin.php?page=bkm-performanslar'); ?>" class="button">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Performance List -->
        <div class="bkm-list-section">
            <h2>Performans Verileri</h2>
            
            <div class="bkm-performance-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Performans Adı</th>
                            <th>Açıklama</th>
                            <th style="width: 100px;">Kullanım</th>
                            <th style="width: 200px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($performances): ?>
                            <?php foreach ($performances as $performance): ?>
                                <tr>
                                    <td><?php echo $performance->id; ?></td>
                                    <td><strong><?php echo esc_html($performance->name); ?></strong></td>
                                    <td><?php echo esc_html($performance->description); ?></td>
                                    <td>
                                        <span class="bkm-usage-count">
                                            <?php echo $performance->usage_count; ?> aksiyon
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=bkm-performanslar&edit=' . $performance->id); ?>" 
                                           class="button button-small">
                                            Düzenle
                                        </a>
                                        
                                        <?php if ($performance->usage_count == 0): ?>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Bu performans verisini silmek istediğinizden emin misiniz?');">
                                                <?php wp_nonce_field('bkm_performance', 'bkm_nonce'); ?>
                                                <input type="hidden" name="action" value="delete_performance" />
                                                <input type="hidden" name="performance_id" value="<?php echo $performance->id; ?>" />
                                                <button type="submit" class="button button-small button-link-delete">
                                                    Sil
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="button button-small button-disabled" 
                                                  title="Bu performans verisi <?php echo $performance->usage_count; ?> adet aksiyonda kullanıldığı için silinemez.">
                                                Silinemez
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Henüz performans verisi bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>