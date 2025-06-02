jQuery(document).ready(function($) {
    'use strict';

    // Global değişkenler
    const BKM_Admin = {
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.initMultiSelect();
            this.initTableSorting();
            this.initCharts();
            this.updateCircularProgress();
        },

        bindEvents: function() {
            // Form submit olayları
            $(document).on('submit', '#bkm-aksiyon-form', this.handleAksiyonForm);
            $(document).on('submit', '#bkm-kategori-form', this.handleKategoriForm);
            $(document).on('submit', '#bkm-performans-form', this.handlePerformansForm);

            // Silme işlemleri
            $(document).on('click', '.bkm-delete-aksiyon', this.deleteAksiyon);
            $(document).on('click', '.bkm-delete-kategori', this.deleteKategori);
            $(document).on('click', '.bkm-delete-performans', this.deletePerformans);

            // Düzenleme işlemleri
            $(document).on('click', '.bkm-edit-kategori', this.editKategori);
            $(document).on('click', '.bkm-edit-performans', this.editPerformans);

            // Modal işlemleri
            $(document).on('click', '.bkm-view-details', this.viewAksiyonDetails);
            $(document).on('click', '.bkm-modal-close', this.closeModal);
            $(document).on('click', '.bkm-modal', function(e) {
                if (e.target === this) {
                    BKM_Admin.closeModal();
                }
            });

            // Filtreleme
            $(document).on('change', '#kategori-filter, #durum-filter', this.filterTable);
            $(document).on('keyup', '#arama-input', this.searchTable);

            // İlerleme durumu slider
            $(document).on('input', '#ilerleme_durumu', this.updateProgressDisplay);

            // ESC tuşu ile modal kapatma
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    BKM_Admin.closeModal();
                }
            });
        },

        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.bkm-datepicker, input[type="date"]').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: '-10:+10'
                });
            }
        },

        initMultiSelect: function() {
            $('.bkm-multiselect').each(function() {
                const $select = $(this);
                if ($select.prop('multiple')) {
                    // Basit multi-select stillemesi
                    $select.css({
                        'height': 'auto',
                        'min-height': '100px'
                    });
                }
            });
        },

        initTableSorting: function() {
            $('.sortable').on('click', function() {
                const $th = $(this);
                const $table = $th.closest('table');
                const column = $th.data('column');
                const $tbody = $table.find('tbody');
                const rows = $tbody.find('tr').toArray();

                // Sıralama yönünü belirle
                const isAsc = $th.hasClass('sorted-asc');
                $table.find('th').removeClass('sorted-asc sorted-desc');
                $th.addClass(isAsc ? 'sorted-desc' : 'sorted-asc');

                // Satırları sırala
                rows.sort(function(a, b) {
                    const aVal = $(a).find(`[data-sort="${column}"]`).text() || 
                                $(a).find('td').eq($th.index()).text();
                    const bVal = $(b).find(`[data-sort="${column}"]`).text() || 
                                $(b).find('td').eq($th.index()).text();

                    if ($.isNumeric(aVal) && $.isNumeric(bVal)) {
                        return isAsc ? bVal - aVal : aVal - bVal;
                    } else {
                        return isAsc ? bVal.localeCompare(aVal) : aVal.localeCompare(bVal);
                    }
                });

                $tbody.html(rows);
            });
        },

        initCharts: function() {
            // Chart.js kullanımı (CDN'den yüklenecek)
            if (typeof Chart !== 'undefined') {
                this.createKategoriChart();
                this.createOnemChart();
                this.createTrendChart();
            }
        },

        createKategoriChart: function() {
            const ctx = document.getElementById('kategoriChart');
            if (ctx && typeof kategoriData !== 'undefined') {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: kategoriData,
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },

        createOnemChart: function() {
            const ctx = document.getElementById('onemChart');
            if (ctx && typeof onemData !== 'undefined') {
                new Chart(ctx, {
                    type: 'bar',
                    data: onemData,
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        },

        createTrendChart: function() {
            const ctx = document.getElementById('trendChart');
            if (ctx) {
                // Trend verilerini AJAX ile al
                $.ajax({
                    url: bkm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bkm_get_trend_data',
                        nonce: bkm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            new Chart(ctx, {
                                type: 'line',
                                data: response.data,
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        }
                    }
                });
            }
        },

        updateCircularProgress: function() {
            $('.circular-progress').each(function() {
                const $this = $(this);
                const percentage = $this.data('percentage') || 0;
                const degrees = (percentage / 100) * 360;
                
                $this.css('--percentage', degrees + 'deg');
            });
        },

        handleAksiyonForm: function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Loading state
            $submitBtn.addClass('bkm-loading').prop('disabled', true);
            
            $.ajax({
                url: bkm_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=bkm_save_aksiyon&nonce=' + bkm_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        BKM_Admin.showMessage('Aksiyon başarıyla kaydedildi!', 'success');
                        if (response.data.redirect) {
                            setTimeout(() => {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        BKM_Admin.showMessage(response.data.message || 'Bir hata oluştu!', 'error');
                    }
                },
                error: function() {
                    BKM_Admin.showMessage('Sunucu hatası oluştu!', 'error');
                },
                complete: function() {
                    $submitBtn.removeClass('bkm-loading').prop('disabled', false);
                }
            });
        },

        handleKategoriForm: function(e) {
            const $form = $(this);
            const action = $form.find('input[name="action"]').val();
            
            if (action === 'add') {
                e.preventDefault();
                BKM_Admin.submitForm($form, 'bkm_add_kategori');
            }
        },

        handlePerformansForm: function(e) {
            const $form = $(this);
            const action = $form.find('input[name="action"]').val();
            
            if (action === 'add') {
                e.preventDefault();
                BKM_Admin.submitForm($form, 'bkm_add_performans');
            }
        },

        submitForm: function($form, ajaxAction) {
            const $submitBtn = $form.find('button[type="submit"]');
            
            $submitBtn.addClass('bkm-loading').prop('disabled', true);
            
            $.ajax({
                url: bkm_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=' + ajaxAction + '&nonce=' + bkm_ajax.nonce,
                success: function(response) {
                    if (response.success) {
                        BKM_Admin.showMessage(response.data.message, 'success');
                        $form[0].reset();
                        // Tabloyu güncelle
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        BKM_Admin.showMessage(response.data.message || 'Bir hata oluştu!', 'error');
                    }
                },
                error: function() {
                    BKM_Admin.showMessage('Sunucu hatası oluştu!', 'error');
                },
                complete: function() {
                    $submitBtn.removeClass('bkm-loading').prop('disabled', false);
                }
            });
        },

        deleteAksiyon: function(e) {
            e.preventDefault();
            const aksiyonId = $(this).data('id');
            
            if (confirm('Bu aksiyonu silmek istediğinizden emin misiniz?')) {
                $.ajax({
                    url: bkm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bkm_delete_aksiyon',
                        aksiyon_id: aksiyonId,
                        nonce: bkm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`tr[data-id="${aksiyonId}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                            BKM_Admin.showMessage('Aksiyon başarıyla silindi!', 'success');
                        } else {
                            BKM_Admin.showMessage(response.data.message || 'Silme işlemi başarısız!', 'error');
                        }
                    },
                    error: function() {
                        BKM_Admin.showMessage('Sunucu hatası oluştu!', 'error');
                    }
                });
            }
        },

        deleteKategori: function(e) {
            e.preventDefault();
            const kategoriId = $(this).data('id');
            
            if (confirm('Bu kategoriyi silmek istediğinizden emin misiniz?')) {
                BKM_Admin.deleteItem('bkm_delete_kategori', kategoriId, 'Kategori');
            }
        },

        deletePerformans: function(e) {
            e.preventDefault();
            const performansId = $(this).data('id');
            
            if (confirm('Bu performans verisini silmek istediğinizden emin misiniz?')) {
                BKM_Admin.deleteItem('bkm_delete_performans', performansId, 'Performans verisi');
            }
        },

        deleteItem: function(action, itemId, itemType) {
            $.ajax({
                url: bkm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    item_id: itemId,
                    nonce: bkm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        BKM_Admin.showMessage(response.data.message || `${itemType} silme işlemi başarısız!`, 'error');
                    }
                },
                error: function() {
                    BKM_Admin.showMessage('Sunucu hatası oluştu!', 'error');
                }
            });
        },

        editKategori: function(e) {
            e.preventDefault();
            const $btn = $(this);
            
            $('#edit_kategori_id').val($btn.data('id'));
            $('#edit_kategori_adi').val($btn.data('name'));
            $('#edit_aciklama').val($btn.data('desc'));
            
            $('#bkm-edit-kategori-modal').fadeIn(300);
        },

        editPerformans: function(e) {
            e.preventDefault();
            const $btn = $(this);
            
            $('#edit_performans_id').val($btn.data('id'));
            $('#edit_performans_adi').val($btn.data('name'));
            $('#edit_aciklama').val($btn.data('desc'));
            
            $('#bkm-edit-performans-modal').fadeIn(300);
        },

        viewAksiyonDetails: function(e) {
            e.preventDefault();
            const aksiyonId = $(this).data('id');
            
            // Loading modal content
            $('#bkm-modal-body').html('<div class="bkm-loading">Yükleniyor...</div>');
            $('#bkm-detail-modal').fadeIn(300);
            
            $.ajax({
                url: bkm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_get_aksiyon_details',
                    aksiyon_id: aksiyonId,
                    nonce: bkm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#bkm-modal-body').html(response.data.html);
                    } else {
                        $('#bkm-modal-body').html('<p>Detaylar yüklenemedi.</p>');
                    }
                },
                error: function() {
                    $('#bkm-modal-body').html('<p>Sunucu hatası oluştu.</p>');
                }
            });
        },

        closeModal: function() {
            $('.bkm-modal').fadeOut(300);
        },

        filterTable: function() {
            const kategoriFilter = $('#kategori-filter').val();
            const durumFilter = $('#durum-filter').val();
            
            $('#bkm-aksiyonlar-table tbody tr').each(function() {
                const $row = $(this);
                let showRow = true;
                
                if (kategoriFilter && $row.data('kategori') != kategoriFilter) {
                    showRow = false;
                }
                
                if (durumFilter && $row.data('durum') !== durumFilter) {
                    showRow = false;
                }
                
                $row.toggle(showRow);
            });
        },

        searchTable: function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('#bkm-aksiyonlar-table tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                $row.toggle(text.includes(searchTerm));
            });
        },

        updateProgressDisplay: function() {
            const value = $(this).val();
            const $container = $(this).closest('.progress-input-container');
            
            $container.find('.progress-value').text(value + '%');
            $container.siblings('.progress-bar-preview').find('.progress-fill').css('width', value + '%');
            
            // %100 olduğunda kapanma tarihini otomatik doldur
            if (value == 100) {
                const today = new Date().toISOString().split('T')[0];
                const $kapanmaTarihi = $('#kapanma_tarihi');
                if (!$kapanmaTarihi.val()) {
                    $kapanmaTarihi.val(today);
                }
            }
        },

        showMessage: function(message, type) {
            const $messageDiv = $(`<div class="bkm-message ${type} bkm-fade-in">${message}</div>`);
            
            // Eski mesajları kaldır
            $('.bkm-message').remove();
            
            // Yeni mesajı ekle
            $('.wrap').prepend($messageDiv);
            
            // 5 saniye sonra kaldır
            setTimeout(() => {
                $messageDiv.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        // Sıra numarası otomatik hesaplama
        getNextSiraNo: function() {
            $.ajax({
                url: bkm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_get_next_sira_no',
                    nonce: bkm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#sira_no').val(response.data.next_sira_no);
                    }
                }
            });
        }
    };

    // Sayfa yüklendiğinde başlat
    BKM_Admin.init();

    // Chart.js CDN yüklemesi
    if (typeof Chart === 'undefined' && document.getElementById('kategoriChart')) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            BKM_Admin.initCharts();
        };
        document.head.appendChild(script);
    }
});