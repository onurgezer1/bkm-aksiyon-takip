<div class="aksiyon-details-modal">
    <div class="detail-header">
        <div class="header-left">
            <h3>Aksiyon #<?php echo $aksiyon->sira_no; ?></h3>
            <div class="status-info">
                <span class="bkm-badge <?php echo empty($aksiyon->kapanma_tarihi) ? 'status-open' : 'status-closed'; ?>">
                    <?php echo empty($aksiyon->kapanma_tarihi) ? 'Açık' : 'Kapalı'; ?>
                </span>
                <span class="bkm-badge onem-<?php echo $aksiyon->onem_derecesi; ?>">
                    <?php 
                    $onem_text = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                    echo $onem_text[$aksiyon->onem_derecesi]; 
                    ?>
                </span>
            </div>
        </div>
        
        <div class="header-right">
            <div class="progress-display">
                <div class="progress-circle" data-percentage="<?php echo $aksiyon->ilerleme_durumu; ?>">
                    <span><?php echo $aksiyon->ilerleme_durumu; ?>%</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="detail-content">
        <div class="info-grid">
            <div class="info-section">
                <h4><i class="bkm-icon bkm-user"></i> Temel Bilgiler</h4>
                <div class="info-item">
                    <label>Tanımlayan:</label>
                    <span><?php echo esc_html($aksiyon->tanimlayan_adi); ?></span>
                </div>
                <div class="info-item">
                    <label>Kategori:</label>
                    <span><?php echo esc_html($aksiyon->kategori_adi ?: 'Belirlenmemiş'); ?></span>
                </div>
                <div class="info-item">
                    <label>Performans:</label>
                    <span><?php echo esc_html($aksiyon->performans_adi ?: 'Belirlenmemiş'); ?></span>
                </div>
                <div class="info-item">
                    <label>Hafta:</label>
                    <span><?php echo $aksiyon->hafta ?: '-'; ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h4><i class="bkm-icon bkm-calendar"></i> Tarih Bilgileri</h4>
                <div class="info-item">
                    <label>Açılma Tarihi:</label>
                    <span><?php echo date('d.m.Y', strtotime($aksiyon->acilma_tarihi)); ?></span>
                </div>
                <?php if($aksiyon->hedef_tarih): ?>
                <div class="info-item">
                    <label>Hedef Tarih:</label>
                    <span class="<?php echo ($aksiyon->hedef_tarih < date('Y-m-d') && empty($aksiyon->kapanma_tarihi)) ? 'text-danger' : ''; ?>">
                        <?php echo date('d.m.Y', strtotime($aksiyon->hedef_tarih)); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if($aksiyon->kapanma_tarihi): ?>
                <div class="info-item">
                    <label>Kapanma Tarihi:</label>
                    <span class="text-success"><?php echo date('d.m.Y', strtotime($aksiyon->kapanma_tarihi)); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Son Güncelleme:</label>
                    <span><?php echo human_time_diff(strtotime($aksiyon->guncelleme_tarihi)); ?> önce</span>
                </div>
            </div>
        </div>
        
        <?php if($aksiyon->aksiyon_sorumlusu): ?>
        <div class="info-section full-width">
            <h4><i class="bkm-icon bkm-users"></i> Sorumlular</h4>
            <div class="sorumlu-grid">
                <?php 
                $sorumlular = explode(',', $aksiyon->aksiyon_sorumlusu);
                foreach($sorumlular as $sorumlu_id):
                    $user = get_user_by('id', trim($sorumlu_id));
                    if($user):
                ?>
                <div class="sorumlu-card">
                    <div class="sorumlu-avatar">
                        <?php echo get_avatar($user->ID, 40); ?>
                    </div>
                    <div class="sorumlu-info">
                        <strong><?php echo $user->display_name; ?></strong>
                        <small><?php echo $user->user_email; ?></small>
                    </div>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-section full-width">
            <h4><i class="bkm-icon bkm-clipboard"></i> Aksiyon Açıklaması</h4>
            <div class="content-box">
                <?php echo nl2br(esc_html($aksiyon->aksiyon_aciklamasi)); ?>
            </div>
        </div>
        
        <?php if($aksiyon->tespit_nedeni): ?>
        <div class="info-section full-width">
            <h4><i class="bkm-icon bkm-warning"></i> Tespit Nedeni</h4>
            <div class="content-box">
                <?php echo nl2br(esc_html($aksiyon->tespit_nedeni)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($aksiyon->notlar): ?>
        <div class="info-section full-width">
            <h4><i class="bkm-icon bkm-note"></i> Notlar</h4>
            <div class="content-box">
                <?php echo nl2br(esc_html($aksiyon->notlar)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($gorevler)): ?>
        <div class="info-section full-width">
            <h4><i class="bkm-icon bkm-tasks"></i> İlgili Görevler (<?php echo count($gorevler); ?>)</h4>
            <div class="tasks-list">
                <?php foreach($gorevler as $gorev): ?>
                <div class="task-item <?php echo $gorev->durum; ?>">
                    <div class="task-status">
                        <?php if($gorev->durum === 'tamamlandi'): ?>
                            <i class="bkm-icon bkm-check-circle success"></i>
                        <?php else: ?>
                            <i class="bkm-icon bkm-clock warning"></i>
                        <?php endif; ?>
                    </div>
                    <div class="task-content">
                        <h5><?php echo esc_html($gorev->gorev_icerigi); ?></h5>
                        <div class="task-meta">
                            <span>Sorumlu: <?php echo esc_html($gorev->sorumlu_adi); ?></span>
                            <span>Hedef: <?php echo date('d.m.Y', strtotime($gorev->hedef_bitis_tarihi)); ?></span>
                            <span>İlerleme: <?php echo $gorev->ilerleme_durumu; ?>%</span>
                        </div>
                        <div class="task-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $gorev->ilerleme_durumu; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="modal-actions">
        <?php if($aksiyon->aksiyonu_tanimlayan == get_current_user_id() || 
                in_array(get_current_user_id(), explode(',', $aksiyon->aksiyon_sorumlusu))): ?>
        <button class="bkm-btn bkm-btn-primary update-progress-btn" data-id="<?php echo $aksiyon->id; ?>">
            <i class="bkm-icon bkm-edit"></i> İlerleme Güncelle
        </button>
        <?php endif; ?>
        
        <button class="bkm-btn bkm-btn-outline export-aksiyon" data-id="<?php echo $aksiyon->id; ?>">
            <i class="bkm-icon bkm-download"></i> Export
        </button>
        
        <button class="bkm-btn bkm-btn-outline print-aksiyon">
            <i class="bkm-icon bkm-print"></i> Yazdır
        </button>
    </div>
</div>

<style>
.aksiyon-details-modal {
    max-width: 800px;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
}

.header-left h3 {
    margin: 0 0 10px 0;
    color: var(--primary-color);
    font-size: 24px;
}

.status-info {
    display: flex;
    gap: 10px;
}

.progress-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(
        var(--success-color) 0deg,
        var(--success-color) var(--percentage, 0deg),
        var(--border-color) var(--percentage, 0deg),
        var(--border-color) 360deg
    );
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circle::before {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
}

.progress-circle span {
    position: relative;
    z-index: 1;
    font-weight: bold;
    color: var(--dark-color);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.info-section h4 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-section.full-width {
    grid-column: 1 / -1;
    margin-bottom: 25px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--light-color);
}

.info-item label {
    font-weight: 500;
    color: var(--secondary-color);
}

.content-box {
    background: var(--light-color);
    padding: 15px;
    border-radius: 6px;
    line-height: 1.6;
}

.sorumlu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.sorumlu-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--light-color);
    border-radius: 6px;
}

.sorumlu-avatar {
    border-radius: 50%;
    overflow: hidden;
}

.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.task-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: var(--light-color);
    border-radius: 6px;
    border-left: 4px solid var(--primary-color);
}

.task-item.tamamlandi {
    border-left-color: var(--success-color);
    background: rgba(40, 167, 69, 0.05);
}

.task-status {
    font-size: 20px;
}

.task-content {
    flex: 1;
}

.task-content h5 {
    margin: 0 0 8px 0;
    font-size: 14px;
}

.task-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    font-size: 12px;
    color: var(--secondary-color);
}

.task-progress .progress-bar {
    height: 6px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .detail-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .sorumlu-grid {
        grid-template-columns: 1fr;
    }
    
    .task-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>