jQuery(document).ready(function($) {
    'use strict';

    // BKM Frontend Ana Sınıfı
    window.BKM_Frontend = {
        // Başlatma
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initFilters();
            this.initProgressSliders();
            this.initCircularProgress();
            this.updateTimeDisplays();
            this.autoSave();
        },

        // Event Listener'ları bağlama
        bindEvents: function() {
            // Login formu
            $(document).on('submit', '#bkm-frontend-login-form', this.handleLogin);
            
            // Tab geçişleri
            $(document).on('click', '.bkm-tab-button', this.switchTab);
            
            // Görev ekleme formu
            $(document).on('submit', '#bkm-add-task-form', this.handleAddTask);
            
            // Görev işlemleri
            $(document).on('click', '.complete-task', this.completeTask);
            $(document).on('click', '.edit-task', this.editTask);
            $(document).on('click', '.delete-task', this.deleteTask);
            
            // Aksiyon işlemleri
            $(document).on('click', '.view-aksiyon-details', this.viewAksiyonDetails);
            $(document).on('click', '.edit-progress', this.editProgress);
            
            // Progress slider değişimi
            $(document).on('input', '.progress-slider', this.updateProgressSlider);
            $(document).on('input', '.task-progress-slider', this.updateTaskProgress);
            $(document).on('input', '#ilerleme_durumu', this.updateFormProgress);
            
            // Filtreleme ve arama
            $(document).on('change', '#aksiyon-kategori-filter, #aksiyon-durum-filter', this.filterAksiyonlar);
            $(document).on('keyup', '#aksiyon-search', this.searchAksiyonlar);
            $(document).on('change', '#gorev-durum-filter, #gorev-siralama', this.filterGorevler);
            
            // Data refresh
            $(document).on('click', '.refresh-data', this.refreshData);
            
            // Form temizleme
            $(document).on('click', 'button[type="reset"]', this.resetForm);
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts);
            
            // Auto-save için input değişiklikleri
            $(document).on('input change', '.auto-save', this.debounceAutoSave);
        },

        // Login işlemi
        handleLogin: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $message = $('#bkm-login-message');
            
            // Loading state
            $submitBtn.addClass('loading');
            $message.removeClass('bkm-message success error').empty();
            
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=bkm_user_login&nonce=' + bkm_frontend_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        $message.addClass('bkm-message success').text(response.data.message);
                        
                        // Redirect veya sayfa yenileme
                        setTimeout(() => {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                location.reload();
                            }
                        }, 1500);
                    } else {
                        $message.addClass('bkm-message error').text(response.data.message);
                        $form.find('input[type="password"]').val('');
                    }
                },
                error: function() {
                    $message.addClass('bkm-message error').text(bkm_frontend_ajax.strings.error);
                },
                complete: function() {
                    $submitBtn.removeClass('loading');
                }
            });
        },

        // Şifre görünürlüğü toggle
        togglePassword: function() {
            const $passwordInput = $('#bkm_password');
            const $toggle = $('.password-toggle .bkm-icon');
            
            if ($passwordInput.attr('type') === 'password') {
                $passwordInput.attr('type', 'text');
                $toggle.removeClass('bkm-eye').addClass('bkm-eye-slash');
            } else {
                $passwordInput.attr('type', 'password');
                $toggle.removeClass('bkm-eye-slash').addClass('bkm-eye');
            }
        },

        // Tab sistemi
        initTabs: function() {
            const activeTab = localStorage.getItem('bkm_active_tab') || 'aksiyonlar';
            this.switchToTab(activeTab);
        },

        switchTab: function(e) {
            if (e) e.preventDefault();
            
            const tabId = $(this).data('tab');
            BKM_Frontend.switchToTab(tabId);
            
            // Tab'ı localStorage'a kaydet
            localStorage.setItem('bkm_active_tab', tabId);
        },

        switchToTab: function(tabId) {
            // Tüm tab'ları gizle
            $('.bkm-tab-content').removeClass('active');
            $('.bkm-tab-button').removeClass('active');
            
            // Seçili tab'ı göster
            $('#' + tabId + '-tab').addClass('active');
            $('.bkm-tab-button[data-tab="' + tabId + '"]').addClass('active');
            
            // Tab özel işlemleri
            if (tabId === 'raporlarim') {
                this.initCircularProgress();
            }
        },

        // Filtreleme sistemi
        initFilters: function() {
            // URL'den filtreleri al
            const urlParams = new URLSearchParams(window.location.search);
            const kategori = urlParams.get('kategori');
            const durum = urlParams.get('durum');
            
            if (kategori) $('#aksiyon-kategori-filter').val(kategori);
            if (durum) $('#aksiyon-durum-filter').val(durum);
            
            // Filtreleri uygula
            this.filterAksiyonlar();
        },

        filterAksiyonlar: function() {
            const kategoriFilter = $('#aksiyon-kategori-filter').val();
            const durumFilter = $('#aksiyon-durum-filter').val();
            const searchTerm = $('#aksiyon-search').val().toLowerCase();
            
            let visibleCount = 0;
            
            $('#user-aksiyonlar-table tbody tr').each(function() {
                const $row = $(this);
                let showRow = true;
                
                // Kategori filtresi
                if (kategoriFilter && $row.data('kategori') != kategoriFilter) {
                    showRow = false;
                }
                
                // Durum filtresi
                if (durumFilter && $row.data('durum') !== durumFilter) {
                    showRow = false;
                }
                
                // Arama filtresi
                if (searchTerm && !$row.text().toLowerCase().includes(searchTerm)) {
                    showRow = false;
                }
                
                if (showRow) {
                    $row.show().addClass('fade-in');
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });
            
            // Sonuç sayısını güncelle
            this.updateResultCount('aksiyonlar', visibleCount);
        },

        searchAksiyonlar: function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                BKM_Frontend.filterAksiyonlar();
            }, 300);
        },

        filterGorevler: function() {
            const durumFilter = $('#gorev-durum-filter').val();
            const siralama = $('#gorev-siralama').val();
            
            let visibleCards = $('.task-card');
            
            // Durum filtresi
            if (durumFilter) {
                visibleCards = visibleCards.filter('[data-durum="' + durumFilter + '"]');
            }
            
            // Tümünü gizle, sadece filtrelenenleri göster
            $('.task-card').hide();
            visibleCards.show().addClass('fade-in');
            
            // Sıralama
            if (siralama) {
                visibleCards = this.sortTaskCards(visibleCards, siralama);
            }
            
            this.updateResultCount('gorevler', visibleCards.length);
        },

        sortTaskCards: function($cards, sortBy) {
            const $container = $('.bkm-tasks-grid');
            
            $cards.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                
                switch(sortBy) {
                    case 'hedef_bitis_tarihi':
                        return new Date($a.data('hedef-tarih')) - new Date($b.data('hedef-tarih'));
                    case 'olusturma_tarihi':
                        return new Date($b.data('olusturma-tarihi')) - new Date($a.data('olusturma-tarihi'));
                    case 'ilerleme_durumu':
                        return $b.data('ilerleme') - $a.data('ilerleme');
                    default:
                        return 0;
                }
            });
            
            $container.append($cards);
            return $cards;
        },

        updateResultCount: function(type, count) {
            const $header = $('.bkm-content-header h3');
            const text = $header.text().split('(')[0].trim();
            $header.text(text + ' (' + count + ')');
        },

        // Progress slider'ları
        initProgressSliders: function() {
            $('.progress-slider').each(function() {
                const $slider = $(this);
                const value = $slider.val();
                const $progressBar = $slider.closest('.progress-wrapper').find('.progress-fill');
                const $progressText = $slider.closest('.progress-wrapper').find('.progress-text');
                
                $progressBar.css('width', value + '%');
                $progressText.text(value + '%');
            });
        },

        updateProgressSlider: function() {
            const $slider = $(this);
            const value = $slider.val();
            const $wrapper = $slider.closest('.progress-wrapper');
            const $progressBar = $wrapper.find('.progress-fill');
            const $progressText = $wrapper.find('.progress-text');
            
            $progressBar.css('width', value + '%');
            $progressText.text(value + '%');
            
            // Auto-save progress
            clearTimeout(this.progressTimeout);
            this.progressTimeout = setTimeout(() => {
                BKM_Frontend.saveAksiyonProgress($slider.data('aksiyon-id'), value);
            }, 1000);
        },

        updateTaskProgress: function() {
            const $slider = $(this);
            const value = $slider.val();
            const $card = $slider.closest('.task-card');
            const $progressBar = $card.find('.progress-fill');
            const $progressText = $card.find('.progress-info span:last-child');
            
            $progressBar.css('width', value + '%');
            $progressText.text(value + '%');
            
            // Auto-save task progress
            clearTimeout(this.taskProgressTimeout);
            this.taskProgressTimeout = setTimeout(() => {
                BKM_Frontend.saveTaskProgress($slider.data('task-id'), value);
            }, 1000);
        },

        updateFormProgress: function() {
            const value = $(this).val();
            const $wrapper = $(this).closest('.progress-input-wrapper');
            const $preview = $wrapper.siblings('.progress-preview');
            
            $wrapper.find('.progress-value').text(value + '%');
            $preview.find('.progress-fill').css('width', value + '%');
        },

        // Circular Progress
        initCircularProgress: function() {
            $('.circle-progress').each(function() {
                const $circle = $(this);
                const percentage = $circle.data('percentage') || 0;
                const degrees = (percentage / 100) * 360;
                
                $circle.css('--percentage', degrees + 'deg');
                
                // Animasyon
                $circle.find('span').text('0%');
                $({ percentage: 0 }).animate({ percentage: percentage }, {
                    duration: 1500,
                    easing: 'swing',
                    step: function(now) {
                        $circle.find('span').text(Math.round(now) + '%');
                        $circle.css('--percentage', (now / 100) * 360 + 'deg');
                    }
                });
            });
        },

        // Görev ekleme
        handleAddTask: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Validasyon
            if (!BKM_Frontend.validateTaskForm($form)) {
                return;
            }
            
            // Loading state
            $submitBtn.addClass('loading');
            
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=bkm_add_task&nonce=' + bkm_frontend_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        BKM_Frontend.showMessage(response.data.message, 'success');
                        $form[0].reset();
                        BKM_Frontend.updateFormProgress.call($form.find('#ilerleme_durumu')[0]);
                        
                        // Görevler tab'ına geç ve yenile
                        setTimeout(() => {
                            BKM_Frontend.switchToTab('gorevler');
                            BKM_Frontend.refreshData();
                        }, 1500);
                    } else {
                        BKM_Frontend.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    BKM_Frontend.showMessage(bkm_frontend_ajax.strings.error, 'error');
                },
                complete: function() {
                    $submitBtn.removeClass('loading');
                }
            });
        },

        validateTaskForm: function($form) {
            let isValid = true;
            
            // Required field kontrolü
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Tarih kontrolü
            const startDate = new Date($form.find('#baslangic_tarihi').val());
            const endDate = new Date($form.find('#hedef_bitis_tarihi').val());
            
            if (endDate <= startDate) {
                BKM_Frontend.showMessage('Hedef bitiş tarihi başlangıç tarihinden sonra olmalıdır!', 'error');
                isValid = false;
            }
            
            if (!isValid) {
                BKM_Frontend.showMessage('Lütfen tüm zorunlu alanları doldurunuz!', 'error');
            }
            
            return isValid;
        },

        // Görev tamamlama
        completeTask: function(e) {
            e.preventDefault();
            
            if (!confirm(bkm_frontend_ajax.strings.confirm_complete)) {
                return;
            }
            
            const taskId = $(this).data('id');
            const $card = $(this).closest('.task-card');
            
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_complete_task',
                    task_id: taskId,
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.addClass('completed');
                        $card.find('.task-status i').removeClass('bkm-clock warning').addClass('bkm-check-circle success');
                        $card.find('.complete-task').remove();
                        $card.find('.task-progress-slider').prop('disabled', true);
                        
                        // Progress'i %100 yap
                        $card.find('.progress-fill').css('width', '100%');
                        $card.find('.progress-info span:last-child').text('100%');
                        
                        BKM_Frontend.showMessage(response.data.message, 'success');
                        
                        // İstatistikleri güncelle
                        BKM_Frontend.updateStats();
                    } else {
                        BKM_Frontend.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    BKM_Frontend.showMessage(bkm_frontend_ajax.strings.error, 'error');
                }
            });
        },

        // Görev düzenleme
        editTask: function(e) {
            e.preventDefault();
            
            const taskId = $(this).data('id');
            const $card = $(this).closest('.task-card');
            
            // Inline editing modal'ı açabilir veya yeni sayfaya yönlendirebiliriz
            BKM_Frontend.openTaskEditModal(taskId, $card);
        },

        openTaskEditModal: function(taskId, $card) {
            // Bu fonksiyon task editing modal'ını açar
            // Şimdilik basit bir prompt ile yapalım
            const currentContent = $card.find('h4').text();
            const newContent = prompt('Görev içeriğini düzenleyin:', currentContent);
            
            if (newContent && newContent !== currentContent) {
                $.ajax({
                    url: bkm_frontend_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bkm_update_task',
                        task_id: taskId,
                        gorev_icerigi: newContent,
                        nonce: bkm_frontend_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.find('h4').text(newContent);
                            BKM_Frontend.showMessage(response.data.message, 'success');
                        } else {
                            BKM_Frontend.showMessage(response.data.message, 'error');
                        }
                    }
                });
            }
        },

        // Görev silme
        deleteTask: function(e) {
            e.preventDefault();
            
            if (!confirm(bkm_frontend_ajax.strings.confirm_delete)) {
                return;
            }
            
            const taskId = $(this).data('id');
            const $card = $(this).closest('.task-card');
            
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_delete_task',
                    task_id: taskId,
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            BKM_Frontend.updateStats();
                        });
                        BKM_Frontend.showMessage(response.data.message, 'success');
                    } else {
                        BKM_Frontend.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    BKM_Frontend.showMessage(bkm_frontend_ajax.strings.error, 'error');
                }
            });
        },

        // Aksiyon detayları görüntüleme
        viewAksiyonDetails: function(e) {
            e.preventDefault();
            
            const aksiyonId = $(this).data('id');
            BKM_Frontend.openAksiyonModal(aksiyonId);
        },

        openAksiyonModal: function(aksiyonId) {
            // Modal oluştur veya mevcut olanı kullan
            let $modal = $('#bkm-aksiyon-modal');
            
            if ($modal.length === 0) {
                $modal = $(`
                    <div id="bkm-aksiyon-modal" class="bkm-modal">
                        <div class="bkm-modal-content">
                            <span class="bkm-modal-close">&times;</span>
                            <div class="bkm-modal-header">
                                <h3>Aksiyon Detayları</h3>
                            </div>
                            <div class="bkm-modal-body">
                                <div class="loading-spinner">
                                    <div class="bkm-spinner"></div>
                                    <p>Yükleniyor...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `).appendTo('body');
                
                // Modal kapatma
                $modal.on('click', '.bkm-modal-close, .bkm-modal', function(e) {
                    if (e.target === this) {
                        $modal.fadeOut(300);
                    }
                });
            }
            
            $modal.fadeIn(300);
            
            // Aksiyon verilerini al
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_get_aksiyon_details',
                    aksiyon_id: aksiyonId,
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $modal.find('.bkm-modal-body').html(response.data.html);
                    } else {
                        $modal.find('.bkm-modal-body').html('<p>Detaylar yüklenemedi.</p>');
                    }
                },
                error: function() {
                    $modal.find('.bkm-modal-body').html('<p>Bir hata oluştu.</p>');
                }
            });
        },

        // Progress düzenleme
        editProgress: function(e) {
            e.preventDefault();
            
            const aksiyonId = $(this).data('id');
            const $row = $(this).closest('tr');
            const $progressWrapper = $row.find('.progress-wrapper');
            const $slider = $progressWrapper.find('.progress-slider');
            
            if ($slider.is(':visible')) {
                $slider.hide();
                $(this).html('<i class="bkm-icon bkm-edit"></i>');
            } else {
                $slider.show().focus();
                $(this).html('<i class="bkm-icon bkm-check"></i>');
            }
        },

        // Progress kaydetme
        saveAksiyonProgress: function(aksiyonId, progress) {
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_update_aksiyon_progress',
                    aksiyon_id: aksiyonId,
                    progress: progress,
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BKM_Frontend.showMessage('İlerleme güncellendi!', 'success', 2000);
                    }
                }
            });
        },

        saveTaskProgress: function(taskId, progress) {
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_update_task_progress',
                    task_id: taskId,
                    progress: progress,
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BKM_Frontend.showMessage('Görev ilerlemesi güncellendi!', 'success', 2000);
                    }
                }
            });
        },

        // Data yenileme
        refreshData: function() {
            const $btn = $('.refresh-data');
            $btn.addClass('loading');
            
            // Sayfayı yenile (daha sofistike bir AJAX reload da yapılabilir)
            setTimeout(() => {
                location.reload();
            }, 500);
        },

        // İstatistikleri güncelle
        updateStats: function() {
            $.ajax({
                url: bkm_frontend_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_get_user_stats',
                    nonce: bkm_frontend_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        
                        $('.stat-card').each(function() {
                            const $card = $(this);
                            const $content = $card.find('.stat-content h3');
                            const type = $card.find('.stat-icon').hasClass('primary') ? 'total' :
                                        $card.find('.stat-icon').hasClass('success') ? 'completed' :
                                        $card.find('.stat-icon').hasClass('warning') ? 'pending' : 'overdue';
                            
                            if (stats[type + '_actions'] !== undefined) {
                                $content.text(stats[type + '_actions']);
                            } else if (stats[type + '_tasks'] !== undefined) {
                                $content.text(stats[type + '_tasks']);
                            }
                        });
                    }
                }
            });
        },

        // Form sıfırlama
        resetForm: function(e) {
            const $form = $(this).closest('form');
            
            // Progress slider'ı sıfırla
            $form.find('#ilerleme_durumu').val(0);
            BKM_Frontend.updateFormProgress.call($form.find('#ilerleme_durumu')[0]);
            
            // Hata sınıflarını kaldır
            $form.find('.error').removeClass('error');
            
            // Mesajları temizle
            $('.bkm-message').fadeOut(300);
        },

        // Keyboard shortcuts
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + Enter: Form submit
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                const $activeForm = $('form:visible');
                if ($activeForm.length) {
                    $activeForm.submit();
                }
            }
            
            // Esc: Modal'ları kapat
            if (e.keyCode === 27) {
                $('.bkm-modal:visible').fadeOut(300);
            }
            
            // Tab shortcuts (Alt + 1,2,3,4)
            if (e.altKey && e.keyCode >= 49 && e.keyCode <= 52) {
                e.preventDefault();
                const tabIndex = e.keyCode - 49;
                const $tabButton = $('.bkm-tab-button').eq(tabIndex);
                if ($tabButton.length) {
                    $tabButton.click();
                }
            }
        },

        // Auto-save
        autoSave: function() {
            // Her 30 saniyede bir draft kaydet
            setInterval(() => {
                const $activeForm = $('#bkm-add-task-form:visible');
                if ($activeForm.length && $activeForm.find('textarea').val().trim()) {
                    BKM_Frontend.saveDraft($activeForm);
                }
            }, 30000);
        },

        debounceAutoSave: function() {
            clearTimeout(this.autoSaveTimeout);
            this.autoSaveTimeout = setTimeout(() => {
                const $form = $(this).closest('form');
                BKM_Frontend.saveDraft($form);
            }, 2000);
        },

        saveDraft: function($form) {
            const formData = $form.serialize();
            localStorage.setItem('bkm_task_draft', formData);
            
            // Küçük bildirim göster
            this.showMessage('Taslak kaydedildi', 'info', 1000);
        },

        loadDraft: function() {
            const draft = localStorage.getItem('bkm_task_draft');
            if (draft) {
                const $form = $('#bkm-add-task-form');
                if ($form.length && confirm('Kaydedilmiş taslak bulundu. Yüklemek ister misiniz?')) {
                    // Form alanlarını doldur
                    $.each(draft.split('&'), function() {
                        const pair = this.split('=');
                        const name = decodeURIComponent(pair[0]);
                        const value = decodeURIComponent(pair[1] || '');
                        $form.find('[name="' + name + '"]').val(value);
                    });
                    
                    // Progress bar'ı güncelle
                    const progressValue = $form.find('#ilerleme_durumu').val();
                    if (progressValue) {
                        BKM_Frontend.updateFormProgress.call($form.find('#ilerleme_durumu')[0]);
                    }
                }
            }
        },

        // Zaman gösterimlerini güncelle
        updateTimeDisplays: function() {
            $('[data-time]').each(function() {
                const $el = $(this);
                const time = $el.data('time');
                const humanTime = BKM_Frontend.humanTimeDate(time);
                $el.text(humanTime);
            });
            
            // Her dakika güncelle
            setTimeout(() => {
                BKM_Frontend.updateTimeDisplays();
            }, 60000);
        },

        humanTimeDate: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = (now - date) / 1000; // saniye cinsinden
            
            if (diff < 60) return 'Az önce';
            if (diff < 3600) return Math.floor(diff / 60) + ' dakika önce';
            if (diff < 86400) return Math.floor(diff / 3600) + ' saat önce';
            if (diff < 2592000) return Math.floor(diff / 86400) + ' gün önce';
            
            return date.toLocaleDateString('tr-TR');
        },

        // Mesaj gösterme sistemi
        showMessage: function(message, type = 'info', duration = 5000) {
            // Eski mesajları kaldır
            $('.bkm-message').remove();
            
            const $message = $(`
                <div class="bkm-message ${type}">
                    <i class="bkm-icon bkm-${type === 'success' ? 'check' : type === 'error' ? 'warning' : 'info'}"></i>
                    <span>${message}</span>
                </div>
            `);
            
            // En üste ekle
            $('#bkm-frontend-container').prepend($message);
            
            // Animasyon
            $message.hide().slideDown(300);
            
            // Otomatik kaldırma
            if (duration > 0) {
                setTimeout(() => {
                    $message.slideUp(300, function() {
                        $(this).remove();
                    });
                }, duration);
            }
            
            return $message;
        },

        // Loading overlay
        showLoading: function($element) {
            $element.addClass('bkm-loading');
        },

        hideLoading: function($element) {
            $element.removeClass('bkm-loading');
        },

        // Utility fonksiyonlar
        formatDate: function(date) {
            return new Date(date).toLocaleDateString('tr-TR');
        },

        formatTime: function(date) {
            return new Date(date).toLocaleTimeString('tr-TR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        isToday: function(date) {
            const today = new Date();
            const checkDate = new Date(date);
            return today.toDateString() === checkDate.toDateString();
        },

        isOverdue: function(date) {
            return new Date(date) < new Date();
        },

        // Responsive helper
        isMobile: function() {
            return window.innerWidth <= 768;
        },

        // Local Storage helper
        setStorage: function(key, value) {
            try {
                localStorage.setItem('bkm_' + key, JSON.stringify(value));
            } catch(e) {
                console.warn('localStorage not available');
            }
        },

        getStorage: function(key, defaultValue = null) {
            try {
                const value = localStorage.getItem('bkm_' + key);
                return value ? JSON.parse(value) : defaultValue;
            } catch(e) {
                return defaultValue;
            }
        },

        // Performance monitoring
        measurePerformance: function(name, fn) {
            const start = performance.now();
            const result = fn();
            const end = performance.now();
            console.log(`${name} took ${end - start} milliseconds`);
            return result;
        }
    };

    // Sayfa yüklendiğinde başlat
    BKM_Frontend.init();

    // Draft yükleme (sayfa tamamen yüklendikten sonra)
    $(window).on('load', function() {
        BKM_Frontend.loadDraft();
    });

    // Sayfa kapatılırken draft kaydet
    $(window).on('beforeunload', function() {
        const $form = $('#bkm-add-task-form:visible');
        if ($form.length && $form.find('textarea').val().trim()) {
            BKM_Frontend.saveDraft($form);
        }
    });

    // Resize olayları
    $(window).on('resize', debounce(function() {
        // Responsive davranışları buraya ekleyebiliriz
        if (BKM_Frontend.isMobile()) {
            $('.bkm-tab-nav').addClass('mobile');
        } else {
            $('.bkm-tab-nav').removeClass('mobile');
        }
    }, 250));

    // Service Worker kaydı (PWA için)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function() {
            // Service worker yok, sorun değil
        });
    }

    // Debounce utility function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('BKM Frontend Error:', e.error);
        BKM_Frontend.showMessage('Beklenmeyen bir hata oluştu!', 'error');
    });

    // AJAX error handler
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (xhr.status === 403) {
            BKM_Frontend.showMessage('Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.', 'error');
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    });
});

// CSS animasyonu ekleme
const style = document.createElement('style');
style.textContent = `
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .bkm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }
    
    .bkm-modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .bkm-modal-header {
        padding: 20px 30px;
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }
    
    .bkm-modal-body {
        padding: 30px;
    }
    
    .bkm-modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: #999;
        z-index: 1;
    }
    
    .bkm-modal-close:hover {
        color: #000;
    }
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
    }
    
    .loading-spinner .bkm-spinner {
        width: 40px;
        height: 40px;
        border-width: 4px;
        margin: 0 auto 20px;
    }
`;
document.head.appendChild(style);