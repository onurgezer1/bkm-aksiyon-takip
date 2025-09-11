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

$categories_table = $wpdb->prefix . 'bkm_categories';
$actions_table = $wpdb->prefix . 'bkm_actions';

// Handle form submissions
if (isset($_POST['action']) && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_categories')) {
    
    if ($_POST['action'] === 'add_category') {
        $name = sanitize_text_field($_POST['category_name']);
        $description = sanitize_textarea_field($_POST['category_description']);
        
        if (!empty($name)) {
            $result = $wpdb->insert(
                $categories_table,
                array(
                    'name' => $name,
                    'description' => $description
                ),
                array('%s', '%s')
            );
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Kategori başarıyla eklendi!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Kategori eklenirken bir hata oluştu.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Kategori adı boş olamaz.</p></div>';
        }
    }
    
    elseif ($_POST['action'] === 'edit_category') {
        $id = intval($_POST['category_id']);
        $name = sanitize_text_field($_POST['category_name']);
        $description = sanitize_textarea_field($_POST['category_description']);
        
        if (!empty($name) && $id > 0) {
            $result = $wpdb->update(
                $categories_table,
                array(
                    'name' => $name,
                    'description' => $description
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Kategori başarıyla güncellendi!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Kategori güncellenirken bir hata oluştu.</p></div>';
            }
        }
    }
    
    elseif ($_POST['action'] === 'delete_category') {
        $id = intval($_POST['category_id']);
        
        if ($id > 0) {
            // Check if category is used in any actions
            $usage_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $actions_table WHERE kategori_id = %d", 
                $id
            ));
            
            if ($usage_count > 0) {
                echo '<div class="notice notice-error"><p>Bu kategori ' . $usage_count . ' adet aksiyonda kullanıldığı için silinemez.</p></div>';
            } else {
                $result = $wpdb->delete($categories_table, array('id' => $id), array('%d'));
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>Kategori başarıyla silindi!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Kategori silinirken bir hata oluştu.</p></div>';
                }
            }
        }
    }
}

// Get categories with usage count
$categories = $wpdb->get_results(
    "SELECT c.*, COUNT(a.id) as usage_count 
     FROM $categories_table c 
     LEFT JOIN $actions_table a ON c.id = a.kategori_id 
     GROUP BY c.id 
     ORDER BY c.name"
);

// Handle edit mode
$edit_category = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $categories_table WHERE id = %d", 
        $edit_id
    ));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="bkm-categories-page">
        <!-- Add/Edit Category Form -->
        <div class="bkm-form-section">
            <h2><?php echo $edit_category ? 'Kategori Düzenle' : 'Yeni Kategori Ekle'; ?></h2>
            
            <form method="post" action="" class="bkm-form">
                <?php wp_nonce_field('bkm_categories', 'bkm_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit_category' : 'add_category'; ?>" />
                
                <?php if ($edit_category): ?>
                    <input type="hidden" name="category_id" value="<?php echo $edit_category->id; ?>" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="category_name">Kategori Adı *</label>
                        </th>
                        <td>
                            <input type="text" name="category_name" id="category_name" 
                                   value="<?php echo $edit_category ? esc_attr($edit_category->name) : ''; ?>" 
                                   class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="category_description">Açıklama</label>
                        </th>
                        <td>
                            <textarea name="category_description" id="category_description" 
                                      rows="3" cols="50"><?php echo $edit_category ? esc_textarea($edit_category->description) : ''; ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <div class="submit-section">
                    <?php submit_button($edit_category ? 'Kategori Güncelle' : 'Kategori Ekle', 'primary'); ?>
                    
                    <?php if ($edit_category): ?>
                        <a href="<?php echo admin_url('admin.php?page=bkm-kategoriler'); ?>" class="button">İptal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Categories List -->
        <div class="bkm-list-section">
            <h2>Kategoriler</h2>
            
            <div class="bkm-categories-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Kategori Adı</th>
                            <th>Açıklama</th>
                            <th style="width: 100px;">Kullanım</th>
                            <th style="width: 200px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories): ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category->id; ?></td>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td><?php echo esc_html($category->description); ?></td>
                                    <td>
                                        <span class="bkm-usage-count">
                                            <?php echo $category->usage_count; ?> aksiyon
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=bkm-kategoriler&edit=' . $category->id); ?>" 
                                           class="button button-small">
                                            Düzenle
                                        </a>
                                        
                                        <?php if ($category->usage_count == 0): ?>
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">
                                                <?php wp_nonce_field('bkm_categories', 'bkm_nonce'); ?>
                                                <input type="hidden" name="action" value="delete_category" />
                                                <input type="hidden" name="category_id" value="<?php echo $category->id; ?>" />
                                                <button type="submit" class="button button-small button-link-delete">
                                                    Sil
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="button button-small button-disabled" 
                                                  title="Bu kategori <?php echo $category->usage_count; ?> adet aksiyonda kullanıldığı için silinemez.">
                                                Silinemez
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Henüz kategori bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>