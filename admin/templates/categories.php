<?php
global $wpdb;

// Kategori işlemleri
if ($_POST) {
    if (wp_verify_nonce($_POST['bkm_nonce'], 'bkm_category_action')) {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $kategori_adi = sanitize_text_field($_POST['kategori_adi']);
            $aciklama = sanitize_textarea_field($_POST['aciklama']);
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'bkm_kategoriler',
                array(
                    'kategori_adi' => $kategori_adi,
                    'aciklama' => $aciklama
                ),
                array('%s', '%s')
            );
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Kategori başarıyla eklendi.</p></div>';
            }
        }
        
        if ($action === 'edit') {
            $id = intval($_POST['kategori_id']);
            $kategori_adi = sanitize_text_field($_POST['kategori_adi']);
            $aciklama = sanitize_textarea_field($_POST['aciklama']);
            
            $wpdb->update(
                $wpdb->prefix . 'bkm_kategoriler',
                array(
                    'kategori_adi' => $kategori_adi,
                    'aciklama' => $aciklama
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            
            echo '<div class="notice notice-success"><p>Kategori başarıyla güncellendi.</p></div>';
        }
    }
}

// Silme işlemi AJAX ile yapılacak
$kategoriler = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bkm_kategoriler ORDER BY kategori_adi");
?>

<div class="wrap">
    <h1>Kategori Yönetimi</h1>

    <div class="bkm-admin-grid">
        <!-- Kategori Ekleme Formu -->
        <div class="bkm-admin-card">
            <h2>Yeni Kategori Ekle</h2>
            <form method="post" id="bkm-kategori-form">
                <?php wp_nonce_field('bkm_category_action', 'bkm_nonce'); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="bkm-form-row">
                    <label for="kategori_adi">Kategori Adı *</label>
                    <input type="text" name="kategori_adi" id="kategori_adi" required>
                </div>
                
                <div class="bkm-form-row">
                    <label for="aciklama">Açıklama</label>
                    <textarea name="aciklama" id="aciklama" rows="3"></textarea>
                </div>
                
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span> Kategori Ekle
                </button>
            </form>
        </div>

        <!-- Kategori Listesi -->
        <div class="bkm-admin-card">
            <h2>Mevcut Kategoriler</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kategori Adı</th>
                        <th>Açıklama</th>
                        <th>Kullanım</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($kategoriler as $kategori): ?>
                    <?php
                    // Kategori kullanım sayısını hesapla
                    $kullanim_sayisi = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar WHERE kategori_id = %d",
                        $kategori->id
                    ));
                    ?>
                    <tr>
                        <td><?php echo $kategori->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($kategori->kategori_adi); ?></strong>
                        </td>
                        <td><?php echo esc_html($kategori->aciklama); ?></td>
                        <td>
                            <span class="usage-count"><?php echo $kullanim_sayisi; ?> aksiyon</span>
                        </td>
                        <td>
                            <button class="button button-small bkm-edit-kategori" 
                                    data-id="<?php echo $kategori->id; ?>"
                                    data-name="<?php echo esc_attr($kategori->kategori_adi); ?>"
                                    data-desc="<?php echo esc_attr($kategori->aciklama); ?>">
                                <span class="dashicons dashicons-edit"></span> Düzenle
                            </button>
                            
                            <?php if($kullanim_sayisi == 0): ?>
                            <button class="button button-small button-link-delete bkm-delete-kategori" 
                                    data-id="<?php echo $kategori->id; ?>">
                                <span class="dashicons dashicons-trash"></span> Sil
                            </button>
                            <?php else: ?>
                            <span class="button button-small" disabled title="Bu kategori kullanımda olduğu için silinemez">
                                <span class="dashicons dashicons-lock"></span> Korumalı
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Düzenleme Modal -->
<div id="bkm-edit-kategori-modal" class="bkm-modal" style="display: none;">
    <div class="bkm-modal-content">
        <span class="bkm-modal-close">&times;</span>
        <h2>Kategori Düzenle</h2>
        <form method="post" id="bkm-edit-kategori-form">
            <?php wp_nonce_field('bkm_category_action', 'bkm_nonce'); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="kategori_id" id="edit_kategori_id">
            
            <div class="bkm-form-row">
                <label for="edit_kategori_adi">Kategori Adı *</label>
                <input type="text" name="kategori_adi" id="edit_kategori_adi" required>
            </div>
            
            <div class="bkm-form-row">
                <label for="edit_aciklama">Açıklama</label>
                <textarea name="aciklama" id="edit_aciklama" rows="3"></textarea>
            </div>
            
            <div class="bkm-modal-actions">
                <button type="submit" class="button button-primary">Güncelle</button>
                <button type="button" class="button bkm-modal-close">İptal</button>
            </div>
        </form>
    </div>
</div>