<?php

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

class BKM_Frontend {
    
    public function __construct() {
        add_shortcode('aksiyon_takipx', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // AJAX işlemleri
        add_action('wp_ajax_bkm_user_login', array($this, 'handle_user_login'));
        add_action('wp_ajax_nopriv_bkm_user_login', array($this, 'handle_user_login'));
        add_action('wp_ajax_bkm_add_task', array($this, 'add_task'));
        add_action('wp_ajax_bkm_update_task', array($this, 'update_task'));
        add_action('wp_ajax_bkm_complete_task', array($this, 'complete_task'));
        add_action('wp_ajax_bkm_delete_task', array($this, 'delete_task'));
        add_action('wp_ajax_bkm_get_user_data', array($this, 'get_user_data'));
        add_action('wp_ajax_bkm_update_aksiyon_progress', array($this, 'update_aksiyon_progress'));
        add_action('wp_ajax_bkm_get_user_stats', array($this, 'get_user_stats'));
        add_action('wp_ajax_bkm_get_aksiyon_details', array($this, 'get_aksiyon_details'));
        add_action('wp_ajax_bkm_update_task_progress', array($this, 'update_task_progress'));
        add_action('wp_ajax_bkm_get_recent_activities', array($this, 'get_recent_activities'));
        add_action('wp_ajax_bkm_search_content', array($this, 'search_content'));
        add_action('wp_ajax_bkm_export_user_data', array($this, 'export_user_data'));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('bkm-frontend-css', BKM_PLUGIN_URL . 'frontend/css/frontend.css', array(), BKM_VERSION);
        wp_enqueue_script('bkm-frontend-js', BKM_PLUGIN_URL . 'frontend/js/frontend.js', array('jquery'), BKM_VERSION, true);
        
        wp_localize_script('bkm-frontend-js', 'bkm_frontend_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bkm_frontend_nonce'),
            'current_user_id' => get_current_user_id(),
            'strings' => array(
                'loading' => 'Yükleniyor...',
                'error' => 'Bir hata oluştu!',
                'success' => 'İşlem başarıyla tamamlandı!',
                'confirm_delete' => 'Bu görevi silmek istediğinizden emin misiniz?',
                'confirm_complete' => 'Bu görevi tamamlandı olarak işaretlemek istediğinizden emin misiniz?'
            )
        ));
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'login_redirect' => '',
            'show_stats' => 'true',
            'items_per_page' => '10'
        ), $atts);
        
        ob_start();
        
        if (!is_user_logged_in()) {
            $this->render_login_form($atts['login_redirect']);
        } else {
            $this->render_user_dashboard($atts);
        }
        
        return ob_get_clean();
    }
    
    private function render_login_form($redirect = '') {
        ?>
        <div id="bkm-frontend-container">
            <div class="bkm-login-wrapper">
                <div class="bkm-login-card">
                    <div class="bkm-login-header">
                        <h2>BKM Aksiyon Takip Sistemi</h2>
                        <p>Lütfen giriş yapınız</p>
                    </div>
                    
                    <form id="bkm-frontend-login-form" method="post">
                        <div class="bkm-form-group">
                            <label for="bkm_username">
                                <i class="bkm-icon bkm-user"></i>
                                Kullanıcı Adı
                            </label>
                            <input type="text" id="bkm_username" name="username" required autocomplete="username">
                        </div>
                        
                        <div class="bkm-form-group">
                            <label for="bkm_password">
                                <i class="bkm-icon bkm-lock"></i>
                                Şifre
                            </label>
                            <input type="password" id="bkm_password" name="password" required autocomplete="current-password">
                            <span class="password-toggle" onclick="BKM_Frontend.togglePassword()">
                                <i class="bkm-icon bkm-eye"></i>
                            </span>
                        </div>
                        
                        <div class="bkm-form-group">
                            <label class="bkm-checkbox">
                                <input type="checkbox" name="remember_me">
                                <span class="checkmark"></span>
                                Beni hatırla
                            </label>
                        </div>
                        
                        <div class="bkm-form-group">
                            <button type="submit" class="bkm-btn bkm-btn-primary">
                                <span class="btn-text">Giriş Yap</span>
                                <span class="btn-loading" style="display: none;">
                                    <i class="bkm-spinner"></i> Giriş yapılıyor...
                                </span>
                            </button>
                        </div>
                        
                        <div id="bkm-login-message"></div>
                        
                        <?php if($redirect): ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                        <?php endif; ?>
                        
                        <?php wp_nonce_field('bkm_frontend_nonce', 'bkm_nonce'); ?>
                    </form>
                    
                    <div class="bkm-login-footer">
                        <small>© <?php echo date('Y'); ?> BKM Aksiyon Takip Sistemi</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_user_dashboard($atts) {
        $current_user = wp_get_current_user();
        $user_stats = $this->get_user_statistics($current_user->ID);
        
        ?>
        <div id="bkm-frontend-container">
            <div class="bkm-dashboard">
                <!-- Dashboard Header -->
                <div class="bkm-dashboard-header">
                    <div class="user-welcome">
                        <div class="user-avatar">
                            <?php echo get_avatar($current_user->ID, 50); ?>
                        </div>
                        <div class="user-info">
                            <h2>Hoş Geldiniz, <?php echo esc_html($current_user->display_name); ?></h2>
                            <p>Son giriş: <?php echo date('d.m.Y H:i', strtotime($current_user->user_registered)); ?></p>
                        </div>
                    </div>
                    
                    <div class="dashboard-actions">
                        <button class="bkm-btn bkm-btn-outline refresh-data">
                            <i class="bkm-icon bkm-refresh"></i> Yenile
                        </button>
                        <a href="<?php echo wp_logout_url(); ?>" class="bkm-btn bkm-btn-danger">
                            <i class="bkm-icon bkm-logout"></i> Çıkış
                        </a>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <?php if($atts['show_stats'] === 'true'): ?>
                <div class="bkm-stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="bkm-icon bkm-clipboard"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['total_actions']; ?></h3>
                            <p>Toplam Aksiyonlarım</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="bkm-icon bkm-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['completed_actions']; ?></h3>
                            <p>Tamamlanan</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="bkm-icon bkm-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['pending_actions']; ?></h3>
                            <p>Bekleyen</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="bkm-icon bkm-warning"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['overdue_tasks']; ?></h3>
                            <p>Geciken Görevler</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="bkm-tabs">
                    <div class="bkm-tab-nav">
                        <button class="bkm-tab-button active" data-tab="aksiyonlar">
                            <i class="bkm-icon bkm-list"></i> Aksiyonlarım
                        </button>
                        <button class="bkm-tab-button" data-tab="gorevler">
                            <i class="bkm-icon bkm-tasks"></i> 
                            Görevlerim 
                            <span class="badge"><?php echo $user_stats['total_tasks']; ?></span>
                        </button>
                        <?php if (current_user_can('edit_posts')): ?>
                        <button class="bkm-tab-button" data-tab="yeni-gorev">
                            <i class="bkm-icon bkm-plus"></i> Yeni Görev
                        </button>
                        <?php endif; ?>
                        <button class="bkm-tab-button" data-tab="raporlarim">
                            <i class="bkm-icon bkm-chart"></i> Raporlarım
                        </button>
                    </div>

                    <!-- Aksiyonlarım Tab -->
                    <div id="aksiyonlar-tab" class="bkm-tab-content active">
                        <?php $this->render_user_actions_tab($current_user->ID, $atts); ?>
                    </div>

                    <!-- Görevlerim Tab -->
                    <div id="gorevler-tab" class="bkm-tab-content">
                        <?php $this->render_user_tasks_tab($current_user->ID, $atts); ?>
                    </div>

                    <!-- Yeni Görev Tab -->
                    <?php if (current_user_can('edit_posts')): ?>
                    <div id="yeni-gorev-tab" class="bkm-tab-content">
                        <?php $this->render_add_task_tab($current_user->ID); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Raporlarım Tab -->
                    <div id="raporlarim-tab" class="bkm-tab-content">
                        <?php $this->render_user_reports_tab($current_user->ID); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_user_actions_tab($user_id, $atts) {
        $aksiyonlar = $this->get_user_actions($user_id);
        ?>
        <div class="bkm-content-header">
            <h3>Aksiyonlarım (<?php echo count($aksiyonlar); ?>)</h3>
            <div class="content-filters">
                <select id="aksiyon-kategori-filter" class="bkm-select">
                    <option value="">Tüm Kategoriler</option>
                    <?php foreach($this->get_kategoriler() as $kategori): ?>
                    <option value="<?php echo $kategori->id; ?>"><?php echo esc_html($kategori->kategori_adi); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="aksiyon-durum-filter" class="bkm-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="acik">Açık</option>
                    <option value="kapali">Kapalı</option>
                </select>
                
                <input type="text" id="aksiyon-search" class="bkm-input" placeholder="Aksiyon ara...">
            </div>
        </div>

        <?php if (empty($aksiyonlar)): ?>
        <div class="no-data-message">
            <i class="bkm-icon bkm-info"></i>
            <h3>Henüz aksiyon bulunmuyor</h3>
            <p>Size atanmış veya tanımladığınız bir aksiyon bulunmamaktadır.</p>
        </div>
        <?php else: ?>
        <div class="bkm-table-responsive">
            <table class="bkm-table" id="user-aksiyonlar-table">
                <thead>
                    <tr>
                        <th>Sıra No</th>
                        <th>Aksiyon</th>
                        <th>Kategori</th>
                        <th>Önem</th>
                        <th>Açılma</th>
                        <th>Hedef</th>
                        <th>İlerleme</th>
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
                            <div class="aksiyon-title"><?php echo wp_trim_words($aksiyon->aksiyon_aciklamasi, 6); ?></div>
                            <?php if($aksiyon->tespit_nedeni): ?>
                            <div class="aksiyon-subtitle"><?php echo wp_trim_words($aksiyon->tespit_nedeni, 4); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($aksiyon->kategori_adi): ?>
                            <span class="bkm-badge kategori"><?php echo esc_html($aksiyon->kategori_adi); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="bkm-badge onem-<?php echo $aksiyon->onem_derecesi; ?>">
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
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="progress-wrapper">
                                <div class="progress-bar" data-aksiyon-id="<?php echo $aksiyon->id; ?>">
                                    <div class="progress-fill" style="width: <?php echo $aksiyon->ilerleme_durumu; ?>%"></div>
                                </div>
                                <span class="progress-text"><?php echo $aksiyon->ilerleme_durumu; ?>%</span>
                                <?php if($aksiyon->aksiyonu_tanimlayan == get_current_user_id() || 
                                        in_array(get_current_user_id(), explode(',', $aksiyon->aksiyon_sorumlusu))): ?>
                                <input type="range" class="progress-slider" 
                                       min="0" max="100" 
                                       value="<?php echo $aksiyon->ilerleme_durumu; ?>"
                                       data-aksiyon-id="<?php echo $aksiyon->id; ?>"
                                       style="display: none;">
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if(empty($aksiyon->kapanma_tarihi)): ?>
                                <span class="bkm-badge status-open">Açık</span>
                            <?php else: ?>
                                <span class="bkm-badge status-closed">Kapalı</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="bkm-btn bkm-btn-sm bkm-btn-outline view-aksiyon-details" 
                                        data-id="<?php echo $aksiyon->id; ?>" title="Detayları Gör">
                                    <i class="bkm-icon bkm-eye"></i>
                                </button>
                                
                                <button class="bkm-btn bkm-btn-sm bkm-btn-outline edit-progress" 
                                        data-id="<?php echo $aksiyon->id; ?>" title="İlerleme Güncelle">
                                    <i class="bkm-icon bkm-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
    }
    
    private function render_user_tasks_tab($user_id, $atts) {
        $gorevler = $this->get_user_tasks($user_id);
        ?>
        <div class="bkm-content-header">
            <h3>Görevlerim (<?php echo count($gorevler); ?>)</h3>
            <div class="content-filters">
                <select id="gorev-durum-filter" class="bkm-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="aktif">Aktif</option>
                    <option value="tamamlandi">Tamamlandı</option>
                </select>
                
                <select id="gorev-siralama" class="bkm-select">
                    <option value="hedef_bitis_tarihi">Hedef Tarihe Göre</option>
                    <option value="olusturma_tarihi">Oluşturma Tarihi</option>
                    <option value="ilerleme_durumu">İlerleme Durumu</option>
                </select>
            </div>
        </div>

        <?php if (empty($gorevler)): ?>
        <div class="no-data-message">
            <i class="bkm-icon bkm-info"></i>
            <h3>Henüz görev bulunmuyor</h3>
            <p>Size atanmış bir görev bulunmamaktadır.</p>
        </div>
        <?php else: ?>
        <div class="bkm-tasks-grid">
            <?php foreach($gorevler as $gorev): ?>
            <div class="task-card <?php echo $gorev->durum; ?> <?php echo $gorev->durum === 'tamamlandi' ? 'completed' : ''; ?>" 
                 data-durum="<?php echo $gorev->durum; ?>">
                
                <div class="task-header">
                    <div class="task-status">
                        <?php if($gorev->durum === 'tamamlandi'): ?>
                            <i class="bkm-icon bkm-check-circle success"></i>
                        <?php else: ?>
                            <i class="bkm-icon bkm-clock warning"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-actions">
                        <?php if($gorev->sorumlu_kisi == get_current_user_id() && $gorev->durum === 'aktif'): ?>
                        <button class="bkm-btn bkm-btn-sm bkm-btn-outline edit-task" 
                                data-id="<?php echo $gorev->id; ?>" title="Düzenle">
                            <i class="bkm-icon bkm-edit"></i>
                        </button>
                        <?php endif; ?>
                        
                        <?php if(current_user_can('manage_options') || $gorev->olusturan == get_current_user_id()): ?>
                        <button class="bkm-btn bkm-btn-sm bkm-btn-danger delete-task" 
                                data-id="<?php echo $gorev->id; ?>" title="Sil">
                            <i class="bkm-icon bkm-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="task-content">
                    <h4><?php echo wp_trim_words($gorev->gorev_icerigi, 8); ?></h4>
                    
                    <div class="task-meta">
                        <div class="meta-item">
                            <i class="bkm-icon bkm-calendar"></i>
                            <span>Başlangıç: <?php echo date('d.m.Y', strtotime($gorev->baslangic_tarihi)); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="bkm-icon bkm-target"></i>
                            <span class="<?php echo ($gorev->hedef_bitis_tarihi < date('Y-m-d') && $gorev->durum === 'aktif') ? 'text-danger' : ''; ?>">
                                Hedef: <?php echo date('d.m.Y', strtotime($gorev->hedef_bitis_tarihi)); ?>
                            </span>
                        </div>
                        
                        <?php if($gorev->gercek_bitis_tarihi): ?>
                        <div class="meta-item">
                            <i class="bkm-icon bkm-check"></i>
                            <span>Bitiş: <?php echo date('d.m.Y', strtotime($gorev->gercek_bitis_tarihi)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="task-progress">
                        <div class="progress-info">
                            <span>İlerleme</span>
                            <span><?php echo $gorev->ilerleme_durumu; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $gorev->ilerleme_durumu; ?>%"></div>
                        </div>
                        
                        <?php if($gorev->sorumlu_kisi == get_current_user_id() && $gorev->durum === 'aktif'): ?>
                        <input type="range" class="task-progress-slider" 
                               min="0" max="100" 
                               value="<?php echo $gorev->ilerleme_durumu; ?>"
                               data-task-id="<?php echo $gorev->id; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <?php if($gorev->aksiyon_aciklamasi): ?>
                    <div class="related-action">
                        <i class="bkm-icon bkm-link"></i>
                        <small>İlgili Aksiyon: <?php echo wp_trim_words($gorev->aksiyon_aciklamasi, 6); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="task-footer">
                    <?php if($gorev->sorumlu_kisi == get_current_user_id() && $gorev->durum === 'aktif'): ?>
                    <button class="bkm-btn bkm-btn-success complete-task" data-id="<?php echo $gorev->id; ?>">
                        <i class="bkm-icon bkm-check"></i> Tamamla
                    </button>
                    <?php endif; ?>
                    
                    <?php 
                    $gecikme_gun = 0;
                    if($gorev->hedef_bitis_tarihi < date('Y-m-d') && $gorev->durum === 'aktif') {
                        $gecikme_gun = floor((time() - strtotime($gorev->hedef_bitis_tarihi)) / (60 * 60 * 24));
                    }
                    if($gecikme_gun > 0): 
                    ?>
                    <span class="overdue-badge">
                        <i class="bkm-icon bkm-warning"></i> <?php echo $gecikme_gun; ?> gün gecikme
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
    }
    
    private function render_add_task_tab($user_id) {
        $aksiyonlar = $this->get_user_actions($user_id);
        $users = get_users();
        ?>
        <div class="bkm-content-header">
            <h3>Yeni Görev Oluştur</h3>
            <p>Aksiyon bazlı görev oluşturabilir ve kullanıcılara atayabilirsiniz.</p>
        </div>

        <form id="bkm-add-task-form" class="bkm-form">
            <div class="form-grid">
                <div class="form-section">
                    <h4>Görev Bilgileri</h4>
                    
                    <div class="bkm-form-group">
                        <label for="aksiyon_id">İlgili Aksiyon *</label>
                        <select name="aksiyon_id" id="aksiyon_id" class="bkm-select" required>
                            <option value="">Aksiyon Seçiniz</option>
                            <?php foreach($aksiyonlar as $aksiyon): ?>
                            <option value="<?php echo $aksiyon->id; ?>">
                                #<?php echo $aksiyon->sira_no; ?> - <?php echo wp_trim_words($aksiyon->aksiyon_aciklamasi, 8); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bkm-form-group">
                        <label for="gorev_icerigi">Görev İçeriği *</label>
                        <textarea name="gorev_icerigi" id="gorev_icerigi" class="bkm-textarea" 
                                  rows="4" required placeholder="Görevin detaylı açıklamasını yazınız..."></textarea>
                    </div>
                    
                    <div class="bkm-form-group">
                        <label for="sorumlu_kisi">Sorumlu Kişi *</label>
                        <select name="sorumlu_kisi" id="sorumlu_kisi" class="bkm-select" required>
                            <option value="">Kullanıcı Seçiniz</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>">
                                <?php echo esc_html($user->display_name); ?> (<?php echo $user->user_login; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Tarih ve İlerleme</h4>
                    
                    <div class="bkm-form-group">
                        <label for="baslangic_tarihi">Başlangıç Tarihi *</label>
                        <input type="date" name="baslangic_tarihi" id="baslangic_tarihi" 
                               class="bkm-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="bkm-form-group">
                        <label for="hedef_bitis_tarihi">Hedef Bitiş Tarihi *</label>
                        <input type="date" name="hedef_bitis_tarihi" id="hedef_bitis_tarihi" 
                               class="bkm-input" required>
                    </div>
                    
                    <div class="bkm-form-group">
                        <label for="ilerleme_durumu">Başlangıç İlerleme Durumu (%)</label>
                        <div class="progress-input-wrapper">
                            <input type="range" name="ilerleme_durumu" id="ilerleme_durumu" 
                                   class="bkm-range" min="0" max="100" value="0">
                            <span class="progress-value">0%</span>
                        </div>
                        <div class="progress-preview">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="bkm-btn bkm-btn-primary">
                    <i class="bkm-icon bkm-plus"></i>
                    <span class="btn-text">Görev Oluştur</span>
                    <span class="btn-loading" style="display: none;">
                        <i class="bkm-spinner"></i> Oluşturuluyor...
                    </span>
                </button>
                <button type="reset" class="bkm-btn bkm-btn-outline">
                    <i class="bkm-icon bkm-refresh"></i> Temizle
                </button>
            </div>
            
            <?php wp_nonce_field('bkm_frontend_nonce', 'bkm_nonce'); ?>
        </form>
        <?php
    }
    
    private function render_user_reports_tab($user_id) {
        $monthly_stats = $this->get_user_monthly_stats($user_id);
        $performance_data = $this->get_user_performance_data($user_id);
        ?>
        <div class="bkm-content-header">
            <h3>Kişisel Raporlarım</h3>
            <p>Performans analizi ve istatistikleriniz</p>
        </div>

        <div class="reports-grid">
            <!-- Aylık Performans -->
            <div class="report-card">
                <h4>Bu Ay Performansım</h4>
                <div class="performance-circle">
                    <div class="circle-progress" data-percentage="<?php echo $performance_data['completion_rate']; ?>">
                        <span><?php echo $performance_data['completion_rate']; ?>%</span>
                    </div>
                    <p>Tamamlanma Oranı</p>
                </div>
            </div>
            
            <!-- Görev Dağılımı -->
            <div class="report-card">
                <h4>Görev Durumu</h4>
                <div class="task-distribution">
                    <div class="distribution-item">
                        <span class="dot active"></span>
                        <span>Aktif: <?php echo $performance_data['active_tasks']; ?></span>
                    </div>
                    <div class="distribution-item">
                        <span class="dot completed"></span>
                        <span>Tamamlanan: <?php echo $performance_data['completed_tasks']; ?></span>
                    </div>
                    <div class="distribution-item">
                        <span class="dot overdue"></span>
                        <span>Geciken: <?php echo $performance_data['overdue_tasks']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Son Aktiviteler -->
            <div class="report-card full-width">
                <h4>Son Aktivitelerim</h4>
                <div class="activity-timeline">
                    <?php foreach($this->get_user_recent_activities($user_id, 10) as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $activity->type; ?>"></div>
                        <div class="timeline-content">
                            <p><?php echo $activity->description; ?></p>
                            <small><?php echo human_time_diff(strtotime($activity->created_at)); ?> önce</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    // AJAX Handler: Kullanıcı girişi
    public function handle_user_login() {
        if (!wp_verify_nonce($_POST['bkm_nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember_me']);
        
        // Brute force koruması
        $login_attempts = get_transient('bkm_login_attempts_' . $username);
        if ($login_attempts && $login_attempts >= 5) {
            wp_send_json_error(array('message' => 'Çok fazla başarısız giriş denemesi. 15 dakika sonra tekrar deneyiniz.'));
        }
        
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            // Başarısız giriş sayacını artır
            $attempts = $login_attempts ? $login_attempts + 1 : 1;
            set_transient('bkm_login_attempts_' . $username, $attempts, 15 * MINUTE_IN_SECONDS);
            
            wp_send_json_error(array('message' => 'Kullanıcı adı veya şifre hatalı!'));
        }
        
        // Başarılı giriş - sayacı temizle
        delete_transient('bkm_login_attempts_' . $username);
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        
        // Giriş log'u
        $this->log_user_activity($user->ID, 'login', 'Kullanıcı giriş yaptı');
        
        wp_send_json_success(array(
            'message' => 'Giriş başarılı! Yönlendiriliyorsunuz...',
            'redirect' => isset($_POST['redirect_to']) ? $_POST['redirect_to'] : '',
            'user_name' => $user->display_name
        ));
    }
    
    // AJAX Handler: Görev ekleme
    public function add_task() {
        if (!wp_verify_nonce($_POST['bkm_nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Bu işlem için yetkiniz yok!'));
        }
        
        global $wpdb;
        
        $data = array(
            'aksiyon_id' => intval($_POST['aksiyon_id']),
            'gorev_icerigi' => sanitize_textarea_field($_POST['gorev_icerigi']),
            'baslangic_tarihi' => sanitize_text_field($_POST['baslangic_tarihi']),
            'sorumlu_kisi' => intval($_POST['sorumlu_kisi']),
            'hedef_bitis_tarihi' => sanitize_text_field($_POST['hedef_bitis_tarihi']),
            'ilerleme_durumu' => intval($_POST['ilerleme_durumu']),
            'olusturan' => get_current_user_id()
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'bkm_gorevler',
            $data,
            array('%d', '%s', '%s', '%d', '%s', '%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Görev başarıyla oluşturuldu!'));
        } else {
            wp_send_json_error(array('message' => 'Görev oluşturma başarısız!'));
        }
    }
    
    // AJAX Handler: Görev güncelleme
    public function update_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Yetki kontrolü
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array();
        
        if (isset($_POST['gorev_icerigi'])) {
            $update_data['gorev_icerigi'] = sanitize_textarea_field($_POST['gorev_icerigi']);
        }
        
        if (isset($_POST['hedef_bitis_tarihi'])) {
            $update_data['hedef_bitis_tarihi'] = sanitize_text_field($_POST['hedef_bitis_tarihi']);
        }
        
        if (isset($_POST['ilerleme_durumu'])) {
            $update_data['ilerleme_durumu'] = intval($_POST['ilerleme_durumu']);
        }
        
        $update_data['guncelleme_tarihi'] = current_time('mysql');
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            $update_data,
            array('id' => $task_id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'task_updated', "Görev güncellendi: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla güncellendi!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev güncellenirken bir hata oluştu!'));
        }
    }
    
    // AJAX Handler: Görev tamamlama
    public function complete_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Görevin kullanıcıya ait olup olmadığını kontrol et
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        if ($task->durum === 'tamamlandi') {
            wp_send_json_error(array('message' => 'Bu görev zaten tamamlanmış!'));
        }
        
        // Görevi tamamla
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            array(
                'durum' => 'tamamlandi',
                'ilerleme_durumu' => 100,
                'gercek_bitis_tarihi' => current_time('Y-m-d'),
                'guncelleme_tarihi' => current_time('mysql')
            ),
            array('id' => $task_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Aktivite log'u ekle
            $this->add_activity_log($user_id, 'task_completed', "Görev tamamlandı: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla tamamlandı!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev tamamlanırken bir hata oluştu!'));
        }
    }
    
    // AJAX Handler: Görev silme
    public function delete_task() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $user_id = get_current_user_id();
        
        // Görevin silinip silinemeyeceğini kontrol et
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d",
            $task_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı!'));
        }
        
        // Yetki kontrolü: sadece yöneticiler veya görev oluşturanlar silebilir
        if (!current_user_can('manage_options') && $task->olusturan != $user_id) {
            wp_send_json_error(array('message' => 'Bu görevi silme yetkiniz yok!'));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'bkm_gorevler',
            array('id' => $task_id),
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'task_deleted', "Görev silindi: " . wp_trim_words($task->gorev_icerigi, 6));
            
            wp_send_json_success(array(
                'message' => 'Görev başarıyla silindi!',
                'task_id' => $task_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Görev silinirken bir hata oluştu!'));
        }
    }
    
    // AJAX Handler: Kullanıcı verilerini al
    public function get_user_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'all');
        
        $data = array();
        
        switch ($data_type) {
            case 'aksiyonlar':
                $data['aksiyonlar'] = $this->get_user_actions($user_id);
                break;
            case 'gorevler':
                $data['gorevler'] = $this->get_user_tasks($user_id);
                break;
            case 'stats':
                $data['stats'] = $this->calculate_user_stats($user_id);
                break;
            default:
                $data['aksiyonlar'] = $this->get_user_actions($user_id);
                $data['gorevler'] = $this->get_user_tasks($user_id);
                $data['stats'] = $this->calculate_user_stats($user_id);
        }
        
        wp_send_json_success($data);
    }
    
    // AJAX Handler: Aksiyon ilerleme güncelleme
    public function update_aksiyon_progress() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        $progress = intval($_POST['progress']);
        $user_id = get_current_user_id();
        
        // Progress validasyonu
        if ($progress < 0 || $progress > 100) {
            wp_send_json_error(array('message' => 'İlerleme değeri 0-100 arasında olmalıdır!'));
        }
        
        // Yetki kontrolü
        $aksiyon = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE id = %d 
            AND (aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu))
        ", $aksiyon_id, $user_id, $user_id));
        
        if (!$aksiyon) {
            wp_send_json_error(array('message' => 'Aksiyon bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array(
            'ilerleme_durumu' => $progress,
            'guncelleme_tarihi' => current_time('mysql')
        );
        
        // %100 olduğunda kapanma tarihini set et
        if ($progress == 100 && empty($aksiyon->kapanma_tarihi)) {
            $update_data['kapanma_tarihi'] = current_time('Y-m-d');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_aksiyonlar',
            $update_data,
            array('id' => $aksiyon_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->add_activity_log($user_id, 'aksiyon_updated', "Aksiyon ilerlemesi güncellendi: #" . $aksiyon->sira_no);
            
            wp_send_json_success(array(
                'message' => 'Aksiyon ilerlemesi güncellendi!',
                'progress' => $progress
            ));
        } else {
            wp_send_json_error(array('message' => 'İlerleme güncellenirken bir hata oluştu!'));
        }
    }
    
    // AJAX Handler: Kullanıcı istatistikleri
    public function get_user_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $stats = $this->calculate_user_stats($user_id);
        
        wp_send_json_success($stats);
    }
    
    // AJAX Handler: Aksiyon detayları
    public function get_aksiyon_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $aksiyon_id = intval($_POST['aksiyon_id']);
        $user_id = get_current_user_id();
        
        $aksiyon = $wpdb->get_row($wpdb->prepare("
            SELECT a.*, 
                   k.kategori_adi, 
                   p.performans_adi,
                   u.display_name as tanimlayan_adi
            FROM {$wpdb->prefix}bkm_aksiyonlar a
            LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
            LEFT JOIN {$wpdb->prefix}bkm_performanslar p ON a.performans_id = p.id
            LEFT JOIN {$wpdb->users} u ON a.aksiyonu_tanimlayan = u.ID
            WHERE a.id = %d
            AND (a.aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, a.aksiyon_sorumlusu))
        ", $aksiyon_id, $user_id, $user_id));
        
        if (!$aksiyon) {
            wp_send_json_error(array('message' => 'Aksiyon bulunamadı veya erişim yetkiniz yok!'));
        }
        
        // İlgili görevleri al
        $gorevler = $wpdb->get_results($wpdb->prepare("
            SELECT g.*, u.display_name as sorumlu_adi
            FROM {$wpdb->prefix}bkm_gorevler g
            LEFT JOIN {$wpdb->users} u ON g.sorumlu_kisi = u.ID
            WHERE g.aksiyon_id = %d
            ORDER BY g.olusturma_tarihi DESC
        ", $aksiyon_id));
        
        ob_start();
        $template_path = BKM_PLUGIN_PATH . 'frontend/templates/aksiyon-details-modal.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Detay template dosyası bulunamadı.</p>';
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    // AJAX Handler: Görev ilerleme güncelleme
    public function update_task_progress() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        global $wpdb;
        
        $task_id = intval($_POST['task_id']);
        $progress = intval($_POST['progress']);
        $user_id = get_current_user_id();
        
        // Progress validasyonu
        if ($progress < 0 || $progress > 100) {
            wp_send_json_error(array('message' => 'İlerleme değeri 0-100 arasında olmalıdır!'));
        }
        
        // Yetki kontrolü
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkm_gorevler WHERE id = %d AND sorumlu_kisi = %d",
            $task_id, $user_id
        ));
        
        if (!$task) {
            wp_send_json_error(array('message' => 'Görev bulunamadı veya yetkiniz yok!'));
        }
        
        $update_data = array(
            'ilerleme_durumu' => $progress,
            'guncelleme_tarihi' => current_time('mysql')
        );
        
        // %100 olduğunda otomatik tamamla
        if ($progress == 100 && $task->durum !== 'tamamlandi') {
            $update_data['durum'] = 'tamamlandi';
            $update_data['gercek_bitis_tarihi'] = current_time('Y-m-d');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'bkm_gorevler',
            $update_data,
            array('id' => $task_id),
            array('%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'İlerleme başarıyla güncellendi!',
                'progress' => $progress,
                'completed' => $progress == 100
            ));
        } else {
            wp_send_json_error(array('message' => 'İlerleme güncellenirken bir hata oluştu!'));
        }
    }
    
    // AJAX Handler: Son aktiviteler
    public function get_recent_activities() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 10);
        
        $activities = $this->get_user_activities($user_id, $limit);
        
        wp_send_json_success(array('activities' => $activities));
    }
    
        // AJAX Handler: İçerik arama (devam)
    public function search_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $search_type = sanitize_text_field($_POST['search_type'] ?? 'all');
        $user_id = get_current_user_id();
        
        if (strlen($search_term) < 2) {
            wp_send_json_error(array('message' => 'Arama terimi en az 2 karakter olmalıdır!'));
        }
        
        global $wpdb;
        
        $results = array();
        
        if ($search_type === 'all' || $search_type === 'aksiyonlar') {
            $aksiyonlar = $wpdb->get_results($wpdb->prepare("
                SELECT a.*, k.kategori_adi
                FROM {$wpdb->prefix}bkm_aksiyonlar a
                LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
                WHERE (a.aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, a.aksiyon_sorumlusu))
                AND (a.aksiyon_aciklamasi LIKE %s OR a.tespit_nedeni LIKE %s OR a.notlar LIKE %s)
                ORDER BY a.olusturma_tarihi DESC
                LIMIT 20
            ", $user_id, $user_id, '%' . $search_term . '%', '%' . $search_term . '%', '%' . $search_term . '%'));
            
            $results['aksiyonlar'] = $aksiyonlar;
        }
        
        if ($search_type === 'all' || $search_type === 'gorevler') {
            $gorevler = $wpdb->get_results($wpdb->prepare("
                SELECT g.*, a.aksiyon_aciklamasi
                FROM {$wpdb->prefix}bkm_gorevler g
                LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON g.aksiyon_id = a.id
                WHERE g.sorumlu_kisi = %d
                AND g.gorev_icerigi LIKE %s
                ORDER BY g.olusturma_tarihi DESC
                LIMIT 20
            ", $user_id, '%' . $search_term . '%'));
            
            $results['gorevler'] = $gorevler;
        }
        
        wp_send_json_success($results);
    }
    
    // AJAX Handler: Veri export
    public function export_user_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'bkm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız!'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Giriş yapmanız gerekiyor!'));
        }
        
        $user_id = get_current_user_id();
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'excel');
        $data_range = sanitize_text_field($_POST['data_range'] ?? 'all');
        
        // Export dosyası oluştur
        $file_url = $this->create_export_file($user_id, $export_type, $data_range);
        
        if ($file_url) {
            wp_send_json_success(array(
                'message' => 'Export dosyası oluşturuldu!',
                'download_url' => $file_url
            ));
        } else {
            wp_send_json_error(array('message' => 'Export dosyası oluşturulamadı!'));
        }
    }
    
    // Helper metodlar
    private function get_user_actions($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*, 
                   k.kategori_adi, 
                   p.performans_adi
            FROM {$wpdb->prefix}bkm_aksiyonlar a
            LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
            LEFT JOIN {$wpdb->prefix}bkm_performanslar p ON a.performans_id = p.id
            WHERE a.aksiyonu_tanimlayan = %d 
               OR FIND_IN_SET(%d, a.aksiyon_sorumlusu)
            ORDER BY a.acilma_tarihi DESC
        ", $user_id, $user_id));
    }
    
    private function get_user_tasks($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT g.*, a.aksiyon_aciklamasi 
            FROM {$wpdb->prefix}bkm_gorevler g
            LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON g.aksiyon_id = a.id
            WHERE g.sorumlu_kisi = %d
            ORDER BY g.hedef_bitis_tarihi ASC, g.durum ASC
        ", $user_id));
    }
    
    private function get_user_statistics($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Aksiyon istatistikleri
        $stats['total_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu)
        ", $user_id, $user_id));
        
        $stats['completed_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE (aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu))
            AND kapanma_tarihi IS NOT NULL AND kapanma_tarihi != ''
        ", $user_id, $user_id));
        
        $stats['pending_actions'] = $stats['total_actions'] - $stats['completed_actions'];
        
        // Görev istatistikleri
        $stats['total_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler WHERE sorumlu_kisi = %d
        ", $user_id));
        
        $stats['overdue_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif' 
            AND hedef_bitis_tarihi < CURDATE()
        ", $user_id));
        
        return $stats;
    }
    
    private function get_kategoriler() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bkm_kategoriler ORDER BY kategori_adi");
    }
    
    private function get_user_monthly_stats($user_id) {
        // Bu ay için istatistikler
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(olusturma_tarihi, '%Y-%m') as ay,
                COUNT(*) as toplam_gorev,
                COUNT(CASE WHEN durum = 'tamamlandi' THEN 1 END) as tamamlanan_gorev
            FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d 
            AND olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(olusturma_tarihi, '%Y-%m')
            ORDER BY ay DESC
        ", $user_id));
    }
    
    private function get_user_performance_data($user_id) {
        global $wpdb;
        
        $data = array();
        
        // Bu ay tamamlanma oranı
        $total_tasks = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $completed_tasks = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi' 
            AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $data['completion_rate'] = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
        $data['completed_tasks'] = $completed_tasks;
        
        $data['active_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif'
        ", $user_id));
        
        $data['overdue_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif' 
            AND hedef_bitis_tarihi < CURDATE()
        ", $user_id));
        
        return $data;
    }
    
    private function get_user_recent_activities($user_id, $limit = 10) {
        global $wpdb;
        
        // Bu basit bir örnek, gerçek uygulamada aktivite log tablosu olmalı
        $activities = array();
        
        // Son görev güncellemeleri
        $recent_tasks = $wpdb->get_results($wpdb->prepare("
            SELECT *, 'task' as type, 'Görev güncellendi' as description
            FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d 
            ORDER BY guncelleme_tarihi DESC 
            LIMIT %d
        ", $user_id, $limit));
        
        foreach($recent_tasks as $task) {
            $activity = new stdClass();
            $activity->type = 'task';
            $activity->description = wp_trim_words($task->gorev_icerigi, 6) . ' görevi güncellendi';
            $activity->created_at = $task->guncelleme_tarihi;
            $activities[] = $activity;
        }
        
        return $activities;
    }
    
    private function calculate_user_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Aksiyon istatistikleri
        $stats['total_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu)
        ", $user_id, $user_id));
        
        $stats['completed_actions'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar 
            WHERE (aksiyonu_tanimlayan = %d OR FIND_IN_SET(%d, aksiyon_sorumlusu))
            AND kapanma_tarihi IS NOT NULL AND kapanma_tarihi != ''
        ", $user_id, $user_id));
        
        $stats['pending_actions'] = $stats['total_actions'] - $stats['completed_actions'];
        
        // Görev istatistikleri
        $stats['total_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler WHERE sorumlu_kisi = %d
        ", $user_id));
        
        $stats['completed_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi'
        ", $user_id));
        
        $stats['active_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif'
        ", $user_id));
        
        $stats['overdue_tasks'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'aktif' 
            AND hedef_bitis_tarihi < CURDATE()
        ", $user_id));
        
        // Bu ay tamamlanma oranı
        $monthly_total = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $monthly_completed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi' 
            AND MONTH(olusturma_tarihi) = MONTH(NOW())
        ", $user_id));
        
        $stats['monthly_completion_rate'] = $monthly_total > 0 ? round(($monthly_completed / $monthly_total) * 100) : 0;
        
        // Ortalama tamamlama süresi (gün)
        $avg_completion = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(DATEDIFF(gercek_bitis_tarihi, baslangic_tarihi)) 
            FROM {$wpdb->prefix}bkm_gorevler 
            WHERE sorumlu_kisi = %d AND durum = 'tamamlandi' 
            AND gercek_bitis_tarihi IS NOT NULL
        ", $user_id));
        
        $stats['avg_completion_days'] = $avg_completion ? round($avg_completion, 1) : 0;
        
        return $stats;
    }
    
    private function get_user_activities($user_id, $limit = 10) {
        $activities = get_user_meta($user_id, 'bkm_activities', true) ?: array();
        return array_slice($activities, 0, $limit);
    }
    
    private function create_export_file($user_id, $export_type, $data_range) {
        // Export dosyası oluşturma (CSV örneği)
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/bkm-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'bkm-export-' . $user_id . '-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $export_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            return false;
        }
        
        // CSV başlıkları
        fputcsv($file, array(
            'Tip', 'Sıra No', 'Başlık', 'Durum', 'Açılma Tarihi', 
            'Hedef Tarih', 'Tamamlama Tarihi', 'İlerleme (%)', 'Kategori'
        ));
        
        // Kullanıcı verilerini al ve yazdır
        $aksiyonlar = $this->get_user_actions($user_id);
        foreach ($aksiyonlar as $aksiyon) {
            fputcsv($file, array(
                'Aksiyon',
                $aksiyon->sira_no,
                wp_trim_words($aksiyon->aksiyon_aciklamasi, 10),
                empty($aksiyon->kapanma_tarihi) ? 'Açık' : 'Kapalı',
                $aksiyon->acilma_tarihi,
                $aksiyon->hedef_tarih,
                $aksiyon->kapanma_tarihi,
                $aksiyon->ilerleme_durumu,
                $aksiyon->kategori_adi
            ));
        }
        
        $gorevler = $this->get_user_tasks($user_id);
        foreach ($gorevler as $gorev) {
            fputcsv($file, array(
                'Görev',
                '',
                wp_trim_words($gorev->gorev_icerigi, 10),
                $gorev->durum === 'tamamlandi' ? 'Tamamlandı' : 'Aktif',
                $gorev->baslangic_tarihi,
                $gorev->hedef_bitis_tarihi,
                $gorev->gercek_bitis_tarihi,
                $gorev->ilerleme_durumu,
                ''
            ));
        }
        
        fclose($file);
        
        // Dosya URL'sini döndür
        $file_url = $upload_dir['baseurl'] . '/bkm-exports/' . $filename;
        
        // 24 saat sonra dosyayı sil
        wp_schedule_single_event(time() + (24 * HOUR_IN_SECONDS), 'bkm_delete_export_file', array($filepath));
        
        return $file_url;
    }
    
    // Kullanıcı aktivitesi log'lama
    private function log_user_activity($user_id, $action, $description) {
        global $wpdb;
        
        // User activities tablosu mevcut mu kontrol et
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bkm_user_activities'");
        
        if ($table_exists) {
            $wpdb->insert(
                $wpdb->prefix . 'bkm_user_activities',
                array(
                    'user_id' => $user_id,
                    'action' => $action,
                    'description' => $description,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        } else {
            // Fallback olarak user meta kullan
            $activities = get_user_meta($user_id, 'bkm_activities', true) ?: array();
            
            $activity = array(
                'id' => uniqid(),
                'type' => $action,
                'description' => $description,
                'created_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            array_unshift($activities, $activity);
            
            // Son 100 aktiviteyi tut
            $activities = array_slice($activities, 0, 100);
            
            update_user_meta($user_id, 'bkm_activities', $activities);
        }
    }
    
    private function add_activity_log($user_id, $type, $description) {
        $this->log_user_activity($user_id, $type, $description);
    }
}