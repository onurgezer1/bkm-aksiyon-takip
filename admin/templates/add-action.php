<?php
$edit_mode = isset($_GET['edit']) && !empty($_GET['edit']);
$aksiyon = null;

if($edit_mode) {
    global $wpdb;
    $aksiyon_id = intval($_GET['edit']);
    $aksiyon = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}bkm_aksiyonlar WHERE id = %d", 
        $aksiyon_id
    ));
}
?>

<div class="wrap">
    <h1><?php echo $edit_mode ? 'Aksiyon Düzenle' : 'Yeni Aksiyon Ekle'; ?></h1>

    <form method="post" id="bkm-aksiyon-form" class="bkm-form">
        <?php wp_nonce_field('bkm_add_action', 'bkm_nonce'); ?>
        
        <?php if($edit_mode): ?>
            <input type="hidden" name="aksiyon_id" value="<?php echo $aksiyon->id; ?>">
        <?php endif; ?>

        <div class="bkm-form-grid">
            <!-- Sol Kolon -->
            <div class="bkm-form-column">
                <div class="bkm-form-section">
                    <h3>Temel Bilgiler</h3>
                    
                    <div class="bkm-form-row">
                        <label for="aksiyonu_tanimlayan">Aksiyonu Tanımlayan *</label>
                        <select name="aksiyonu_tanimlayan" id="aksiyonu_tanimlayan" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>" 
                                    <?php echo ($aksiyon && $aksiyon->aksiyonu_tanimlayan == $user->ID) ? 'selected' : ''; ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bkm-form-row">
                        <label for="sira_no">Sıra No</label>
                        <input type="number" name="sira_no" id="sira_no" 
                               value="<?php echo $aksiyon ? $aksiyon->sira_no : $this->get_next_sira_no(); ?>" 
                               readonly>
                    </div>

                    <div class="bkm-form-row">
                        <label for="onem_derecesi">Önem Derecesi *</label>
                        <select name="onem_derecesi" id="onem_derecesi" required>
                            <option value="">Seçiniz...</option>
                            <option value="1" <?php echo ($aksiyon && $aksiyon->onem_derecesi == 1) ? 'selected' : ''; ?>>1 - Düşük</option>
                            <option value="2" <?php echo ($aksiyon && $aksiyon->onem_derecesi == 2) ? 'selected' : ''; ?>>2 - Orta</option>
                            <option value="3" <?php echo ($aksiyon && $aksiyon->onem_derecesi == 3) ? 'selected' : ''; ?>>3 - Yüksek</option>
                        </select>
                    </div>

                    <div class="bkm-form-row">
                        <label for="acilma_tarihi">Açılma Tarihi *</label>
                        <input type="date" name="acilma_tarihi" id="acilma_tarihi" 
                               value="<?php echo $aksiyon ? $aksiyon->acilma_tarihi : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="bkm-form-row">
                        <label for="hafta">Hafta</label>
                        <input type="number" name="hafta" id="hafta" min="1" max="53"
                               value="<?php echo $aksiyon ? $aksiyon->hafta : date('W'); ?>">
                    </div>
                </div>

                <div class="bkm-form-section">
                    <h3>Kategori ve Performans</h3>
                    
                    <div class="bkm-form-row">
                        <label for="kategori_id">Kategori</label>
                        <select name="kategori_id" id="kategori_id">
                            <option value="">Seçiniz...</option>
                            <?php foreach($kategoriler as $kategori): ?>
                            <option value="<?php echo $kategori->id; ?>"
                                    <?php echo ($aksiyon && $aksiyon->kategori_id == $kategori->id) ? 'selected' : ''; ?>>
                                <?php echo esc_html($kategori->kategori_adi); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bkm-form-row">
                        <label for="performans_id">Performans</label>
                        <select name="performans_id" id="performans_id">
                            <option value="">Seçiniz...</option>
                            <?php foreach($performanslar as $performans): ?>
                            <option value="<?php echo $performans->id; ?>"
                                    <?php echo ($aksiyon && $aksiyon->performans_id == $performans->id) ? 'selected' : ''; ?>>
                                <?php echo esc_html($performans->performans_adi); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon -->
            <div class="bkm-form-column">
                <div class="bkm-form-section">
                    <h3>Sorumlular ve Tarihler</h3>
                    
                    <div class="bkm-form-row">
                        <label for="aksiyon_sorumlusu">Aksiyon Sorumlusu</label>
                        <select name="aksiyon_sorumlusu[]" id="aksiyon_sorumlusu" multiple class="bkm-multiselect">
                            <?php 
                            $selected_sorumlular = $aksiyon ? explode(',', $aksiyon->aksiyon_sorumlusu) : array();
                            foreach($users as $user): 
                            ?>
                            <option value="<?php echo $user->ID; ?>"
                                    <?php echo in_array($user->ID, $selected_sorumlular) ? 'selected' : ''; ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bkm-form-row">
                        <label for="hedef_tarih">Hedef Tarih</label>
                        <input type="date" name="hedef_tarih" id="hedef_tarih" 
                               value="<?php echo $aksiyon ? $aksiyon->hedef_tarih : ''; ?>">
                    </div>

                    <div class="bkm-form-row">
                        <label for="kapanma_tarihi">Kapanma Tarihi</label>
                        <input type="date" name="kapanma_tarihi" id="kapanma_tarihi" 
                               value="<?php echo $aksiyon ? $aksiyon->kapanma_tarihi : ''; ?>">
                    </div>

                    <div class="bkm-form-row">
                        <label for="ilerleme_durumu">İlerleme Durumu (%)</label>
                        <div class="progress-input-container">
                            <input type="range" name="ilerleme_durumu" id="ilerleme_durumu" 
                                   min="0" max="100" value="<?php echo $aksiyon ? $aksiyon->ilerleme_durumu : 0; ?>"
                                   class="bkm-range-input">
                            <span class="progress-value"><?php echo $aksiyon ? $aksiyon->ilerleme_durumu : 0; ?>%</span>
                        </div>
                        <div class="progress-bar-preview">
                            <div class="progress-fill" style="width: <?php echo $aksiyon ? $aksiyon->ilerleme_durumu : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Açıklamalar Bölümü -->
        <div class="bkm-form-section bkm-full-width">
            <h3>Açıklamalar</h3>
            
            <div class="bkm-form-row">
                <label for="tespit_nedeni">Tespit Nedeni</label>
                <textarea name="tespit_nedeni" id="tespit_nedeni" rows="3"><?php echo $aksiyon ? esc_textarea($aksiyon->tespit_nedeni) : ''; ?></textarea>
            </div>

            <div class="bkm-form-row">
                <label for="aksiyon_aciklamasi">Aksiyon Açıklaması *</label>
                <textarea name="aksiyon_aciklamasi" id="aksiyon_aciklamasi" rows="5" required><?php echo $aksiyon ? esc_textarea($aksiyon->aksiyon_aciklamasi) : ''; ?></textarea>
            </div>

            <div class="bkm-form-row">
                <label for="notlar">Notlar</label>
                <textarea name="notlar" id="notlar" rows="5"><?php echo $aksiyon ? esc_textarea($aksiyon->notlar) : ''; ?></textarea>
            </div>
        </div>

        <div class="bkm-form-actions">
            <button type="submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $edit_mode ? 'Güncelle' : 'Kaydet'; ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=bkm-aksiyon'); ?>" class="button button-large">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                Geri Dön
            </a>
        </div>
    </form>
</div>

<script>
// İlerleme durumu slider'ı için real-time güncelleme
document.getElementById('ilerleme_durumu').addEventListener('input', function() {
    const value = this.value;
    document.querySelector('.progress-value').textContent = value + '%';
    document.querySelector('.progress-fill').style.width = value + '%';
    
    // Kapanma tarihi otomatik doldurma
    if(value == 100 && !document.getElementById('kapanma_tarihi').value) {
        document.getElementById('kapanma_tarihi').value = new Date().toISOString().split('T')[0];
    }
});
</script>