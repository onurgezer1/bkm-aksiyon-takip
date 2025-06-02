<div class="bkm-detail-content">
    <div class="detail-header">
        <h3>Aksiyon #<?php echo $aksiyon->sira_no; ?></h3>
        <span class="status-badge <?php echo empty($aksiyon->kapanma_tarihi) ? 'status-open' : 'status-closed'; ?>">
            <?php echo empty($aksiyon->kapanma_tarihi) ? 'Açık' : 'Kapalı'; ?>
        </span>
    </div>
    
    <div class="detail-grid">
        <div class="detail-section">
            <h4>Temel Bilgiler</h4>
            <div class="detail-row">
                <strong>Tanımlayan:</strong> <?php echo esc_html($aksiyon->tanimlayan_adi); ?>
            </div>
            <div class="detail-row">
                <strong>Kategori:</strong> <?php echo esc_html($aksiyon->kategori_adi); ?>
            </div>
            <div class="detail-row">
                <strong>Önem Derecesi:</strong> 
                <span class="onem-badge onem-<?php echo $aksiyon->onem_derecesi; ?>">
                    <?php 
                    $onem_text = array(1 => 'Düşük', 2 => 'Orta', 3 => 'Yüksek');
                    echo $onem_text[$aksiyon->onem_derecesi]; 
                    ?>
                </span>
            </div>
            <div class="detail-row">
                <strong>Performans:</strong> <?php echo esc_html($aksiyon->performans_adi); ?>
            </div>
        </div>
        
        <div class="detail-section">
            <h4>Tarihler</h4>
            <div class="detail-row">
                <strong>Açılma Tarihi:</strong> <?php echo date('d.m.Y', strtotime($aksiyon->acilma_tarihi)); ?>
            </div>
            <?php if($aksiyon->hedef_tarih): ?>
            <div class="detail-row">
                <strong>Hedef Tarih:</strong> <?php echo date('d.m.Y', strtotime($aksiyon->hedef_tarih)); ?>
            </div>
            <?php endif; ?>
            <?php if($aksiyon->kapanma_tarihi): ?>
            <div class="detail-row">
                <strong>Kapanma Tarihi:</strong> <?php echo date('d.m.Y', strtotime($aksiyon->kapanma_tarihi)); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="detail-section">
        <h4>İlerleme Durumu</h4>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $aksiyon->ilerleme_durumu; ?>%"></div>
            </div>
            <span class="progress-text"><?php echo $aksiyon->ilerleme_durumu; ?>%</span>
        </div>
    </div>
    
    <?php if($aksiyon->tespit_nedeni): ?>
    <div class="detail-section">
        <h4>Tespit Nedeni</h4>
        <p><?php echo nl2br(esc_html($aksiyon->tespit_nedeni)); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="detail-section">
        <h4>Aksiyon Açıklaması</h4>
        <p><?php echo nl2br(esc_html($aksiyon->aksiyon_aciklamasi)); ?></p>
    </div>
    
    <?php if($aksiyon->notlar): ?>
    <div class="detail-section">
        <h4>Notlar</h4>
        <p><?php echo nl2br(esc_html($aksiyon->notlar)); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if($aksiyon->aksiyon_sorumlusu): ?>
    <div class="detail-section">
        <h4>Sorumlular</h4>
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
    </div>
    <?php endif; ?>
</div>

<style>
.bkm-detail-content .detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #0073aa;
}

.bkm-detail-content .detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.bkm-detail-content .detail-section {
    margin-bottom: 20px;
}

.bkm-detail-content .detail-section h4 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 14px;
    text-transform: uppercase;
}

.bkm-detail-content .detail-row {
    margin-bottom: 8px;
    font-size: 14px;
}

.bkm-detail-content .detail-row strong {
    display: inline-block;
    min-width: 120px;
}
</style>