/**
 * BKM Aksiyon Takip - Utility Functions
 * Ortak kullanılan fonksiyonlar burada toplanmıştır
 */

// jQuery uyumluluk kontrolü
(function($) {
    'use strict';

// ===== GLOBAL UTILITY FONKSİYONLARI =====

/**
 * Show notification message to user
 * @param {string} message - Mesaj metni
 * @param {string} type - Mesaj tipi (success, error, info, warning)
 */
function showNotification(message, type) {
    // Check if we're in admin panel
    if (typeof wp !== 'undefined' && wp.ajax) {
        // Admin panel notification
        var notificationClass = type === 'error' ? 'notice-error' : 'notice-success';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    } else {
        // Frontend notification
        var notificationClass = type === 'error' ? 'error' : 'success';
        var notification = $('<div class="bkm-ajax-notification ' + notificationClass + '">' + 
                            '<span>' + message + '</span>' +
                            '<button class="close-btn" onclick="$(this).parent().removeClass(\'show\')">&times;</button>' +
                            '</div>');
        
        // Remove existing notifications
        $('.bkm-ajax-notification').remove();
        
        // Add to body
        $('body').append(notification);
        
        // Show with animation
        setTimeout(function() {
            notification.addClass('show');
        }, 100);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            notification.removeClass('show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 5000);
    }
}

/**
 * Escape HTML special characters
 * @param {string} text - Escape edilecek metin
 * @returns {string} - Escape edilmiş metin
 */
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.toString().replace(/[&<>"']/g, function(m) { 
        return map[m]; 
    });
}

/**
 * Escape JavaScript special characters
 * @param {string} text - Escape edilecek metin
 * @returns {string} - Escape edilmiş metin
 */
function escapeJs(text) {
    if (text === null || text === undefined) {
        return '';
    }
    
    return text.toString()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/\t/g, '\\t');
}

/**
 * Validate required form fields
 * @param {jQuery} form - Form elementi
 * @returns {Array} - Hata mesajları dizisi
 */
function validateForm(form) {
    var errors = [];
    
    form.find('[required]').each(function() {
        var field = $(this);
        var value = field.val();
        
        if (!value || value.trim() === '') {
            var fieldName = field.attr('name') || field.attr('id') || 'Bu alan';
            errors.push(fieldName);
        }
    });
    
    return errors;
}

/**
 * Clear form fields
 * @param {jQuery} form - Form elementi
 */
function clearForm(form) {
    form.find('input[type="text"], input[type="email"], input[type="password"], input[type="number"], textarea').val('');
    form.find('select').prop('selectedIndex', 0);
    form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
}

/**
 * Show loading state on form
 * @param {jQuery} form - Form elementi
 * @param {boolean} isLoading - Loading durumu
 */
function setFormLoading(form, isLoading) {
    if (isLoading) {
        form.addClass('loading');
        form.find('button[type="submit"]').prop('disabled', true);
    } else {
        form.removeClass('loading');
        form.find('button[type="submit"]').prop('disabled', false);
    }
}

/**
 * Make AJAX request with error handling
 * @param {Object} options - AJAX options
 * @returns {Promise} - AJAX promise
 */
function bkmAjax(options) {
    var defaultOptions = {
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        beforeSend: function() {
            if (options.form) {
                setFormLoading(options.form, true);
            }
        },
        success: function(response) {
            if (response.success) {
                if (options.successMessage) {
                    showNotification(options.successMessage, 'success');
                }
                if (options.onSuccess) {
                    options.onSuccess(response);
                }
            } else {
                var errorMsg = response.data || 'İşlem başarısız';
                showNotification('Hata: ' + errorMsg, 'error');
                if (options.onError) {
                    options.onError(response);
                }
            }
        },
        error: function(xhr, status, error) {
            var errorMsg = 'Bağlantı hatası: ' + error;
            showNotification(errorMsg, 'error');
            if (options.onError) {
                options.onError({xhr: xhr, status: status, error: error});
            }
        },
        complete: function() {
            if (options.form) {
                setFormLoading(options.form, false);
            }
            if (options.onComplete) {
                options.onComplete();
            }
        }
    };
    
    return $.ajax($.extend(defaultOptions, options));
}

/**
 * Check if WordPress AJAX is available
 * @returns {boolean} - AJAX hazır mı
 */
function isAjaxReady() {
    return typeof bkmFrontend !== 'undefined' && bkmFrontend.ajax_url;
}

/**
 * Get current user ID safely
 * @returns {number} - Kullanıcı ID'si
 */
function getCurrentUserId() {
    return typeof bkmFrontend !== 'undefined' ? bkmFrontend.current_user_id : 0;
}

/**
 * Get AJAX nonce safely
 * @returns {string} - Nonce değeri
 */
function getAjaxNonce() {
    return typeof bkmFrontend !== 'undefined' ? bkmFrontend.nonce : '';
}

// ===== FORM CLEAR FONKSİYONLARI =====

/**
 * Clear user form
 */
function clearUserForm() {
    $('#bkm-user-form-element')[0].reset();
    $('#bkm-user-form-element input[type="hidden"]').val('');
}

/**
 * Clear category form
 */
function clearCategoryForm() {
    $('#bkm-category-form-element')[0].reset();
    $('#bkm-category-form-element input[type="hidden"]').val('');
}

/**
 * Clear performance form
 */
function clearPerformanceForm() {
    $('#bkm-performance-form-element')[0].reset();
    $('#bkm-performance-form-element input[type="hidden"]').val('');
}

/**
 * Clear company form
 */
function clearCompanyForm() {
    $('#bkm-company-form-element')[0].reset();
    $('#bkm-company-form-element input[type="hidden"]').val('');
}

/**
 * Clear all settings forms
 */
function clearAllSettingsForms() {
    clearUserForm();
    clearCategoryForm();
    clearPerformanceForm();
    clearCompanyForm();
}

// ===== PANEL TOGGLE FONKSİYONLARI =====

/**
 * Toggle settings panel
 */
function toggleSettingsPanel() {
    try {
        console.log('🔧 toggleSettingsPanel fonksiyonu çağrıldı');
        
        var panel = $('#bkm-settings-panel');
        console.log('📋 Panel elementi bulundu:', panel.length > 0);
        
        if (panel.length === 0) {
            console.error('❌ HATA: bkm-settings-panel elementi bulunamadı!');
            showNotification('Ayarlar paneli elementi bulunamadı!', 'error');
            return;
        }
        
        var isVisible = panel.is(':visible');
        console.log('👁️ Panel görünür durumda:', isVisible);
        
        if (isVisible) {
            console.log('🔼 Panel kapatılıyor...');
            clearAllSettingsForms();
            panel.slideUp();
        } else {
            console.log('🔽 Panel açılıyor...');
            $('#bkm-action-form, #bkm-task-form').slideUp();
            clearAllSettingsForms();
            panel.slideDown();
            
            if (!panel.find('.settings-tab.active').length) {
                console.log('🏷️ İlk tab aktif ediliyor...');
                switchSettingsTab('users');
            }
        }
    } catch (error) {
        console.error('❌ toggleSettingsPanel hatası:', error);
        showNotification('HATA: ' + error.message, 'error');
    }
}

/**
 * Toggle filter panel
 */
function toggleFilterPanel() {
    try {
        console.log('🔍 toggleFilterPanel fonksiyonu çağrıldı');
        var panel = $('#bkm-filter-panel');
        if (panel.length === 0) {
            console.error('❌ HATA: bkm-filter-panel elementi bulunamadı!');
            showNotification('Filtre paneli elementi bulunamadı!', 'error');
            return;
        }
        var isVisible = panel.is(':visible');
        if (isVisible) {
            panel.slideUp();
            console.log('🔼 Filtre paneli kapatıldı');
        } else {
            $('#bkm-action-form, #bkm-task-form, #bkm-settings-panel').slideUp();
            panel.slideDown();
            console.log('🔽 Filtre paneli açıldı');
        }
    } catch (error) {
        console.error('❌ toggleFilterPanel hatası:', error);
        showNotification('HATA: ' + error.message, 'error');
    }
}

/**
 * Switch settings tab
 * @param {string} tabName - Tab adı
 */
function switchSettingsTab(tabName) {
    try {
        console.log('🔄 Tab değiştiriliyor:', tabName);
        
        $('.settings-tab').removeClass('active');
        $('.bkm-settings-tab-content').removeClass('active');
        
        $('.settings-tab[data-tab="' + tabName + '"]').addClass('active');
        $('#settings-tab-' + tabName).addClass('active');
        
        if (tabName === 'users') {
            clearUserForm();
            console.log('👥 Users tab açıldı, PHP listesi kullanılıyor');
        } else if (tabName === 'company' && typeof loadCompanyInfo === 'function') {
            loadCompanyInfo();
        }
    } catch (error) {
        console.error('❌ switchSettingsTab hatası:', error);
    }
}

// ===== GLOBAL EXPORT =====

// Fonksiyonları global scope'a ekle
window.showNotification = showNotification;
window.escapeHtml = escapeHtml;
window.escapeJs = escapeJs;
window.validateForm = validateForm;
window.clearForm = clearForm;
window.setFormLoading = setFormLoading;
window.bkmAjax = bkmAjax;
window.isAjaxReady = isAjaxReady;
window.getCurrentUserId = getCurrentUserId;
window.getAjaxNonce = getAjaxNonce;

// Form clear fonksiyonları
window.clearUserForm = clearUserForm;
window.clearCategoryForm = clearCategoryForm;
window.clearPerformanceForm = clearPerformanceForm;
window.clearCompanyForm = clearCompanyForm;
window.clearAllSettingsForms = clearAllSettingsForms;

// Panel toggle fonksiyonları
window.toggleSettingsPanel = toggleSettingsPanel;
window.toggleFilterPanel = toggleFilterPanel;
window.switchSettingsTab = switchSettingsTab;

// jQuery wrapper'ı kapat
})(jQuery); 