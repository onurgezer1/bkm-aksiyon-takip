<?php
global $wpdb;

// Performans işlemleri
if ($_POST) {
    if (wp_verify_nonce($_POST['bkm_nonce'], 'bkm_performance_action')) {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $performans_adi = sanitize_text_field($_POST['performans_adi']);
            $aciklama = sanitize_textarea_field($_POST['aciklama']);
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'bkm_performanslar',
                array(
                    'performans_adi' => $performans_adi,
                    'aciklama' => $aciklama
                ),
                array('%s', '%s')
            );
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Performans verisi başarıyla eklendi.</p></div>';
            }
        }
        
        if ($action === 'edit') {
            $id = intval($_POST['performans_id']);
            $performans_adi = sanitize_text_field($_POST['performans_adi']);
            $aciklama = sanitize_textarea_field($_POST['aciklama']);
            
            $wpdb->update(
                $wpdb->prefix . 'bkm_performanslar',
                array(
                    'performans_adi' => $performans_adi,
                    'aciklama' => $aciklama
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            
            echo '<div class="notice notice-success"><p>Performans verisi başarıyla güncellendi.</p></div>';
        }
    }
}

$performanslar = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bkm_performanslar ORDER BY performans_adi");
?>

<div class="wrap">
    <h1>Performans Yönetimi</h1>

    <div class="bkm-admin-grid">
        <!-- Performans Ekleme Formu -->
        <div class="bkm-admin-card">
            <h2>Yeni Performans Verisi Ekle</h2>
            <form method="post" id="bkm-performans-form">
                <?php wp_nonce_field('bkm_performance_action', 'bkm_nonce'); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="bkm-form-row">
                    <label for="performans_adi">Performans Adı *</label>
                    <input type="text" name="performans_adi" id="performans_adi" required>
                </div>
                
                <div class="bkm-form-row">
                    <label for="aciklama">Açıklama</label>
                    <textarea name="aciklama" id="aciklama" rows="3"></textarea>
                </div>
                
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-plus"></span> Performans Verisi Ekle
                </button>
            </form>
        </div>

        <!-- Performans Listesi -->
        <div class="bkm-admin-card">
            <h2>Mevcut Performans Verileri</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Performans Adı</th>
                        <th>Açıklama</th>
                        <th>Kullanım</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($performanslar as $performans): ?>
                    <?php
                    // Performans kullanım sayısını hesapla
                    $kullanim_sayisi = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar WHERE performans_id = %d",
                        $performans->id
                    ));
                    ?>
                    <tr>
                        <td><?php echo $performans->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($performans->performans_adi); ?></strong>
                        </td>
                        <td><?php echo esc_html($performans->aciklama); ?></td>
                        <td>
                            <span class="usage-count"><?php echo $kullanim_sayisi; ?> aksiyon</span>
                        </td>
                        <td>
                            <button class="button button-small bkm-edit-performans" 
                                    data-id="<?php echo $performans->id; ?>"
                                    data-name="<?php echo esc_attr($performans->performans_adi); ?>"
                                    data-desc="<?php echo esc_attr($performans->aciklama); ?>">
                                <span class="dashicons dashicons-edit"></span> Düzenle
                            </button>
                            
                            <?php if($kullanim_sayisi == 0): ?>
                            <button class="button button-small button-link-delete bkm-delete-performans" 
                                    data-id="<?php echo $performans->id; ?>">
                                <span class="dashicons dashicons-trash"></span> Sil
                            </button>
                            <?php else: ?>
                            <span class="button button-small" disabled title="Bu performans verisi kullanımda olduğu için silinemez">
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
<div id="bkm-edit-performans-modal" class="bkm-modal" style="display: none;">
    <div class="bkm-modal-content">
        <span class="bkm-modal-close">&times;</span>
        <h2>Performans Verisi Düzenle</h2>
        <form method="post" id="bkm-edit-performans-form">
            <?php wp_nonce_field('bkm_performance_action', 'bkm_nonce'); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="performans_id" id="edit_performans_id">
            
            <div class="bkm-form-row">
                <label for="edit_performans_adi">Performans Adı *</label>
                <input type="text" name="performans_adi" id="edit_performans_adi" required>
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