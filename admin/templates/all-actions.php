<div class="wrap">
    <h1>Tüm Aksiyonlar</h1>
    
    <div class="bkm-admin-header">
        <a href="<?php echo admin_url('admin.php?page=bkm-add-aksiyon'); ?>" class="button button-primary">
            <span class="dashicons dashicons-plus"></span> Yeni Aksiyon Ekle
        </a>
        
        <div class="bkm-filters">
            <select id="kategori-filter">
                <option value="">Tüm Kategoriler</option>
                <?php foreach($this->get_kategoriler() as $kategori): ?>
                <option value="<?php echo $kategori->id; ?>"><?php echo esc_html($kategori->kategori_adi); ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="durum-filter">
                <option value="">Tüm Durumlar</option>
                <option value="acik">Açık</option>
                <option value="kapali">Kapalı</option>
            </select>
            
            <input type="text" id="arama-input" placeholder="Aksiyon ara...">
        </div>
    </div>

    <div class="bkm-stats-cards">
        <div class="stat-card">
            <h3><?php echo count($aksiyonlar); ?></h3>
            <p>Toplam Aksiyon</p>
        </div>
        <div class="stat-card">
            <h3><?php echo count(array_filter($aksiyonlar, function($a) { return empty($a->kapanma_tarihi); })); ?></h3>
            <p>Açık Aksiyonlar</p>
        </div>
        <div class="stat-card">
            <h3><?php echo count(array_filter($aksiyonlar, function($a) { return !empty($a->kapanma_tarihi); })); ?></h3>
            <p>Tamamlanan</p>
        </div>
        <div class="stat-card">
            <h3><?php 
                $geciken = array_filter($aksiyonlar, function($a) { 
                    return empty($a->kapanma_tarihi) && $a->hedef_tarih < date('Y-m-d'); 
                });
                echo count($geciken);
            ?></h3>
            <p class="text-danger">Geciken</p>
        </div>
    </div>

    <div class="bkm-table-container">
        <table class="wp-list-table widefat fixed striped" id="bkm-aksiyonlar-table">
            <thead>
                <tr>
                    <th class="sortable" data-column="sira_no">Sıra No</th>
                    <th class="sortable" data-column="aksiyon_aciklamasi">Aksiyon</th>
                    <th class="sortable" data-column="tanimlayan_adi">Tanımlayan</th>
                    <th class="sortable" data-column="kategori_adi">Kategori</th>
                    <th class="sortable" data-column="onem_derecesi">Önem</th>
                    <th class="sortable" data-column="acilma_tarihi">Açılma</th>
                    <th class="sortable" data-column="hedef_tarih">Hedef</th>
                    <th class="sortable" data-column="ilerleme_durumu">İlerleme</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($aksiyonlar as $aksiyon): ?>
                <tr data-kategori="<?php echo $aksiyon->kategori_id; ?>" 
                    data-durum="<?php echo empty($aksiyon->kapanma_tarihi) ? 'acik' : 'kapali'; ?>">
                    <td><strong><?php echo $aksiyon->sira_no; ?></strong></td>
                    <td>
                        <div class="aksiyon-title"><?php echo wp_trim_words($aksiyon->aksiyon_aciklamasi, 8); ?></div>
                        <?php if($aksiyon->aksiyon_sorumlusu): ?>
                        <div class="sorumlu-list">
                            <?php 
                            $sorumlular = explode(',', $aksiyon->aksiyon_sorumlusu);
                            foreach($sorumlular as $sorumlu_id):
                                $user = get_user_by('id', trim($sorumlu_id));
                                if($user):
                            ?>
                            <span class="sorumlu-badge"><?php echo $user->display_name; ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($aksiyon->tanimlayan_adi); ?></td>
                    <td>
                        <span class="kategori-badge kategori-<?php echo $aksiyon->kategori_id; ?>">
                            <?php echo esc_html($aksiyon->kategori_adi); ?>
                        </span>
                    </td>
                    <td>
                        <span class="onem-badge onem-<?php echo $aksiyon->onem_derecesi; ?>">
                            <?php 
                            $onem_text = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                            echo $onem_text[$aksiyon->onem_derecesi]; 
                            ?>
                        </span>
                    </td>
                    <td><?php echo date('d.m.Y', strtotime($aksiyon->acilma_tarihi)); ?></td>
                    <td>
                        <?php if($aksiyon->hedef_tarih): ?>
                            <span class="<?php echo ($aksiyon->hedef_tarih < date('Y-m-d') && empty($aksiyon->kapanma_tarihi)) ? 'text-danger' : ''; ?>">
                                <?php echo date('d.m.Y', strtotime($aksiyon->hedef_tarih)); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $aksiyon->ilerleme_durumu; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $aksiyon->ilerleme_durumu; ?>%</span>
                        </div>
                    </td>
                    <td>
                        <?php if(empty($aksiyon->kapanma_tarihi)): ?>
                            <span class="status-badge status-open">Açık</span>
                        <?php else: ?>
                            <span class="status-badge status-closed">Kapalı</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="<?php echo admin_url('admin.php?page=bkm-add-aksiyon&edit=' . $aksiyon->id); ?>" 
                               class="button button-small" title="Düzenle">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            <button class="button button-small bkm-view-details" 
                                    data-id="<?php echo $aksiyon->id; ?>" title="Detayları Gör">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button class="button button-small button-link-delete bkm-delete-aksiyon" 
                                    data-id="<?php echo $aksiyon->id; ?>" title="Sil">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detay Modal -->
<div id="bkm-detail-modal" class="bkm-modal" style="display: none;">
    <div class="bkm-modal-content">
        <span class="bkm-modal-close">&times;</span>
        <h2>Aksiyon Detayları</h2>
        <div id="bkm-modal-body"></div>
    </div>
</div>