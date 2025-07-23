/**
 * BKM Aksiyon Takip - Frontend JavaScript
 * Version: Optimized with utility functions
 */

// WordPress jQuery uyumluluğu için
(function($) {
    'use strict';

    // ===== CONSOLE DEBUG INFO =====
    console.log('🚀 BKM Frontend JS başlatılıyor...');
    console.log('📊 jQuery versiyonu:', $.fn.jquery);
    console.log('🌍 bkmFrontend objesi:', typeof bkmFrontend !== 'undefined' ? 'MEVCUT' : 'EKSİK');
    
    if (typeof bkmFrontend !== 'undefined') {
        console.log('🔗 AJAX URL:', bkmFrontend.ajax_url);
        console.log('🔐 Nonce mevcut:', bkmFrontend.nonce ? 'EVET' : 'HAYIR');
        console.log('👤 Current User ID:', bkmFrontend.current_user_id);
    }

    // Utility fonksiyonları yüklendi mi kontrol et
    if (typeof window.showNotification === 'undefined') {
        console.error('❌ Utility fonksiyonları yüklenmedi! bkm-utils.js dosyasını kontrol edin.');
    }

    // Sayfada bkmFrontend objesi yoksa, hata kontrolü yap ve güvenli çıkış
    if (typeof bkmFrontend === 'undefined') {
        console.error('❌ KRITIK HATA: bkmFrontend objesi bulunamadı!');
        console.error('💡 ÇÖZÜM ÖNERISI: Sayfayı yenileyin veya WordPress\'e giriş yaptığınızdan emin olun');
        
        // Güvenli fallback objesi oluştur
        window.bkmFrontend = {
            ajax_url: '/wp-admin/admin-ajax.php',
            nonce: '',
            current_user_id: 0
        };
        
        // Kullanıcıya bilgi ver
        setTimeout(function() {
            showNotification('WordPress sistemi yüklenirken sorun oluştu. Lütfen sayfayı yenileyin.', 'error');
        }, 1000);
    }

// ===== GLOBAL FONKSİYONLAR (Utility'den geliyor) =====
// Bu fonksiyonlar artık bkm-utils.js'den geliyor:
// - toggleSettingsPanel
// - toggleFilterPanel  
// - switchSettingsTab
// - showNotification
// - escapeHtml
// - escapeJs
// - validateForm
// - clearForm
// - setFormLoading
// - bkmAjax
// - isAjaxReady
// - getCurrentUserId
// - getAjaxNonce

// Test fonksiyonu
function testSettingsPanel() {
    console.log('🧪 Test: toggleSettingsPanel çağrılıyor...');
    if (typeof toggleSettingsPanel === 'function') {
        toggleSettingsPanel();
    } else {
        console.error('❌ toggleSettingsPanel fonksiyonu bulunamadı!');
    }
}

// ===== KULLANICI YÖNETİMİ FONKSİYONLARI =====

// Kullanıcıları yükle - Error handling ile güçlendirilmiş
function loadUsers() {
    console.log('👥 Kullanıcılar yükleniyor...');
    if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
        console.error('❌ bkmFrontend objesi tanımlanmamış!');
        showNotification('WordPress AJAX sistemi hazır değil. Lütfen sayfayı yenileyin.', 'error');
        return;
    }
    $.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        timeout: 30000,
        data: {
            action: 'bkm_get_users',
            nonce: bkmFrontend.nonce
        },
        beforeSend: function() {
            $('#users-list').html('<div class="loading">Kullanıcılar yükleniyor...</div>');
        },
        success: function(response) {
            console.log('👥 Kullanıcılar yanıtı:', response);
            var users = response.data && response.data.users ? response.data.users : (Array.isArray(response.data) ? response.data : []);
            if (!Array.isArray(users)) users = [];
            if (response && response.success) {
                updateUsersDisplay(users);
            } else {
                var errorMessage = 'Bilinmeyen hata';
                if (response && response.data) {
                    if (typeof response.data === 'string') {
                        errorMessage = response.data;
                    } else if (response.data.message) {
                        errorMessage = response.data.message;
                    }
                }
                $('#users-list').html('<div class="error">Hata: ' + errorMessage + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Kullanıcılar yüklenirken hata:', error, xhr.responseText);
            var errorMsg = 'Kullanıcılar yüklenirken hata oluştu.';
            if (xhr.status === 0) {
                errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
            } else if (xhr.status === 403) {
                errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
            } else if (xhr.status === 404) {
                errorMsg = 'WordPress AJAX sistemi bulunamadı.';
            } else if (xhr.status === 500) {
                errorMsg = 'Sunucu hatası oluştu.';
            }
            $('#users-list').html('<div class="error">' + errorMsg + '</div>');
        }
    });
}

// Kullanıcıları listele
function displayUsers(users) {
    var html = '';
    
    if (users && users.length > 0) {
        html += '<table class="users-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>Kullanıcı Adı</th>';
        html += '<th>E-posta</th>';
        html += '<th>Rol</th>';
        html += '<th>Kayıt Tarihi</th>';
        html += '<th>İşlemler</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        $.each(users, function(index, user) {
            html += '<tr>';
            html += '<td>' + escapeHtml(user.display_name) + '</td>';
            html += '<td>' + escapeHtml(user.user_email) + '</td>';
            html += '<td>' + escapeHtml(user.role_name) + '</td>';
            html += '<td>' + user.registration_date + '</td>';
            html += '<td class="actions">';
            html += '<button onclick="editUser(' + user.ID + ', \'' + escapeHtml(user.user_login) + '\', \'' + escapeHtml(user.user_email) + '\', \'' + escapeHtml(user.display_name) + '\', \'' + user.role + '\')" class="edit-btn">Düzenle</button>';
            html += '<button onclick="deleteUser(' + user.ID + ', \'' + escapeHtml(user.display_name) + '\')" class="delete-btn">Sil</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
    } else {
        html = '<div class="no-items">Henüz kullanıcı bulunmuyor.</div>';
    }
    
    $('#users-list').html(html);
}

// HTML escape fonksiyonu artık bkm-utils.js'den geliyor
// - escapeHtml()
// - escapeJs()

// Kullanıcı düzenle - Güvenlik kontrollü
function editUser(id, username, email, displayName, role) {
    console.log('✏️ editUser fonksiyonu çağrıldı:', id, username, email, displayName, role);
    
    // Güvenlik kontrolü - sadece admin kullanıcıları bu fonksiyonu kullanabilir
    var bodyClasses = document.body.className;
    var isAdmin = bodyClasses.includes('user-administrator');
    
    if (!isAdmin) {
        console.warn('🚫 YETKİSİZ ERİŞİM: Admin olmayan kullanıcı editUser fonksiyonunu çağırmaya çalıştı');
        alert('🚫 Bu işlem için yönetici yetkisi gereklidir!');
        return false;
    }
    
    console.log('✅ Yetki kontrolü geçildi, kullanıcı düzenleniyor:', id, username, email, displayName, role);
    
    var form = $('#bkm-user-form-element');
    form.find('#user_username').val(username).prop('disabled', true);
    form.find('#user_email').val(email);
    form.find('#user_display_name').val(displayName);
    
    // Rol seçimini güncelle - sadece allowed rolleri kontrol et
    var roleSelect = form.find('#user_role');
    var allowedRoles = ['administrator', 'editor', 'contributor'];
    
    // Eğer kullanıcının mevcut rolü allowed listede yoksa, uyarı ver ve contributor yap
    if (allowedRoles.indexOf(role) === -1) {
        console.warn('⚠️ Kullanıcının mevcut rolü (' + role + ') desteklenmiyor, contributor olarak ayarlanıyor');
        role = 'contributor';
    }
    
    roleSelect.val(role);
    form.find('#user_password').val('').prop('required', false);
    form.find('button[type="submit"]').text('✅ Kullanıcı Güncelle');
    form.data('edit-id', id);
    
    form.prev('h4').text('Kullanıcı Düzenle');
}

// Kullanıcı sil - Güvenlik kontrollü ve error handling ile güçlendirilmiş
function deleteUser(id, name) {
    console.log('🗑️ deleteUser fonksiyonu çağrıldı:', id, name);
    
    // Güvenlik kontrolü - sadece admin kullanıcıları bu fonksiyonu kullanabilir
    var bodyClasses = document.body.className;
    var isAdmin = bodyClasses.includes('user-administrator');
    
    if (!isAdmin) {
        console.warn('🚫 YETKİSİZ ERİŞİM: Admin olmayan kullanıcı deleteUser fonksiyonunu çağırmaya çalıştı');
        alert('🚫 Bu işlem için yönetici yetkisi gereklidir!');
        return false;
    }
    
    if (!confirm('⚠️ "' + name + '" kullanıcısını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        return;
    }
    
    console.log('✅ Yetki kontrolü geçildi, kullanıcı siliniyor:', id, name);
    
    if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
        console.error('❌ bkmFrontend objesi tanımlanmamış!');
        alert('WordPress AJAX sistemi hazır değil. Sayfayı yenileyin.');
        return;
    }
    
    $.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        timeout: 30000,
        data: {
            action: 'bkm_delete_user',
            user_id: id,
            nonce: bkmFrontend.nonce
        },
        beforeSend: function() {
            if (typeof showNotification === 'function') {
                showNotification('Kullanıcı siliniyor...', 'info');
            }
        },
        success: function(response) {
            console.log('🗑️ Kullanıcı silme yanıtı:', response);
            
            if (response && response.success) {
                showNotification('Kullanıcı başarıyla silindi!', 'success');
                // Kullanıcı listesini yenile
                loadUsers();
            } else {
                var errorMessage = 'Kullanıcı silinemedi';
                if (response && response.data) {
                    if (typeof response.data === 'string') {
                        errorMessage = response.data;
                    } else if (response.data.message) {
                        errorMessage = response.data.message;
                    }
                }
                showNotification('Hata: ' + errorMessage, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Kullanıcı silinirken hata:', error, xhr.responseText);
            
            var errorMsg = 'Kullanıcı silinirken hata oluştu.';
            if (xhr.status === 0) {
                errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
            } else if (xhr.status === 403) {
                errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
            } else if (xhr.status === 404) {
                errorMsg = 'WordPress AJAX sistemi bulunamadı.';
            } else if (xhr.status === 500) {
                errorMsg = 'Sunucu hatası oluştu.';
            }
            
            if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            }
        }
    });
}

// Form clear fonksiyonları artık bkm-utils.js'den geliyor
// - clearUserForm()
// - clearCategoryForm() 
// - clearPerformanceForm()
// - clearCompanyForm()
// - clearAllSettingsForms()

// Kullanıcı formu submit handler - Error handling ile güçlendirilmiş
function handleUserFormSubmit(e) {
    e.preventDefault();
    console.log('👤 Kullanıcı formu submit edildi');
    
    if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
        console.error('❌ bkmFrontend objesi tanımlanmamış!');
        if (typeof showNotification === 'function') {
            showNotification('WordPress AJAX sistemi yüklenemedi. Sayfayı yenileyin.', 'error');
        } else {
            alert('WordPress AJAX sistemi yüklenemedi. Sayfayı yenileyin.');
        }
        return;
    }
    
    var form = $(e.target);
    var isEdit = form.data('edit-id');
    var formData = {
        action: isEdit ? 'bkm_edit_user' : 'bkm_add_user',
        nonce: bkmFrontend.nonce
    };
    
    // Form verilerini al
    form.find('input, select').each(function() {
        var name = $(this).attr('name');
        if (name) {
            formData[name] = $(this).val();
        }
    });
    
    if (isEdit) {
        formData.user_id = isEdit;
    }
    
    console.log('📤 Kullanıcı form verileri:', formData);
    
    $.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        dataType: 'json',
        timeout: 30000,
        data: formData,
        beforeSend: function() {
            form.find('button[type="submit"]').prop('disabled', true).text('Kaydediliyor...');
        },
        success: function(response) {
            console.log('👤 Kullanıcı kaydetme yanıtı:', response);
            
            form.find('button[type="submit"]').prop('disabled', false);
            
            if (response && response.success) {
                if (typeof showNotification === 'function') {
                    var message = response.data.message || (isEdit ? 'Kullanıcı güncellendi!' : 'Kullanıcı eklendi!');
                    showNotification(message, 'success');
                }
                clearUserForm();
                
                console.log('🔄 Kullanıcı ' + (isEdit ? 'güncellendi' : 'eklendi') + ', liste yenileniyor...');
                
                // Kullanıcı ekleme/güncelleme sonrası AJAX ile listeyi güncelle
                loadUsers();
                
                console.log('✅ loadUsers() çağrıldı');
            } else {
                form.find('button[type="submit"]').text(isEdit ? 'Kullanıcı Güncelle' : 'Kullanıcı Ekle');
                if (typeof showNotification === 'function') {
                    showNotification('Hata: ' + (response.data || 'İşlem başarısız'), 'error');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Kullanıcı kaydedilirken hata:', error, xhr.responseText);
            form.find('button[type="submit"]').prop('disabled', false).text(isEdit ? 'Kullanıcı Güncelle' : 'Kullanıcı Ekle');
            
            var errorMsg = 'Kullanıcı kaydedilirken hata oluştu.';
            if (xhr.status === 0) {
                errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
            } else if (xhr.status === 403) {
                errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
            } else if (xhr.status === 404) {
                errorMsg = 'WordPress AJAX sistemi bulunamadı.';
            } else if (xhr.status === 500) {
                errorMsg = 'Sunucu hatası oluştu.';
            }
            
            if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            }
        }
    });
}

// Update users display - Optimized version
function updateUsersDisplay(users) {
    var usersList = $('#users-list');
    var usersHeader = $('#users-header');
    usersList.empty();
    if (!users || users.length === 0) {
        usersList.html('<div class="bkm-no-items">Kullanıcı bulunamadı.</div>');
        if (usersHeader.length) usersHeader.text('Mevcut Kullanıcılar (0)');
        return;
    }
    if (usersHeader.length) usersHeader.text('Mevcut Kullanıcılar (' + users.length + ')');
    $.each(users, function(index, user) {
        var roles = user.roles ? user.roles.join(', ') : 'Rol yok';
        var fullName = (user.first_name || '') + ' ' + (user.last_name || '');
        var displayName = fullName.trim() || user.display_name || user.user_login || 'İsimsiz';
        var html = '<div class="bkm-item" data-id="' + user.ID + '">';
        html += '<div class="bkm-item-content">';
        html += '<strong>' + escapeHtml(displayName) + '</strong>';
        html += '<p><span class="bkm-user-email">📧 ' + escapeHtml(user.user_email || 'Email yok') + '</span><br>';
        html += '<span class="bkm-user-role">👤 ' + escapeHtml(roles) + '</span><br>';
        html += '<span class="bkm-user-registered">📅 ' + (user.user_registered ? new Date(user.user_registered).toLocaleDateString('tr-TR') : '-') + '</span></p>';
        html += '</div>';
        html += '<div class="bkm-item-actions">';
        html += '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editUser(' + user.ID + ', \'' + escapeJs(user.user_login) + '\', \'' + escapeJs(user.user_email) + '\', \'' + escapeJs(user.first_name) + '\', \'' + escapeJs(user.last_name) + '\', \'' + escapeJs(roles) + '\')">✏️ Düzenle</button>';
        if (user.ID != bkmFrontend.current_user_id) {
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deleteUser(' + user.ID + ', \'' + escapeJs(displayName) + '\')">🗑️ Sil</button>';
        }
        html += '</div></div>';
        usersList.append(html);
    });
}

jQuery(document).ready(function($) {
    // Debug information
    console.log('🔧 BKM Frontend JS yüklendi');
    console.log('📊 jQuery versiyonu:', $.fn.jquery);
    console.log('🌍 bkmFrontend objesi:', typeof bkmFrontend !== 'undefined' ? bkmFrontend : 'UNDEFINED');
    
    // Test fonksiyonları
    console.log('🧪 toggleSettingsPanel fonksiyonu:', typeof toggleSettingsPanel);
    console.log('🧪 Global fonksiyonlar test ediliyor...');
    
    // Global test fonksiyonu ekle
    window.testSettingsPanel = testSettingsPanel;
    window.toggleSettingsPanel = toggleSettingsPanel;
    window.switchSettingsTab = switchSettingsTab;
    window.toggleFilterPanel = toggleFilterPanel;
    
    // Test if user is logged in properly
    if (typeof bkmFrontend !== 'undefined' && bkmFrontend.ajax_url) {
        console.log('✅ WordPress AJAX sistemi aktif');
        console.log('🔗 AJAX URL:', bkmFrontend.ajax_url);
        console.log('🔐 Nonce token mevcut:', bkmFrontend.nonce ? 'YES' : 'NO');
        console.log('👤 Current User ID:', bkmFrontend.current_user_id);
    } else {
        console.error('❌ KRITIK HATA: bkmFrontend objesi yüklenemedi!');
        console.error('💡 ÇÖZÜM: WordPress admin paneline giriş yapın veya sayfayı yenileyin');
        
        // Kullanıcıya uyarı göster
        setTimeout(function() {
            if (typeof showNotification === 'function') {
                showNotification('WordPress bağlantısı kurulamadı. Lütfen sayfayı yenileyin ve giriş yapmayı deneyin.', 'error');
            }
        }, 2000);
    }
    
    // Test if task form exists
    if ($('#bkm-task-form-element').length > 0) {
        console.log('✅ Görev ekleme formu bulundu');
    } else {
        console.log('⚠️ Görev ekleme formu bulunamadı - sadece yetkili kullanıcılar görebilir');
    }
    
    // Debug forms availability
    console.log('📋 FORM DURUMU:');
    console.log('- Action Form:', $('#bkm-action-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- Task Form:', $('#bkm-task-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- User Form:', $('#bkm-user-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- Category Form:', $('#bkm-category-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- Performance Form:', $('#bkm-performance-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- Company Form:', $('#bkm-company-form-element').length > 0 ? 'MEVCUT' : 'YOK');
    console.log('- Settings Panel:', $('#bkm-settings-panel').length > 0 ? 'MEVCUT' : 'YOK');
    
    // Auto-load company info if company form exists
    if ($('#bkm-company-form-element').length > 0 && typeof loadCompanyInfo === 'function') {
        console.log('🏢 Firma formu bulundu, bilgileri yükleniyor...');
        setTimeout(function() {
            loadCompanyInfo();
        }, 1000); // 1 saniye bekle ki her şey yüklenmiş olsun
    }
    
    // Auto-load company info when company tab becomes visible
    $(document).on('click', '.settings-tab[data-tab="company"]', function() {
        console.log('🏢 Firma tab\'ına tıklandı, bilgileri yükleniyor...');
        if (typeof loadCompanyInfo === 'function') {
            setTimeout(loadCompanyInfo, 100);
        }
    });
    
    // Check if company tab is already active and load info
    setTimeout(function() {
        if ($('.settings-tab[data-tab="company"]').hasClass('active') && typeof loadCompanyInfo === 'function') {
            console.log('🏢 Firma tab\'ı zaten aktif, bilgileri yükleniyor...');
            loadCompanyInfo();
        }
    }, 1500);
    
    // ===== FORM SUBMIT HANDLERS =====
    
    // Kullanıcı formu submit handler
    $(document).on('submit', '#bkm-user-form-element', function(e) {
        handleUserFormSubmit(e);
    });
    
    // Kategori formu submit handler
    $(document).on('submit', '#bkm-category-form-element', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();
        var editId = form.data('edit-id');
        var isEdit = editId ? true : false;
        var actionName = isEdit ? 'bkm_edit_category' : 'bkm_add_category';
        // Validate
        var name = form.find('#category_name').val().trim();
        if (!name) {
            showNotification('Kategori adı boş olamaz.', 'error');
            return;
        }
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('İşleniyor...');
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: formData + '&action=' + actionName + '&nonce=' + bkmFrontend.nonce + (isEdit ? '&id=' + editId : ''),
            success: function(response) {
                console.log('📂 Kategori AJAX yanıtı:', response);
                if (response && response.success) {
                    var message = 'Kategori başarıyla kaydedildi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    showNotification(message, 'success');
                    form[0].reset();
                    clearCategoryForm();
                    if (typeof refreshCategoryDropdown === 'function') {
                        refreshCategoryDropdown();
                    }
                } else {
                    var errorMessage = 'Kategori işlemi sırasında hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Kategori hatası:', errorMessage, response);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Kategori işlemi hatası:', error, xhr.responseText, xhr);
                var errorMsg = 'İşlem sırasında bir hata oluştu.';
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                }
                showNotification(errorMsg, 'error');
            },
            complete: function(xhr, status) {
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text(isEdit ? 'Kategori Güncelle' : 'Kategori Ekle');
            }
        });
    });
    
    // Performans formu submit handler
    $(document).on('submit', '#bkm-performance-form-element', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = form.serialize();
        var editId = form.data('edit-id');
        var isEdit = editId ? true : false;
        var actionName = isEdit ? 'bkm_edit_performance' : 'bkm_add_performance';
        // Validate
        var name = form.find('#performance_name').val().trim();
        if (!name) {
            showNotification('Performans adı boş olamaz.', 'error');
            return;
        }
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('İşleniyor...');
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: formData + '&action=' + actionName + '&nonce=' + bkmFrontend.nonce + (isEdit ? '&id=' + editId : ''),
            success: function(response) {
                console.log('📊 Performans AJAX yanıtı:', response);
                if (response && response.success) {
                    var message = 'Performans başarıyla kaydedildi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    showNotification(message, 'success');
                    form[0].reset();
                    clearPerformanceForm();
                    if (typeof refreshPerformanceDropdown === 'function') {
                        refreshPerformanceDropdown();
                    }
                } else {
                    var errorMessage = 'Performans işlemi sırasında hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Performans hatası:', errorMessage, response);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Performans işlemi hatası:', error, xhr.responseText, xhr);
                var errorMsg = 'İşlem sırasında bir hata oluştu.';
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                }
                showNotification(errorMsg, 'error');
            },
            complete: function(xhr, status) {
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text(isEdit ? 'Performans Güncelle' : 'Performans Ekle');
            }
        });
    });
    
    // Company form submit handler
    $(document).on('submit', '#bkm-company-form-element', function(e) {
        e.preventDefault();
        console.log('\ud83c\udfe2 Company form submit edildi');
        if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
            console.error('\u274c bkmFrontend objesi tan\u0131mlanmam\u0131\u015f!');
            showNotification('WordPress AJAX sistemi haz\u0131r de\u011fil. Sayfay\u0131 yenileyin.', 'error');
            return;
        }
        var form = $(this);
        var formData = new FormData(this);
        formData.append('action', 'bkm_save_company_settings');
        formData.append('nonce', bkmFrontend.nonce);
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Kaydediliyor...');
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('\ud83c\udfe2 Firma bilgileri AJAX yan\u0131t\u0131:', response);
                if (response && response.success) {
                    var message = 'Firma bilgileri ba\u015far\u0131yla kaydedildi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    showNotification(message, 'success');
                    
                    // Logo preview'ını güncelle (eğer yeni logo yüklendiyse)
                    if (response.data && response.data.logo_url) {
                        var preview = jQuery('#logo-preview');
                        preview.html(
                            '<img src="' + response.data.logo_url + '" alt="Mevcut Logo" />' +
                            '<button type="button" class="bkm-btn bkm-btn-danger bkm-btn-small bkm-remove-logo" ' +
                            'onclick="removeCompanyLogo()">🗑️ Logoyu Kaldır</button>'
                        );
                        // File input'u temizle
                        jQuery('#company_logo').val('');
                    }
                    
                    // Company info display'i güncelle
                    if (response.data && response.data.company_info) {
                        updateCompanyInfoDisplay(response.data.company_info);
                    } else if (typeof loadCompanyInfo === 'function') {
                        loadCompanyInfo();
                    }
                } else {
                    var errorMessage = 'Firma bilgileri kaydedilemedi.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('\u274c Firma bilgileri hatas\u0131:', errorMessage);
                    showNotification('Hata: ' + errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('\u274c Firma bilgileri kaydetme hatas\u0131:', error, xhr.responseText);
                var errorMsg = 'Firma bilgileri kaydedilirken bir hata olu\u015ftu.';
                if (xhr.status === 0) {
                    errorMsg = 'Ba\u011flant\u0131 hatas\u0131. \u0130nternet ba\u011flant\u0131n\u0131z\u0131 kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatas\u0131. Bu i\u015flemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamad\u0131.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatas\u0131 olu\u015ftu.';
                }
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Firma Bilgilerini Kaydet');
            }
        });
    });
    
    // Aksiyon formu submit handler
    $(document).on('submit', '#bkm-action-form-element', function(e) {
        e.preventDefault();
        console.log('🎯 Aksiyon formu submit edildi');
        
        if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            showNotification('WordPress AJAX sistemi hazır değil. Sayfayı yenileyin.', 'error');
            return;
        }
        
        var form = $(this);
        var formData = form.serialize();
        
        // Validate required fields
        var isValid = true;
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotification('Lütfen tüm zorunlu alanları doldurun.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Ekleniyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: formData + '&action=bkm_add_action&nonce=' + bkmFrontend.nonce,
            success: function(response) {
                console.log('🎯 Aksiyon AJAX yanıtı:', response);
                
                if (response && response.success) {
                    var message = 'Aksiyon başarıyla eklendi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    showNotification(message, 'success');
                    form[0].reset();
                    // Hide form if toggle function exists
                    if (typeof toggleActionForm === 'function') {
                        toggleActionForm();
                    }
                    // Aksiyon listesini yenile (sayfa reload yerine)
                    if (typeof refreshActions === 'function') {
                        refreshActions();
                    } else {
                        // Fallback olarak sayfa yenile
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    var errorMessage = 'Aksiyon eklenirken hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Aksiyon hatası:', errorMessage);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Aksiyon ekleme hatası:', error, xhr.responseText);
                
                var errorMsg = 'Bir hata oluştu: ' + error;
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Aksiyon Ekle');
            }
        });
    });
    
    // Görev formu submit handler - Enhanced
    $(document).on('submit', '#bkm-task-form-element', function(e) {
        e.preventDefault();
        console.log('📋 Görev formu submit edildi');
        
        if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            showNotification('WordPress AJAX sistemi hazır değil. Sayfayı yenileyin.', 'error');
            return;
        }
        
        var form = $(this);
        var formData = new FormData(form[0]);
        
        console.log('📝 Form elementi bilgileri:');
        form.find('input, select, textarea').each(function() {
            console.log('  - ' + $(this).attr('name') + ': ' + $(this).val());
        });
        
        // Enhanced data mapping with multiple field name support
        var mappedData = {
            action: 'bkm_add_task',
            nonce: bkmFrontend.nonce
        };
        
        // Primary field mappings from FormData
        for (let [key, value] of formData) {
            mappedData[key] = value;
        }
        
        // Secondary field name support for legacy compatibility
        if (!mappedData.action_id && (mappedData.aksiyon_id || form.find('[name="aksiyon_id"]').val())) {
            mappedData.action_id = mappedData.aksiyon_id || form.find('[name="aksiyon_id"]').val();
        }
        
        if (!mappedData.content) {
            mappedData.content = mappedData.aciklama || mappedData.title || mappedData.description || form.find('[name="aciklama"]').val();
        }
        
        if (!mappedData.description) {
            mappedData.description = mappedData.aciklama || mappedData.content || form.find('[name="aciklama"]').val();
        }
        
        if (!mappedData.hedef_bitis_tarihi) {
            mappedData.hedef_bitis_tarihi = mappedData.bitis_tarihi || mappedData.target_date || form.find('[name="bitis_tarihi"]').val() || form.find('[name="hedef_bitis_tarihi"]').val();
        }
        
        if (!mappedData.sorumlu_id) {
            mappedData.sorumlu_id = mappedData.responsible || form.find('[name="sorumlu_id"]').val();
        }
        
        if (!mappedData.baslangic_tarihi) {
            mappedData.baslangic_tarihi = mappedData.start_date || form.find('[name="baslangic_tarihi"]').val() || new Date().toISOString().split('T')[0];
        }
        
        console.log('📋 Enhanced mapped data:', mappedData);
        
        // Enhanced validation with comprehensive field checking
        var validationErrors = [];
        
        if (!mappedData.action_id || mappedData.action_id <= 0) {
            validationErrors.push('Aksiyon ID gerekli');
        }
        
        if (!mappedData.aciklama && !mappedData.content) {
            validationErrors.push('Görev içeriği gerekli');
        }
        
        if (!mappedData.sorumlu_id || mappedData.sorumlu_id <= 0) {
            validationErrors.push('Sorumlu kişi gerekli');
        }
        
        if (!mappedData.bitis_tarihi && !mappedData.hedef_bitis_tarihi) {
            validationErrors.push('Hedef bitiş tarihi gerekli');
        }
        
        if (validationErrors.length > 0) {
            console.error('❌ Validation errors:', validationErrors);
            showNotification('Eksik alanlar: ' + validationErrors.join(', '), 'error');
            
            // Highlight error fields
            form.find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Ekleniyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: mappedData,
            success: function(response) {
                console.log('📋 Görev AJAX yanıtı:', response);
                
                if (response && response.success) {
                    var message = 'Görev başarıyla eklendi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    showNotification(message, 'success');
                    form[0].reset();
                    // Hide form if toggle function exists
                    if (typeof toggleTaskForm === 'function') {
                        toggleTaskForm();
                    }
                    // Page refresh to show new task
                    if (typeof refreshActions === 'function') {
                        refreshActions();
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                } else {
                    var errorMessage = 'Görev eklenirken hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Görev hatası:', errorMessage);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Görev ekleme hatası:', error, xhr.responseText);
                
                var errorMsg = 'Bir hata oluştu: ' + error;
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Görev Ekle');
            }
        });
    });
    
    // Ana not ekleme formu AJAX (görev notları dahil) - Error handling ile güçlendirilmiş
    $(document).on('submit', '.bkm-note-form form:not(.bkm-reply-form), .bkm-task-note-form-element', function(e) {
        e.preventDefault();
        console.log('🔧 Not ekleme formu submit edildi');
        
        var form = $(this);
        var taskId = form.find('input[name="task_id"]').val();
        var content = form.find('textarea[name="note_content"]').val().trim();
        var progressValue = form.find('input[name="note_progress"]').val();
        
        console.log('📝 Task ID:', taskId, 'Content:', content, 'Progress:', progressValue);
        
        if (!content) {
            showNotification('Not içeriği boş olamaz.', 'error');
            return;
        }
        
        // Progress validation
        if (progressValue !== '' && progressValue !== null) {
            var progress = parseInt(progressValue);
            if (isNaN(progress) || progress < 0 || progress > 100) {
                showNotification('İlerleme durumu 0-100 arasında olmalıdır.', 'error');
                form.find('input[name="note_progress"]').focus();
                return;
            }
        }
        
        // Check if bkmFrontend is available
        if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            showNotification('WordPress AJAX sistemi yüklenmedi. Sayfayı yenileyin.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');
        
        var ajaxData = {
            action: 'bkm_add_note',
            task_id: taskId,
            content: content,
            nonce: bkmFrontend.nonce
        };
        
        // Add progress if provided
        if (progressValue !== '' && progressValue !== null) {
            ajaxData.progress = progressValue;
        }
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: ajaxData,
            success: function(response) {
                console.log('🔄 AJAX response alındı:', response);
                if (response && response.success) {
                    // Store current progress value before clearing form
                    var progressInput = form.find('input[name="note_progress"]');
                    var originalProgress = progressInput.attr('value') || progressInput.val();
                    
                    // Clear form
                    form[0].reset();
                    
                    // Restore original progress value to the input for next use
                    if (response.data.progress_updated && response.data.new_progress !== undefined) {
                        progressInput.val(response.data.new_progress);
                        progressInput.attr('value', response.data.new_progress);
                        
                        // Update the small text showing current progress
                        var smallText = progressInput.siblings('small');
                        if (smallText.length > 0) {
                            smallText.text('Mevcut: ' + response.data.new_progress + '%');
                        }
                    } else {
                        progressInput.val(originalProgress);
                    }
                    
                    // Hide note form
                    toggleNoteForm(taskId);
                    
                    // Update task progress bar if progress was updated
                    if (response.data.progress_updated && response.data.new_progress !== undefined) {
                        console.log('🔄 İlerleme güncelleniyor:', response.data.new_progress + '%');
                        
                        // Find the task item with matching task ID using data attribute
                        var taskItem = $('.bkm-task-item[data-task-id="' + taskId + '"]');
                        console.log('🎯 Task item bulundu:', taskItem.length);
                        
                        if (taskItem.length > 0) {
                            var progressBar = taskItem.find('.bkm-progress-bar');
                            var progressText = taskItem.find('.bkm-progress-text');
                            
                            console.log('✅ İlerleme çubuğu bulundu:', progressBar.length, 'Progress Text:', progressText.length);
                            
                            if (progressBar.length > 0) {
                                // Animate progress bar update
                                progressBar.animate({
                                    width: response.data.new_progress + '%'
                                }, 500, function() {
                                    // Add visual feedback after animation
                                    progressBar.addClass('progress-updated');
                                    setTimeout(function() {
                                        progressBar.removeClass('progress-updated');
                                    }, 2000);
                                });
                                
                                if (progressText.length > 0) {
                                    progressText.text(response.data.new_progress + '%');
                                }
                                
                                console.log('✅ İlerleme çubuğu güncellendi:', response.data.new_progress + '%');
                                
                                // If task is completed (100%), add visual indicator
                                if (response.data.new_progress == 100) {
                                    taskItem.addClass('completed');
                                    
                                    // Show completion message
                                    showNotification('🎉 Görev tamamlandı!', 'success');
                                    
                                    // Update task actions - hide complete button if it exists
                                    var completeButton = taskItem.find('button[onclick*="complete_task"]');
                                    if (completeButton.length > 0) {
                                        completeButton.fadeOut();
                                    }
                                }
                            } else {
                                console.log('❌ İlerleme çubuğu bulunamadı');
                            }
                        } else {
                            console.log('❌ Task item bulunamadı, task ID:', taskId);
                            
                            // Fallback: try to find any progress bar near the form
                            var progressBar = form.closest('.bkm-tasks-container').find('.bkm-progress-bar');
                            var progressText = form.closest('.bkm-tasks-container').find('.bkm-progress-text');
                            
                            if (progressBar.length > 0) {
                                console.log('🔄 Fallback yöntemiyle ilerleme güncelleniyor...');
                                
                                progressBar.animate({
                                    width: response.data.new_progress + '%'
                                }, 500);
                                
                                if (progressText.length > 0) {
                                    progressText.text(response.data.new_progress + '%');
                                }
                                
                                progressBar.addClass('progress-updated');
                                setTimeout(function() {
                                    progressBar.removeClass('progress-updated');
                                }, 2000);
                                
                                console.log('✅ Fallback yöntemiyle ilerleme güncellendi');
                            }
                        }
                    }
                    
                    // Update action progress bar if action progress was updated
                    if (response.data.action_progress_updated && response.data.new_action_progress !== undefined && response.data.action_id) {
                        console.log('🎯 Aksiyon ilerlemesi güncelleniyor:', response.data.new_action_progress + '%');
                        updateActionProgress(response.data.action_id, response.data.new_action_progress);
                        showNotification('Not eklendi ve aksiyon ilerlemesi güncellendi: ' + response.data.new_action_progress + '%', 'success');
                    }
                    
                    // Reload notes to show the new note with proper hierarchy
                    loadTaskNotes(taskId, function() {
                        // Ensure notes section is visible
                        var notesSection = $('#notes-' + taskId);
                        if (notesSection.is(':hidden')) {
                            notesSection.slideDown(300);
                        }
                        
                        // Highlight the new note (last main note)
                        var newNote = notesSection.find('.bkm-main-note').last();
                        if (newNote.length > 0) {
                            newNote.addClass('new-note-highlight');
                            
                            // Smooth scroll to the new note
                            setTimeout(function() {
                                $('html, body').animate({
                                    scrollTop: newNote.offset().top - 100
                                }, 500);
                            }, 300);
                            
                            // Remove highlight after animation
                            setTimeout(function() {
                                newNote.removeClass('new-note-highlight');
                            }, 3000);
                        }
                    });
                    
                    // Update notes button count or create the button
                    var notesButton = $('button[onclick="toggleNotes(' + taskId + ')"]');
                    if (notesButton.length > 0) {
                        var currentCount = parseInt(notesButton.text().match(/\d+/)[0] || 0);
                        var newCount = currentCount + 1;
                        notesButton.text('💬 Notları Göster (' + newCount + ')');
                    } else {
                        // Add notes button if it doesn't exist
                        var taskActions = form.closest('.bkm-task-item').find('.bkm-task-actions');
                        if (taskActions.length === 0) {
                            // If no task actions div, look for it in the task container
                            taskActions = form.closest('.bkm-task-item').find('.bkm-task-actions');
                        }
                        if (taskActions.length === 0) {
                            // Create task actions div if it doesn't exist
                            var taskItem = form.closest('.bkm-task-item');
                            taskActions = $('<div class="bkm-task-actions"></div>');
                            taskItem.append(taskActions);
                        }
                        taskActions.append('<button class="bkm-btn bkm-btn-small" onclick="toggleNotes(' + taskId + ')">💬 Notları Göster (1)</button>');
                    }
                    
                    // Hide note form
                    toggleNoteForm(taskId);
                    
                    var message = 'Not başarıyla eklendi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    
                    if (response.data && response.data.progress_updated) {
                        message += ' İlerleme durumu güncellendi: ' + response.data.new_progress + '%';
                    }
                    if (response.data && response.data.action_progress_updated) {
                        message += ' Aksiyon ilerlemesi: ' + response.data.new_action_progress + '%';
                    }
                    console.log('✅ Not başarıyla eklendi:', message);
                    showNotification(message, 'success');
                } else {
                    var errorMessage = 'Not eklenirken hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Not ekleme hatası:', errorMessage);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Not ekleme hatası:', error, xhr.responseText);
                
                var errorMsg = 'Bir hata oluştu: ' + error;
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Not Ekle ve İlerlemeyi Güncelle');
            }
        });
    });
    
    // Cevap formu AJAX - Enhanced error handling
    $(document).on('submit', '.bkm-reply-form', function(e) {
        e.preventDefault();
        console.log('💬 Cevap formu submit edildi');
        
        if (typeof bkmFrontend === 'undefined' || !bkmFrontend.ajax_url) {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            showNotification('WordPress AJAX sistemi hazır değil. Sayfayı yenileyin.', 'error');
            return;
        }
        
        var form = $(this);
        var taskId = form.data('task-id');
        var parentId = form.data('parent-id');
        var content = form.find('textarea[name="note_content"]').val().trim();
        
        console.log('💬 Cevap data:', {
            taskId: taskId,
            parentId: parentId,
            content: content,
            ajax_url: bkmFrontend.ajax_url,
            nonce: bkmFrontend.nonce ? 'MEVCUT' : 'EKSİK'
        });
        
        if (!content) {
            showNotification('Cevap içeriği boş olamaz.', 'error');
            return;
        }
        
        if (!taskId || !parentId) {
            console.error('❌ Task ID veya Parent ID eksik:', { taskId: taskId, parentId: parentId });
            showNotification('Görev ID veya üst not ID eksik.', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Gönderiliyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: {
                action: 'bkm_reply_note',
                task_id: taskId,
                parent_note_id: parentId,
                content: content,
                nonce: bkmFrontend.nonce
            },
            success: function(response) {
                console.log('💬 Cevap AJAX yanıtı:', response);
                
                if (response && response.success) {
                    // Clear form and hide it
                    form[0].reset();
                    if (typeof toggleReplyForm === 'function') {
                        toggleReplyForm(taskId, parentId);
                    } else {
                        console.warn('⚠️ toggleReplyForm fonksiyonu bulunamadı');
                        form.hide();
                    }
                    
                    // Reload notes to show the new reply with proper hierarchy
                    if (typeof loadTaskNotes === 'function') {
                        loadTaskNotes(taskId, function() {
                            // Ensure notes section is visible
                            var notesSection = $('#notes-' + taskId);
                            if (notesSection.is(':hidden')) {
                                notesSection.slideDown(300);
                            }
                            
                            // Find and highlight the new reply
                            var parentMainNote = notesSection.find('.bkm-main-note[data-note-id="' + parentId + '"]');
                            if (parentMainNote.length > 0) {
                                // Find the last reply to this parent
                                var newReply = parentMainNote.nextAll('.bkm-reply-note[data-parent-id="' + parentId + '"]').last();
                                if (newReply.length > 0) {
                                    newReply.addClass('new-note-highlight');
                                    
                                    // Smooth scroll to the new reply
                                    setTimeout(function() {
                                        $('html, body').animate({
                                            scrollTop: newReply.offset().top - 100
                                        }, 500);
                                    }, 300);
                                    
                                    // Remove highlight after animation
                                    setTimeout(function() {
                                        newReply.removeClass('new-note-highlight');
                                    }, 3000);
                                }
                            }
                            
                            // Update notes count
                            var notesButton = $('button[onclick="toggleNotes(' + taskId + ')"]');
                            if (notesButton.length > 0) {
                                var currentCount = parseInt(notesButton.text().match(/\d+/)[0] || 0);
                                var newCount = currentCount + 1;
                                notesButton.text('💬 Notları Göster (' + newCount + ')');
                            }
                        });
                    } else {
                        console.warn('⚠️ loadTaskNotes fonksiyonu bulunamadı, sayfa yenileniyor...');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                    
                    var message = 'Cevap başarıyla gönderildi!';
                    if (response.data && response.data.message) {
                        message = response.data.message;
                    }
                    console.log('✅ Cevap başarıyla eklendi:', message);
                    showNotification(message, 'success');
                } else {
                    var errorMessage = 'Cevap gönderilirken hata oluştu.';
                    if (response && response.data) {
                        if (typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                    console.error('❌ Cevap gönderme hatası:', errorMessage);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Cevap gönderme AJAX hatası:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                var errorMsg = 'Cevap gönderilirken hata oluştu.';
                if (xhr.status === 0) {
                    errorMsg = 'Bağlantı hatası. İnternet bağlantınızı kontrol edin.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Yetki hatası. Bu işlemi yapmaya yetkiniz yok.';
                } else if (xhr.status === 404) {
                    errorMsg = 'WordPress AJAX sistemi bulunamadı.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Sunucu hatası oluştu.';
                    try {
                        var responseData = JSON.parse(xhr.responseText);
                        if (responseData && responseData.data && responseData.data.message) {
                            errorMsg += ' Detay: ' + responseData.data.message;
                        }
                    } catch(e) {
                        if (xhr.responseText) {
                            errorMsg += ' Response: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                }
                
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Cevap Gönder');
            }
        });
    });
    
    // ===== AKSIYON EKLEME İŞLEVLERİ =====
    
    // Aksiyon ekleme formu AJAX - Error handling ile güçlendirilmiş
    // Action formu AJAX handler duplicate kaldırıldı - üstteki handler kullanılıyor
    
    // ===== GÖREV EKLEME AJAX SİSTEMİ =====
    
    // Görev ekleme formu AJAX - Error handling ile güçlendirilmiş
    // Task formu AJAX handler duplicate kaldırıldı - üstteki handler kullanılıyor
    
    // Yeni aksiyonlardaki görev formları için handler (class-based selector)
    $(document).on('submit', '.bkm-task-form-element', function(e) {
        e.preventDefault();
        
        console.log('🚀 Yeni aksiyon görev formu submit edildi');
        
        if (typeof bkmFrontend === 'undefined') {
            console.error('❌ bkmFrontend objesi tanımlanmamış!');
            alert('HATA: WordPress AJAX sistemi yüklenmemiş. Sayfayı yenileyin.');
            return;
        }
        
        var form = $(this);
        var actionId = form.data('action-id');
        var formData = form.serialize();
        
        console.log('📝 Original form data:', formData);
        console.log('📝 Action ID from data-action-id:', actionId);
        
        // Parse form data and ensure correct field mapping
        var params = new URLSearchParams(formData);
        var mappedData = {
            action: 'bkm_add_task',
            nonce: bkmFrontend.nonce,
            action_id: actionId // Always use the action_id from data attribute
        };
        
        // Map form fields to backend expected format
        for (let [key, value] of params) {
            switch(key) {
                case 'aciklama':
                case 'sorumlu_id':
                case 'baslangic_tarihi':
                case 'bitis_tarihi':
                    mappedData[key] = value;
                    break;
                // Handle real form field names from dashboard.php
                case 'task_content':
                    mappedData['aciklama'] = value;
                    break;
                case 'hedef_bitis_tarihi':
                    mappedData['bitis_tarihi'] = value;
                    break;
                // Handle alternative field names
                case 'gorev_aciklama':
                case 'task_aciklama':
                case 'description':
                    mappedData['aciklama'] = value;
                    break;
                case 'sorumlu':
                case 'sorumlu_kisi':
                case 'responsible':
                    mappedData['sorumlu_id'] = value;
                    break;
                case 'baslangic':
                case 'start_date':
                    mappedData['baslangic_tarihi'] = value;
                    break;
                case 'bitis':
                case 'end_date':
                    mappedData['bitis_tarihi'] = value;
                    break;
                default:
                    // Skip action field to avoid conflict
                    if (key !== 'action') {
                        mappedData[key] = value;
                    }
                    break;
            }
        }
        
        console.log('📋 Mapped form data:', mappedData);
        
        // Validate required fields
        var isValid = true;
        var requiredFields = ['action_id', 'aciklama', 'sorumlu_id'];
        
        requiredFields.forEach(function(field) {
            if (!mappedData[field] || mappedData[field].toString().trim() === '') {
                console.error('❌ Missing required field:', field, 'Value:', mappedData[field]);
                isValid = false;
            }
        });
        
        // Also validate form UI elements
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            showNotification('Lütfen tüm zorunlu alanları doldurun. (Açıklama, Sorumlu Kişi)', 'error');
            return;
        }
        
        // Disable form during submission
        form.addClass('loading').find('button[type="submit"]').prop('disabled', true).text('Ekleniyor...');
        
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: mappedData,
            timeout: 30000,
            success: function(response) {
                console.log('📨 Yeni aksiyon görev AJAX yanıtı:', response);
                
                if (response.success) {
                    // Clear form
                    form[0].reset();
                    
                    // Hide form
                    toggleTaskForm(actionId);
                    
                    // Show success message
                    showNotification(response.data.message, 'success');
                    
                    // Add new task to the action's task list
                    if (response.data.task_html) {
                        addNewTaskToAction(actionId, response.data.task_html);
                    }
                    
                    // Update task count in button
                    updateTaskCount(actionId);
                    
                    // Update action progress if it was updated
                    if (response.data.action_progress_updated && response.data.new_action_progress !== undefined) {
                        console.log('🎯 Aksiyon ilerlemesi güncelleniyor:', response.data.new_action_progress + '%');
                        updateActionProgress(actionId, response.data.new_action_progress);
                        showNotification('Görev eklendi ve aksiyon ilerlemesi güncellendi: ' + response.data.new_action_progress + '%', 'success');
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Bir hata oluştu: ' + error;
                if (xhr.status === 0) {
                    errorMessage = 'Bağlantı hatası: Sunucuya ulaşılamıyor.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Yetki hatası: Bu işlemi yapmaya yetkiniz yok.';
                }
                showNotification(errorMessage, 'error');
            },
            complete: function() {
                // Re-enable form
                form.removeClass('loading').find('button[type="submit"]').prop('disabled', false).text('Görev Ekle');
            }
        });
    });
    
    /**
     * Add new task to action's task list
     */
    function addNewTaskToAction(actionId, taskHtml) {
        var tasksRow = $('#tasks-' + actionId);
        
        if (tasksRow.length === 0) {
            // If tasks row doesn't exist, create it (shouldn't happen normally)
            return;
        }
        
        var tasksContainer = tasksRow.find('.bkm-tasks-container');
        var tasksList = tasksContainer.find('.bkm-tasks-list');
        
        // If no tasks list exists, create it and remove "no tasks" message
        if (tasksList.length === 0) {
            tasksContainer.find('p:contains("henüz görev bulunmamaktadır")').remove();
            tasksList = $('<div class="bkm-tasks-list"></div>');
            tasksContainer.append(tasksList);
        }
        
        // Add new task with enhanced animation
        var newTaskElement = $(taskHtml);
        newTaskElement.hide();
        tasksList.append(newTaskElement);
        
        // Show with slide down animation
        newTaskElement.slideDown(400, function() {
            // Add highlighting animation
            newTaskElement.addClass('new-task-highlight');
            
            // Remove highlight after animation completes
            setTimeout(function() {
                newTaskElement.removeClass('new-task-highlight');
            }, 3000);
            
            // Scroll to the new task with smooth animation
            $('html, body').animate({
                scrollTop: newTaskElement.offset().top - 100
            }, 600, 'swing');
        });
        
        // Update task count in button
        updateTaskCount(actionId);
        
        // If tasks row is not visible, show it
        if (tasksRow.is(':hidden')) {
            tasksRow.slideDown(300);
        }
    }
    
    /**
     * Update task count in the tasks button
     */
    function updateTaskCount(actionId) {
        var tasksButton = $('button[onclick="toggleTasks(' + actionId + ')"]');
        if (tasksButton.length > 0) {
            var currentText = tasksButton.text();
            var match = currentText.match(/\((\d+)\)/);
            if (match) {
                var currentCount = parseInt(match[1]);
                var newCount = currentCount + 1;
                var newText = currentText.replace(/\(\d+\)/, '(' + newCount + ')');
                tasksButton.text(newText);
            }
        }
    }
    
    /**
     * Update action progress bar - Enhanced version
     */
    function updateActionProgress(actionId, newProgress) {
        console.log('🔄 updateActionProgress çağrıldı, actionId:', actionId, 'newProgress:', newProgress + '%');
        
        // Find action progress bars using data-action-id
        var progressBars = $('.bkm-progress[data-action-id="' + actionId + '"]');
        
        // If no data-action-id, try to find in the action row (fallback)
        if (progressBars.length === 0) {
            console.log('⚠️ data-action-id ile bulunamadı, fallback aranıyor...');
            
            // Find the action row and its progress bar
            var actionRows = $('tr').filter(function() {
                var firstCell = $(this).find('td:first').text().trim();
                return firstCell == actionId;
            });
            progressBars = actionRows.find('.bkm-progress');
            console.log('🔍 Fallback ile bulunan progress bar sayısı:', progressBars.length);
        }
        
        console.log('📊 Bulunan aksiyon ilerleme çubukları:', progressBars.length);
        
        if (progressBars.length === 0) {
            console.warn('❌ Aksiyon ' + actionId + ' için ilerleme çubuğu bulunamadı!');
            return;
        }
        
        progressBars.each(function() {
            var progressContainer = $(this);
            var progressBar = progressContainer.find('.bkm-progress-bar');
            var progressText = progressContainer.find('.bkm-progress-text');
            
            console.log('🎯 İlerleme çubuğu güncelleniyor:', {
                actionId: actionId,
                newProgress: newProgress,
                hasBar: progressBar.length > 0,
                hasText: progressText.length > 0
            });
            
            if (progressBar.length > 0) {
                // Store current width for comparison
                var currentWidth = progressBar.css('width');
                var currentPercent = parseInt(currentWidth) || 0;
                
                console.log('📈 İlerleme değişimi:', currentPercent + '% → ' + newProgress + '%');
                
                // Animate progress bar update
                progressBar.animate({
                    width: newProgress + '%'
                }, 800, function() {
                    // Add visual feedback after animation
                    progressBar.addClass('progress-updated');
                    progressContainer.addClass('action-progress-highlight');
                    
                    setTimeout(function() {
                        progressBar.removeClass('progress-updated');
                        progressContainer.removeClass('action-progress-highlight');
                    }, 2500);
                });
                
                if (progressText.length > 0) {
                    progressText.text(newProgress + '%');
                }
                
                console.log('✅ Aksiyon ' + actionId + ' ilerleme çubuğu güncellendi: ' + newProgress + '%');
                
                // If action is completed (100%), add visual indicator
                if (newProgress == 100) {
                    progressContainer.addClass('action-completed');
                    
                    // Show completion celebration
                    setTimeout(function() {
                        progressContainer.append('<div class="completion-badge">🎉 Tamamlandı!</div>');
                        setTimeout(function() {
                            progressContainer.find('.completion-badge').fadeOut();
                        }, 3000);
                    }, 500);
                    
                    console.log('🎉 Aksiyon ' + actionId + ' tamamlandı!');
                    showNotification('🏆 Aksiyon tamamlandı!', 'success');
                }
            } else {
                console.error('❌ İlerleme çubuğu (.bkm-progress-bar) bulunamadı');
            }
        });
    }
    
    // ===== MEVCUT KODLAR =====
    
    // Görev ekleme formu validasyonu (ESKİ - ARTIK KULLANILMIYOR)
    // $('#bkm-task-form form').on('submit', function(e) { ... });

    // Login form validasyonu
    $('.bkm-login-form').on('submit', function(e) {
        var username = $('#log').val();
        var password = $('#pwd').val();
        
        if (!username || !password) {
            e.preventDefault();
            alert('Lütfen kullanıcı adı ve şifre girin.');
            return false;
        }
    });
   
    // Initialize date inputs
    $('input[type="date"]').each(function() {
        if (!$(this).val()) {
            $(this).val(new Date().toISOString().slice(0, 10));
        }
    });
    
    // Form validation (AJAX note formları hariç - bunlar kendi validasyonlarını yapar)
    $('form:not(.bkm-note-form form):not(.bkm-reply-form)').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Clear previous error styles
        form.find('.error').removeClass('error');
        
        // Validate required fields
        form.find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            }
        });
        
        // Validate date fields
        form.find('input[type="date"]').each(function() {
            var dateValue = $(this).val();
            if (dateValue && !isValidDate(dateValue)) {
                $(this).addClass('error');
                isValid = false;
            }
        });
        
        // Validate progress percentage
        var progressInput = form.find('input[name="ilerleme_durumu"]');
        if (progressInput.length > 0) {
            var progress = parseInt(progressInput.val());
            if (isNaN(progress) || progress < 0 || progress > 100) {
                progressInput.addClass('error');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Lütfen tüm gerekli alanları doğru şekilde doldurun.', 'error');
            
            // Scroll to first error
            var firstError = form.find('.error').first();
            if (firstError.length > 0) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
                firstError.focus();
            }
            
            return false;
        }
    });
    
    // Progress bar real-time update
    $('input[name="ilerleme_durumu"]').on('input', function() {
        var value = $(this).val();
        var progressBar = $(this).closest('.bkm-field').find('.bkm-progress-bar');
        if (progressBar.length > 0) {
            progressBar.css('width', value + '%');
        }
    });
    
    // Auto-hide notifications
    $('.bkm-success, .bkm-error').each(function() {
        var notification = $(this);
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    });
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $($(this).attr('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
    
    // Task completion confirmation
    $('.bkm-btn-success[onclick*="confirm"]').on('click', function(e) {
        e.preventDefault();
        
        var form = $(this).closest('form');
        var taskContent = $(this).closest('.bkm-task-item').find('.bkm-task-content p strong').text();
        
        if (confirm('Bu görevi tamamladınız mı?\n\n"' + taskContent + '"')) {
            form.submit();
        }
    });
    
    // Table sorting
    $('.bkm-table th[data-sort]').on('click', function() {
        var table = $(this).closest('table');
        var column = $(this).data('sort');
        var order = $(this).hasClass('asc') ? 'desc' : 'asc';
        
        // Remove existing sort classes
        table.find('th').removeClass('asc desc');
        $(this).addClass(order);
        
        sortTable(table, column, order);
    });
    
    // Search functionality
    $('#bkm-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.bkm-table tbody tr').each(function() {
            var row = $(this);
            var text = row.text().toLowerCase();
            
            if (text.indexOf(searchTerm) > -1) {
                row.show();
            } else {
                row.hide();
            }
        });
    });
    
    // Filter functionality
    $('.bkm-filter-select').on('change', function() {
        // Get all filter values
        var tanimlayan = $('#filter-tanimlayan').val();
        var sorumlu = $('#filter-sorumlu').val();
        var kategori = $('#filter-kategori').val();
        var onem = $('#filter-onem').val();
        var durum = $('#filter-durum').val();

        $('.bkm-table tbody tr').each(function() {
            var row = $(this);
            var match = true;

            if (tanimlayan && row.data('tanimlayan') != tanimlayan) match = false;
            if (sorumlu && (!row.data('sorumlu') || row.data('sorumlu').split(',').indexOf(sorumlu) === -1)) match = false;
            if (kategori && row.data('kategori') != kategori) match = false;
            if (onem && row.data('onem') != onem) match = false;
            if (durum && row.data('durum') != durum) match = false;

            if (match) {
                row.show();
            } else {
                row.hide();
            }
        });

        // Filtrelerden herhangi biri 'Tümü' ise, tüm detay ve görev formlarını kapat
        if (!tanimlayan && !sorumlu && !kategori && !onem && !durum) {
            $('.bkm-action-details-row:visible').slideUp();
            $('.bkm-tasks-row:visible').slideUp();
        }
    });
    
    // Real-time character counter for textareas
    $('textarea[maxlength]').each(function() {
        var textarea = $(this);
        var maxLength = textarea.attr('maxlength');
        var counter = $('<div class="char-counter">' + textarea.val().length + '/' + maxLength + '</div>');
        
        textarea.after(counter);
        
        textarea.on('input', function() {
            var currentLength = $(this).val().length;
            counter.text(currentLength + '/' + maxLength);
            
            if (currentLength > maxLength * 0.9) {
                counter.addClass('warning');
            } else {
                counter.removeClass('warning');
            }
        });
    });
    
    // Mobile menu toggle
    $('.bkm-mobile-menu-toggle').on('click', function() {
        $('.bkm-mobile-menu').slideToggle();
    });
    
    // Responsive table handling
    function makeTablesResponsive() {
        $('.bkm-table').each(function() {
            var table = $(this);
            if (!table.parent().hasClass('table-responsive')) {
                table.wrap('<div class="table-responsive"></div>');
            }
        });
    }
    
    makeTablesResponsive();
    
    // Helper functions
    function isValidDate(dateString) {
        var regEx = /^\d{4}-\d{2}-\d{2}$/;
        if (!dateString.match(regEx)) return false;
        var d = new Date(dateString);
        var dNum = d.getTime();
        if (!dNum && dNum !== 0) return false;
        return d.toISOString().slice(0, 10) === dateString;
    }
    
    // showNotification fonksiyonu global scope'a taşındı
    
    function sortTable(table, column, order) {
        var tbody = table.find('tbody');
        var rows = tbody.find('tr').toArray();
        
        rows.sort(function(a, b) {
            var aValue = $(a).find('[data-sort="' + column + '"]').text().trim();
            var bValue = $(b).find('[data-sort="' + column + '"]').text().trim();
            
            // Try to parse as numbers
            var aNum = parseFloat(aValue);
            var bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return order === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // Parse as dates
            var aDate = new Date(aValue);
            var bDate = new Date(bValue);
            
            if (!isNaN(aDate) && !isNaN(bDate)) {
                return order === 'asc' ? aDate - bDate : bDate - aDate;
            }
            
            // String comparison
            if (order === 'asc') {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });
        
        tbody.empty().append(rows);
    }
    
    // Helper function to add new action to table
    function addNewActionToTable(actionHtml) {
        var tableBody = $('.bkm-table tbody');
        var newRow;
        
        // Check if "no actions" message exists
        var noActionsRow = tableBody.find('td:contains("Henüz aksiyon bulunmamaktadır")').closest('tr');
        
        if (noActionsRow.length > 0) {
            // Replace "no actions" message with new action
            noActionsRow.replaceWith(actionHtml);
            newRow = tableBody.find('tr').first();
        } else {
            // Prepend new action to the top of the table
            tableBody.prepend(actionHtml);
            newRow = tableBody.find('tr').first();
        }
        
        // Add highlight animation to the new row
        newRow.addClass('new-action-row');
        
        // Improved scroll to new action
        setTimeout(function() {
            if (newRow.length && newRow.is(':visible')) {
                // Get the table element for reference
                var table = $('.bkm-table');
                var tableOffset = table.offset();
                
                if (tableOffset) {
                    // Calculate the position of the new row within the table
                    var rowOffset = newRow.offset();
                    var targetPosition = rowOffset.top - 120; // 120px from top for better visibility
                    
                    // Ensure we don't scroll above the table
                    var minPosition = tableOffset.top - 50;
                    targetPosition = Math.max(minPosition, targetPosition);
                    
                    // Use a different scroll method for better reliability
                    $('html, body').stop().animate({
                        scrollTop: targetPosition
                    }, {
                        duration: 1200,
                        easing: 'swing',
                        complete: function() {
                            // Flash effect after scroll completes
                            newRow.fadeOut(150).fadeIn(150).fadeOut(150).fadeIn(150);
                        }
                    });
                } else {
                    // Fallback: scroll to top of page
                    $('html, body').animate({ scrollTop: 0 }, 800);
                }
            }
        }, 400); // Increased delay for DOM to fully update
        
        // Remove highlight after animation
        setTimeout(function() {
            newRow.removeClass('new-action-row');
        }, 5000);
    }
    
    // Helper function to update task form action dropdown
    function updateTaskFormActionDropdown(actionId, actionDetails) {
        var actionSelect = $('#action_id');
        
        if (actionSelect.length === 0) {
            console.log('⚠️ updateTaskFormActionDropdown: Aksiyon dropdown bulunamadı');
            return;
        }
        
        // Create new option element
        var optionText = '#' + actionId + ' - ' + (actionDetails.tespit_konusu || actionDetails.title || '');
        var newOption = $('<option></option>')
            .attr('value', actionId)
            .text(optionText);
        
        // Check if option already exists
        if (actionSelect.find('option[value="' + actionId + '"]').length === 0) {
            // Add new option after the first "Seçiniz..." option
            actionSelect.find('option:first').after(newOption);
            
            // Highlight the new option temporarily
            newOption.addClass('new-option');
            setTimeout(function() {
                newOption.removeClass('new-option');
            }, 3000);
            
            console.log('✅ Yeni aksiyon görev dropdown\'ına eklendi:', optionText);
            showNotification('Yeni aksiyon görev formunda da görüntülendi!', 'success');
        }
    }

    // AJAX functionality
    if (typeof bkmFrontend !== 'undefined') {
        
        // Auto-save form data to localStorage (aksiyon formu hariç)
        $('form input, form select, form textarea').not('#bkm-action-form-element input, #bkm-action-form-element select, #bkm-action-form-element textarea').on('change input', function() {
            var form = $(this).closest('form');
            var formId = form.attr('id') || 'bkm-form';
            
            // Skip action form auto-save to prevent conflicts
            if (formId === 'bkm-action-form-element') {
                return;
            }
            
            var formData = form.serialize();
            localStorage.setItem('bkm_form_data_' + formId, formData);
        });
        
        // Restore form data from localStorage (aksiyon formu hariç)
        $('form').not('#bkm-action-form-element').each(function() {
            var form = $(this);
            var formId = form.attr('id') || 'bkm-form';
            var savedData = localStorage.getItem('bkm_form_data_' + formId);
            
            if (savedData) {
                var params = new URLSearchParams(savedData);
                params.forEach(function(value, key) {
                    var field = form.find('[name="' + key + '"]');
                    if (field.length > 0) {
                        if (field.is('select')) {
                            field.val(value);
                        } else if (field.is('input[type="checkbox"]') || field.is('input[type="radio"]')) {
                            if (field.val() === value) {
                                field.prop('checked', true);
                            }
                        } else {
                            field.val(value);
                        }
                    }
                });
            }
        });
        
        // Clear saved form data on successful submission (aksiyon formu hariç - manuel yönetim)
        $('form').not('#bkm-action-form-element').on('submit', function() {
            var formId = $(this).attr('id') || 'bkm-form';
            localStorage.removeItem('bkm_form_data_' + formId);
        });
    }
    
    // Accessibility improvements
    $('input, select, textarea').on('focus', function() {
        $(this).closest('.bkm-field').addClass('focused');
    }).on('blur', function() {
        $(this).closest('.bkm-field').removeClass('focused');
    });
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        // ESC to close modals/forms
        if (e.key === 'Escape') {
            $('.bkm-task-form:visible').hide();
            $('.bkm-tasks-row:visible').hide();
        }
        
        // Enter to submit forms (if not in textarea)
        if (e.key === 'Enter' && !$(e.target).is('textarea')) {
            var form = $(e.target).closest('form');
            if (form.length > 0) {
                e.preventDefault();
                form.submit();
            }
        }
    });
    
    // Performance optimization: Lazy load images
    $('img[data-src]').each(function() {
        var img = $(this);
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var lazyImg = $(entry.target);
                    lazyImg.attr('src', lazyImg.data('src'));
                    lazyImg.removeAttr('data-src');
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(this);
    });
    
    // Aksiyon formu sorumlu kişiler multi-select fix
    $(document).on('change', '#action_sorumlu_ids', function(e) {
        console.log('🔧 Sorumlu kişiler seçimi değişti:', $(this).val());
        // Prevent auto-clear by stopping any conflicting events
        e.stopPropagation();
        
        // Store the selection to prevent loss
        var selectedValues = $(this).val() || [];
        $(this).data('selected-values', selectedValues);
        
        // Update visual feedback
        $(this).attr('title', selectedValues.length + ' kişi seçildi');
    });
    
    // Prevent multi-select from losing selection on blur
    $(document).on('blur', '#action_sorumlu_ids', function(e) {
        var storedValues = $(this).data('selected-values');
        if (storedValues && storedValues.length > 0) {
            // Restore selection if it was cleared
            setTimeout(() => {
                if (!$(this).val() || $(this).val().length === 0) {
                    $(this).val(storedValues);
                    console.log('🔄 Sorumlu kişiler seçimi geri yüklendi:', storedValues);
                }
            }, 100);
        }
    });

    // ... mevcut kod ...
    $(document).ready(function() {
        // Kategori başlığını her zaman önce sabit olarak ayarla
        $('#categories-header').text('Mevcut Kategoriler');
        if (typeof refreshCategoryDropdown === 'function') {
            refreshCategoryDropdown();
        }
        if (typeof refreshPerformanceDropdown === 'function') {
            refreshPerformanceDropdown();
        }
    });
    // ... mevcut kod ...
    // toggleSettingsPanel fonksiyonu içinde panel açılırken de aynı şekilde çağırabilirsin (isteğe bağlı)
});

// Global functions
window.toggleTaskForm = function() {
    console.log('🔧 toggleTaskForm çağrıldı');
    var form = jQuery('#bkm-task-form');
    var isVisible = form.is(':visible');
    
    if (isVisible) {
        // Form kapanıyorsa sadece kapat (görev formu otomatik temizleme zaten yapılıyor)
        form.slideUp();
        console.log('📝 Görev formu kapatıldı');
    } else {
        // Form açılıyorsa diğer formları kapat
        jQuery('#bkm-action-form, #bkm-settings-panel').slideUp();
        form.slideDown();
        console.log('📝 Görev formu açıldı');
    }
}

// Parametreli task form toggle fonksiyonu (yeni aksiyonlar için)
window.toggleTaskForm = function(actionId) {
    if (actionId) {
        console.log('🔧 toggleTaskForm çağrıldı, actionId:', actionId);
        var form = jQuery('#task-form-' + actionId);
        var isVisible = form.is(':visible');
        
        if (isVisible) {
            form.slideUp();
            console.log('📝 Görev formu kapatıldı, actionId:', actionId);
        } else {
            // Diğer task formlarını kapat
            jQuery('.bkm-task-form').slideUp();
            form.slideDown();
            console.log('📝 Görev formu açıldı, actionId:', actionId);
        }
    } else {
        // Eski toggle fonksiyonu (parametresiz)
        console.log('🔧 toggleTaskForm çağrıldı (eski versiyon)');
        var form = jQuery('#bkm-task-form');
        var isVisible = form.is(':visible');
        
        if (isVisible) {
            form.slideUp();
            console.log('📝 Görev formu kapatıldı');
        } else {
            jQuery('#bkm-action-form, #bkm-settings-panel').slideUp();
            form.slideDown();
            console.log('📝 Görev formu açıldı');
        }
    }
}

window.toggleActionForm = function() {
    console.log('🔧 toggleActionForm çağrıldı');
    var form = jQuery('#bkm-action-form');
    var isVisible = form.is(':visible');
    
    if (isVisible) {
        // Form kapanıyorsa temizle
        form.slideUp();
        if (typeof clearActionForm === 'function') {
            clearActionForm();
        }
        console.log('📝 Aksiyon formu kapatıldı');
    } else {
        // Form açılıyorsa diğer formları kapat
        jQuery('#bkm-task-form, #bkm-settings-panel').slideUp();
        form.slideDown();
        console.log('📝 Aksiyon formu açıldı');
    }
}

function clearActionForm() {
    var form = jQuery('#bkm-action-form-element');
    
    if (form.length === 0) {
        console.log('⚠️ clearActionForm: Form bulunamadı');
        return;
    }
    
    // Reset form completely but preserve the structure
    form[0].reset();
    
    // Remove any error classes
    form.find('.error').removeClass('error');
    
    // Clear multi-select specifically (but don't override user selections)
    // Only clear when form is actually being reset after submission
    var multiSelect = form.find('#action_sorumlu_ids');
    if (multiSelect.length > 0) {
        multiSelect.val([]).trigger('change');
    }
    
    // Set default date to tomorrow
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    form.find('#action_hedef_tarih').val(tomorrow.toISOString().slice(0, 10));
    
    // Reset all field borders to normal
    form.find('input, select, textarea').css('border-color', '');
    
    // Clear saved form data to prevent conflicts
    var formId = form.attr('id') || 'bkm-action-form-element';
    localStorage.removeItem('bkm_form_data_' + formId);
    
    console.log('🧹 Aksiyon formu temizlendi (global function)');
}

// Global fonksiyonları window objesine ekle
window.clearActionForm = clearActionForm;
window.loadUsers = loadUsers;
window.handleUserFormSubmit = handleUserFormSubmit;

window.toggleTasks = function(actionId) {
    console.log('🔧 toggleTasks çağrıldı, actionId:', actionId);
    var tasksRow = jQuery('#tasks-' + actionId);
    console.log('📝 Tasks row bulundu:', tasksRow.length);
    
    if (tasksRow.length > 0) {
        tasksRow.slideToggle();
    } else {
        console.error('❌ Tasks row bulunamadı, ID:', '#tasks-' + actionId);
        showNotification('Görevler bölümü bulunamadı.', 'error');
    }
}

window.toggleActionDetails = function(actionId) {
    console.log('🔧 toggleActionDetails çağrıldı, actionId:', actionId);
    var detailsRow = jQuery('#details-' + actionId);
    var isVisible = detailsRow.is(':visible');
    
    console.log('📋 Details row bulundu:', detailsRow.length, 'Görünür:', isVisible);
    
    if (isVisible) {
        // Detaylar açıksa kapat
        detailsRow.slideUp();
        console.log('📤 Detaylar kapatıldı');
    } else {
        // Detaylar kapalıysa aç ve diğer detayları kapat
        jQuery('.bkm-action-details-row:visible').slideUp();
        detailsRow.slideDown();
        console.log('📥 Detaylar açıldı');
        
        // Smooth scroll to details
        setTimeout(function() {
            jQuery('html, body').animate({
                scrollTop: detailsRow.offset().top - 100
            }, 500);
        }, 300);
    }
}

function bkmPrintTable() {
    var printContents = jQuery('.bkm-table').clone();
    var originalContents = document.body.innerHTML;
    
    document.body.innerHTML = '<table class="bkm-table">' + printContents.html() + '</table>';
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

/**
 * Show notification message to user
 */
window.showNotification = function(message, type) {
    // Modern AJAX notification system
    var notificationClass = type === 'error' ? 'error' : 'success';
    var notification = jQuery('<div class="bkm-ajax-notification ' + notificationClass + '">' + 
                        '<span>' + message + '</span>' +
                        '<button class="close-btn" onclick="jQuery(this).parent().removeClass(\'show\')">&times;</button>' +
                        '</div>');
    
    // Remove existing notifications
    jQuery('.bkm-ajax-notification').remove();
    
    // Add to body
    jQuery('body').append(notification);
    
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

// ===== YENİ GÖREV NOTLARI FONKSİYONLARI =====

/**
 * Toggle note form visibility
 */
window.toggleNoteForm = function(taskId) {
    console.log('🔧 toggleNoteForm çağrıldı, taskId:', taskId);
    var noteForm = jQuery('#note-form-' + taskId);
    console.log('📝 Note form bulundu:', noteForm.length);
    
    if (noteForm.length > 0) {
        if (noteForm.is(':visible')) {
            noteForm.slideUp(300);
        } else {
            // Close other note forms first
            jQuery('.bkm-note-form:visible').slideUp(300);
            noteForm.slideDown(300, function() {
                noteForm.find('textarea').focus();
            });
        }
    } else {
        console.error('❌ Not formu bulunamadı, ID:', '#note-form-' + taskId);
    }
};
    
/**
 * Toggle notes section visibility
 */
window.toggleNotes = function(taskId) {
    console.log('🔧 toggleNotes çağrıldı, taskId:', taskId);
    var notesSection = jQuery('#notes-' + taskId);
    console.log('💬 Notes section bulundu:', notesSection.length, 'Visible:', notesSection.is(':visible'));
    
    if (notesSection.length > 0) {
        if (notesSection.is(':visible')) {
            console.log('📁 Notlar gizleniyor...');
            notesSection.slideUp(300);
        } else {
            console.log('📂 Notlar gösteriliyor, önce yükleniyor...');
            // Load notes first, then show
            loadTaskNotes(taskId, function() {
                console.log('✅ Notlar yüklendi, slideDown çalıştırılıyor...');
                notesSection.slideDown(300, function() {
                    console.log('✅ slideDown tamamlandı');
                });
            });
        }
    } else {
        console.error('❌ Notlar bölümü bulunamadı, ID:', '#notes-' + taskId);
        // Debug: bulmaya çalış
        console.log('🔍 Mevcut notes elementleri:', jQuery('[id*="notes-"]').length);
        jQuery('[id*="notes-"]').each(function() {
            console.log('📄 Bulunan notes elementi:', this.id);
        });
    }
};
    
/**
 * Load task notes via AJAX
 */
window.loadTaskNotes = function(taskId, callback) {
    console.log('🔄 Loading notes for task:', taskId);
    
    // Check if bkmFrontend is available
    if (typeof bkmFrontend === 'undefined') {
        console.error('❌ bkmFrontend objesi tanımlanmamış!');
        showNotification('WordPress AJAX sistemi yüklenmedi.', 'error');
        return;
    }
    
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        data: {
            action: 'bkm_get_notes', // Changed from bkm_get_task_notes to bkm_get_notes
            task_id: taskId,
            nonce: bkmFrontend.nonce
        },
        success: function(response) {
            console.log('📨 Task notes response:', response);
            
            if (response.success) {
                var notesContainer = jQuery('#notes-' + taskId + ' .bkm-notes-content');
                var isDirectContainer = notesContainer.length > 0;
                
                if (!isDirectContainer) {
                    notesContainer = jQuery('#notes-' + taskId);
                }
                
                console.log('🎯 Notes container found:', notesContainer.length, 'Direct container:', isDirectContainer);
                
                // Fixed data structure - backend returns {notes: [...]}
                var notes = response.data.notes || response.data || [];
                console.log('📝 Retrieved notes count:', notes.length);
                
                if (notes && notes.length > 0) {
                    var notesHtml = '';
                    
                    // Add wrapper div only if we're targeting the main container (not .bkm-notes-content)
                    if (!isDirectContainer) {
                        notesHtml += '<div class="bkm-notes-content">';
                    }
                    
                    // Build hierarchical HTML - backend already provides replies for each note
                    notes.forEach(function(note) {
                        // Main note
                        notesHtml += '<div class="bkm-note-item bkm-main-note" data-note-id="' + note.id + '">';
                        notesHtml += '<div class="bkm-note-indicator"></div>';
                        notesHtml += '<div class="bkm-note-content-wrapper">';
                        notesHtml += '<div class="bkm-note-meta">';
                        notesHtml += '<span class="bkm-note-author">👤 ' + (note.author_name || 'Bilinmeyen') + '</span>';
                        notesHtml += '<span class="bkm-note-date">📅 ' + (note.created_at || 'Tarih yok') + '</span>';
                        notesHtml += '</div>';
                        notesHtml += '<div class="bkm-note-content">' + (note.content || '[İçerik yok]') + '</div>';
                        notesHtml += '<div class="bkm-note-actions">';
                        notesHtml += '<button class="bkm-btn bkm-btn-small bkm-btn-secondary" onclick="toggleReplyForm(' + taskId + ', ' + note.id + ')">💬 Notu Cevapla</button>';
                        notesHtml += '</div>';
                        notesHtml += '<div id="reply-form-' + taskId + '-' + note.id + '" class="bkm-note-form" style="display: none;">';
                        notesHtml += '<form class="bkm-reply-form" data-task-id="' + taskId + '" data-parent-id="' + note.id + '">';
                        notesHtml += '<textarea name="note_content" rows="3" placeholder="Cevabınızı buraya yazın..." required></textarea>';
                        notesHtml += '<div class="bkm-form-actions">';
                        notesHtml += '<button type="submit" class="bkm-btn bkm-btn-primary bkm-btn-small">Cevap Gönder</button>';
                        notesHtml += '<button type="button" class="bkm-btn bkm-btn-secondary bkm-btn-small" onclick="toggleReplyForm(' + taskId + ', ' + note.id + ')">İptal</button>';
                        notesHtml += '</div>';
                        notesHtml += '</form>';
                        notesHtml += '</div>';
                        notesHtml += '</div>';
                        notesHtml += '</div>';
                        
                        // Replies to this note (from backend response)
                        if (note.replies && note.replies.length > 0) {
                            note.replies.forEach(function(reply) {
                                notesHtml += '<div class="bkm-note-item bkm-reply-note" data-note-id="' + reply.id + '" data-parent-id="' + note.id + '">';
                                notesHtml += '<div class="bkm-reply-connector"></div>';
                                notesHtml += '<div class="bkm-reply-arrow">↳</div>';
                                notesHtml += '<div class="bkm-note-content-wrapper">';
                                notesHtml += '<div class="bkm-note-meta">';
                                notesHtml += '<span class="bkm-note-author">👤 ' + (reply.author_name || 'Bilinmeyen') + '</span>';
                                notesHtml += '<span class="bkm-note-date">📅 ' + (reply.created_at || 'Tarih yok') + '</span>';
                                notesHtml += '<span class="bkm-reply-badge">Cevap</span>';
                                notesHtml += '</div>';
                                notesHtml += '<div class="bkm-note-content">' + (reply.content || '[İçerik yok]') + '</div>';
                                notesHtml += '</div>';
                                notesHtml += '</div>';
                            });
                        }
                    });
                    
                    // Close wrapper div only if we added it
                    if (!isDirectContainer) {
                        notesHtml += '</div>';
                    }
                    
                    notesContainer.html(notesHtml);
                    console.log('✅ Notes HTML updated successfully');
                } else {
                    var emptyHtml = '<p style="text-align: center; color: #9e9e9e; font-style: italic; margin: 20px 0; padding: 30px; border: 2px dashed #e0e0e0; border-radius: 12px;">📝 Bu görev için henüz not bulunmamaktadır.</p>';
                    
                    if (!isDirectContainer) {
                        emptyHtml = '<div class="bkm-notes-content">' + emptyHtml + '</div>';
                    }
                    
                    notesContainer.html(emptyHtml);
                    console.log('📝 Empty notes message displayed');
                }
                
                if (callback) callback();
            } else {
                var errorMessage = 'Notlar yüklenirken hata oluştu.';
                if (response && response.data) {
                    if (typeof response.data === 'string') {
                        errorMessage = response.data;
                    } else if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                }
                console.error('❌ Failed to load task notes:', errorMessage);
                showNotification(errorMessage, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('💥 AJAX error loading task notes:', error);
            showNotification('Notlar yüklenirken bağlantı hatası oluştu.', 'error');
            if (callback) callback();
        }
    });
}

/**
 * Toggle reply form visibility for a specific note
 */
window.toggleReplyForm = function(taskId, noteId) {
    console.log('🔧 toggleReplyForm çağrıldı, taskId:', taskId, 'noteId:', noteId);
    var replyForm = jQuery('#reply-form-' + taskId + '-' + noteId);
    console.log('💬 Reply form bulundu:', replyForm.length);
    
    if (replyForm.length > 0) {
        if (replyForm.is(':visible')) {
            replyForm.slideUp(300);
        } else {
            // Close other reply forms first
            jQuery('.bkm-note-form:visible').slideUp(300);
            replyForm.slideDown(300, function() {
                replyForm.find('textarea').focus();
            });
        }
    } else {
        console.error('❌ Cevap formu bulunamadı, ID:', '#reply-form-' + taskId + '-' + noteId);
    }
};

// Service Worker devre dışı - sw.js dosyası mevcut değil
// if ('serviceWorker' in navigator) {
//     navigator.serviceWorker.register('/sw.js').then(function(registration) {
//         console.log('ServiceWorker registration successful');
//     }).catch(function(err) {
//         console.log('ServiceWorker registration failed');
//     });
// }

// ===== YÖNETİM PANELLERİ (KATEGORİLER & PERFORMANSLAR) =====
    
    // Kategoriler paneli toggle (eski - artık kullanılmıyor)
    function toggleCategoriesPanel() {
        // Yeni sistemde ayarlar panelini aç ve kategori tab'ını göster
        toggleSettingsPanel();
        setTimeout(function() {
            switchSettingsTab('categories');
        }, 100);
    }
    
    // Performanslar paneli toggle (eski - artık kullanılmıyor)
    function togglePerformancesPanel() {
        // Yeni sistemde ayarlar panelini aç ve performans tab'ını göster
        toggleSettingsPanel();
        setTimeout(function() {
            switchSettingsTab('performances');
        }, 100);
    }
    
    // Kategori formu temizle
    function clearCategoryForm() {
        var form = $('#bkm-category-form-element');
        form[0].reset();
        form.find('button[type="submit"]').text('Kategori Ekle');
        form.removeData('edit-id');
    }
    
    // Performans formu temizle
    function clearPerformanceForm() {
        var form = $('#bkm-performance-form-element');
        form[0].reset();
        form.find('button[type="submit"]').text('Performans Ekle');
        form.removeData('edit-id');
    }
    
    // Kategori düzenle
    function editCategory(id, name, description) {
        var form = $('#bkm-category-form-element');
        form.find('#category_name').val(name);
        form.find('#category_description').val(description);
        form.find('button[type="submit"]').text('Kategori Güncelle');
        form.data('edit-id', id);
        
        // Form alanını highlight et
        form.find('#category_name').focus();
    }
    
    // Performans düzenle  
    function editPerformance(id, name, description) {
        var form = $('#bkm-performance-form-element');
        form.find('#performance_name').val(name);
        form.find('#performance_description').val(description);
        form.find('button[type="submit"]').text('Performans Güncelle');
        form.data('edit-id', id);
        
        // Form alanını highlight et
        form.find('#performance_name').focus();
    }
    
    // Kategori sil
    
    // Kategori formu AJAX handler duplicate kaldırıldı - üstteki handler kullanılıyor
    
    // Performans formu AJAX - Error handling ile güçlendirilmiş
    // Performans formu AJAX handler duplicate kaldırıldı - üstteki handler kullanılıyor
    
    // Yeni kategori listeye ekle
    function addCategoryToList(category) {
        var html = '<div class="bkm-item" data-id="' + category.id + '">' +
                   '<div class="bkm-item-content">' +
                   '<strong>' + escapeHtml(category.name) + '</strong>';
        
        if (category.description && typeof category.description === 'string' && category.description.trim()) {
            html += '<p>' + escapeHtml(category.description) + '</p>';
        }
        
        html += '</div>' +
                '<div class="bkm-item-actions">' +
                '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editCategory(' + category.id + ', \'' + 
                escapeJs(category.name) + '\', \'' + escapeJs(category.description || '') + '\')">Düzenle</button>' +
                '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deleteCategory(' + category.id + ')">Sil</button>' +
                '</div></div>';
        
        $('#categories-list').prepend(html);
    }
    
    // Yeni performans listeye ekle
    function addPerformanceToList(performance) {
        var html = '<div class="bkm-item" data-id="' + performance.id + '">' +
                   '<div class="bkm-item-content">' +
                   '<strong>' + escapeHtml(performance.name) + '</strong>';
        
        if (performance.description && typeof performance.description === 'string' && performance.description.trim()) {
            html += '<p>' + escapeHtml(performance.description) + '</p>';
        }
        
        html += '</div>' +
                '<div class="bkm-item-actions">' +
                '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editPerformance(' + performance.id + ', \'' + 
                escapeJs(performance.name) + '\', \'' + escapeJs(performance.description || '') + '\')">Düzenle</button>' +
                '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deletePerformance(' + performance.id + ')">Sil</button>' +
                '</div></div>';
        
        $('#performances-list').prepend(html);
    }
    
    // Helper functions
    // Escape fonksiyonları artık bkm-utils.js'den geliyor
    // - escapeHtml()
    // - escapeJs()
    
    // Global user cache
    var usersCache = {};

    // Actions refresh fonksiyonu
    function refreshActions() {
        $.ajax({
            url: bkm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_get_actions',
                nonce: bkm_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateActionsTable(response.data);
                } else {
                    console.error('Aksiyon listesi yenilenemedi:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX hatası:', error);
            }
        });
    }

    // Actions tablosunu güncelleme fonksiyonu
    function updateActionsTable(actions) {
        var tbody = $('.bkm-table tbody');
        if (tbody.length === 0) {
            console.error('Actions table tbody bulunamadı');
            return;
        }

        tbody.empty();

        if (actions.length === 0) {
            tbody.append('<tr><td colspan="8">Henüz aksiyon bulunmamaktadır.</td></tr>');
            return;
        }

        actions.forEach(function(action) {
            var row = $('<tr>');
            
            // ID
            row.append('<td>' + action.id + '</td>');
            
            // Tanımlayan
            row.append('<td><span class="bkm-user-badge">' + (action.tanımlayan_name || 'Bilinmiyor') + '</span></td>');
            
            // Sorumlu Kişiler  
            var sorumluCell = '<td>';
            if (action.sorumlu_ids) {
                var sorumluIds = action.sorumlu_ids.split(',');
                var sorumluNames = [];
                sorumluIds.forEach(function(id) {
                    var name = getUserDisplayName(id.trim());
                    if (name) {
                        sorumluNames.push(name);
                    }
                });
                
                if (sorumluNames.length > 0) {
                    sorumluCell += '<div class="bkm-responsible-users-elegant">';
                    sorumluNames.forEach(function(name, index) {
                        sorumluCell += '<div class="bkm-user-chip">';
                        sorumluCell += '<span class="bkm-user-avatar">' + name.charAt(0).toUpperCase() + '</span>';
                        sorumluCell += '<span class="bkm-user-name">' + name + '</span>';
                        sorumluCell += '</div>';
                        if (index < sorumluNames.length - 1) {
                            sorumluCell += '<div class="bkm-user-separator">•</div>';
                        }
                    });
                    sorumluCell += '</div>';
                } else {
                    sorumluCell += '-';
                }
            } else {
                sorumluCell += '-';
            }
            sorumluCell += '</td>';
            row.append(sorumluCell);
            
            // Kategori
            row.append('<td><span class="bkm-category-badge">' + (action.kategori_name || '-') + '</span></td>');
            
            // Tespit Konusu
            row.append('<td>' + (action.tespit_konusu || '-') + '</td>');
            
            // Önem
            var onemBadge = getOnemBadge(action.onem);
            row.append('<td>' + onemBadge + '</td>');
            
            // İlerleme
            var progressWrapper = '<div class="bkm-progress-container" style="background: #e9ecef; border-radius: 10px; height: 20px; width: 100%;">';
            progressWrapper += '<div class="bkm-progress-bar" style="width: ' + (action.ilerleyis || 0) + '%; height: 100%;"></div>';
            progressWrapper += '</div>';
            row.append('<td>' + progressWrapper + '</td>');
            
            // Durum
            var durumBadge = getDurumBadge(action.durum);
            row.append('<td>' + durumBadge + '</td>');
            
            // Görevler  
            var gorevBtn = '<button class="bkm-btn bkm-btn-info bkm-btn-sm" onclick="showActionTasks(' + action.id + ')">' +
                          '<i class="fas fa-tasks"></i> Detaylar</button> ' +
                          '<span class="bkm-task-count">Görevler (' + (action.task_count || 0) + ')</span>';
            row.append('<td>' + gorevBtn + '</td>');
            
            tbody.append(row);
        });
    }

    // Helper fonksiyonları
    function getUserDisplayName(userId) {
        userId = parseInt(userId);
        if (window.usersCache && window.usersCache[userId]) {
            return window.usersCache[userId].display_name;
        }
        return 'User ' + userId;
    }

    function getOnemBadge(onem) {
        var badges = {
            'YÜKSEK': '<span class="bkm-onem-badge yuksek">YÜKSEK</span>',
            'ORTA': '<span class="bkm-onem-badge orta">ORTA</span>',
            'DÜŞÜK': '<span class="bkm-onem-badge dusuk">DÜŞÜK</span>'
        };
        return badges[onem] || '<span class="bkm-onem-badge orta">ORTA</span>';
    }

    function getDurumBadge(durum) {
        var badges = {
            'ACİL': '<span class="bkm-durum-badge acil">ACİL</span>',
            'NORMAL': '<span class="bkm-durum-badge normal">NORMAL</span>',
            'BEKLEMEDE': '<span class="bkm-durum-badge beklemede">BEKLEMEDE</span>'
        };
        return badges[durum] || '<span class="bkm-durum-badge normal">NORMAL</span>';
    }

    // Dropdown refresh fonksiyonları
    function refreshCategoryDropdown() {
        console.log('🔄 Kategori dropdown ve liste yenileniyor...');
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_get_categories',
                nonce: bkmFrontend.nonce
            },
            success: function(response) {
                console.log('📂 Kategori listesi yanıtı:', response);
                if (response.success) {
                    // Update action form dropdown
                    var actionSelect = $('#action_kategori_id');
                    if (actionSelect.length > 0) {
                        var selectedValue = actionSelect.val();
                        actionSelect.empty();
                        actionSelect.append('<option value="">Seçiniz...</option>');
                        
                        $.each(response.data.categories, function(index, category) {
                            actionSelect.append('<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>');
                        });
                        
                        if (selectedValue) {
                            actionSelect.val(selectedValue);
                        }
                    }
                    
                    // Update other category dropdowns (if any)
                    var categorySelects = $('select[name="kategori_id"]:not(#action_kategori_id)');
                    categorySelects.each(function() {
                        var selectedValue = $(this).val();
                        $(this).empty();
                        $(this).append('<option value="">Kategori Seçin</option>');
                        
                        $.each(response.data.categories, function(index, category) {
                            $(this).append('<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>');
                        }.bind(this));
                        
                        if (selectedValue) {
                            $(this).val(selectedValue);
                        }
                    });
                    
                    // Update category list display
                    refreshCategoryList(response.data.categories);
                    
                    // Update filter dropdowns too
                    var filterSelect = $('#filter-kategori');
                    if (filterSelect.length > 0) {
                        var selectedValue = filterSelect.val();
                        filterSelect.empty();
                        filterSelect.append('<option value="">Tüm Kategoriler</option>');
                        
                        $.each(response.data.categories, function(index, category) {
                            filterSelect.append('<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>');
                        });
                        
                        if (selectedValue) {
                            filterSelect.val(selectedValue);
                        }
                    }
                    
                    console.log('✅ Kategori dropdown ve liste güncellendi');
                }
            },
            error: function() {
                console.error('❌ Kategori listesi güncellenirken hata oluştu');
            }
        });
    }
    
    function refreshCategoryList(categories) {
        var categoriesList = $('#categories-list');
        var categoriesHeader = $('#categories-header');
        // Her zaman başlığı sabit olarak ayarla, ardından güncel veriyle güncelle
        if (categoriesHeader.length) categoriesHeader.text('Mevcut Kategoriler' + (categories && categories.length ? ' (' + categories.length + ')' : ' (0)'));
        categoriesList.empty();
        if (!categories || categories.length === 0) {
            categoriesList.html('<div class="bkm-no-items">Henüz kategori eklenmemiş.</div>');
            return;
        }
        $.each(categories, function(index, category) {
            var html = '<div class="bkm-item" data-id="' + category.id + '">';
            html += '<div class="bkm-item-content"><strong>' + escapeHtml(category.name) + '</strong>';
            if (category.description && typeof category.description === 'string' && category.description.trim()) {
                html += '<p>' + escapeHtml(category.description) + '</p>';
            }
            html += '</div>';
            html += '<div class="bkm-item-actions">';
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editCategory(' + category.id + ', \'' + escapeJs(category.name) + '\', \'' + escapeJs(category.description || '') + '\')">✏️ Düzenle</button>';
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deleteCategory(' + category.id + ')">🗑️ Sil</button>';
            html += '</div></div>';
            categoriesList.append(html);
        });
    }
    
    function refreshPerformanceDropdown() {
        console.log('🔄 Performans dropdown ve liste yenileniyor...');
        $.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_get_performances',
                nonce: bkmFrontend.nonce
            },
            success: function(response) {
                console.log('🎯 Performans listesi yanıtı:', response);
                if (response.success) {
                    // Update action form dropdown
                    var actionSelect = $('#action_performans_id');
                    if (actionSelect.length > 0) {
                        var selectedValue = actionSelect.val();
                        actionSelect.empty();
                        actionSelect.append('<option value="">Seçiniz...</option>');
                        
                        $.each(response.data.performances, function(index, performance) {
                            actionSelect.append('<option value="' + performance.id + '">' + escapeHtml(performance.name) + '</option>');
                        });
                        
                        if (selectedValue) {
                            actionSelect.val(selectedValue);
                        }
                    }
                    
                    // Update other performance dropdowns (if any)
                    var performanceSelects = $('select[name="performans_id"]:not(#action_performans_id)');
                    performanceSelects.each(function() {
                        var selectedValue = $(this).val();
                        $(this).empty();
                        $(this).append('<option value="">Performans Seçin</option>');
                        
                        $.each(response.data.performances, function(index, performance) {
                            $(this).append('<option value="' + performance.id + '">' + escapeHtml(performance.name) + '</option>');
                        }.bind(this));
                        
                        if (selectedValue) {
                            $(this).val(selectedValue);
                        }
                    });
                    
                    // Update performance list display
                    refreshPerformanceList(response.data.performances);
                    
                    console.log('✅ Performans dropdown ve liste güncellendi');
                }
            },
            error: function() {
                console.error('❌ Performans listesi güncellenirken hata oluştu');
            }
        });
    }
    
    function refreshPerformanceList(performances) {
        console.log('📄 Performans listesi güncelleniyor...', performances);
        var performancesList = $('#performances-list');
        var performancesHeader = $('#settings-tab-performances .bkm-management-list h4');
        if (performancesList.length === 0) {
            console.log('⚠️ performances-list elementi bulunamadı');
            return;
        }
        performancesList.empty();
        if (!performances || performances.length === 0) {
            performancesList.html('<div class="bkm-no-items">Henüz performans eklenmemiş.</div>');
            if (performancesHeader.length) performancesHeader.text('Mevcut Performanslar (0)');
            return;
        }
        if (performancesHeader.length) performancesHeader.text('Mevcut Performanslar (' + performances.length + ')');
        $.each(performances, function(index, performance) {
            var html = '<div class="bkm-item" data-id="' + performance.id + '">';
            html += '<div class="bkm-item-content"><strong>' + escapeHtml(performance.name) + '</strong>';
            if (performance.description && typeof performance.description === 'string' && performance.description.trim()) {
                html += '<p>' + escapeHtml(performance.description) + '</p>';
            }
            html += '</div>';
            html += '<div class="bkm-item-actions">';
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-info" onclick="editPerformance(' + performance.id + ', \'' + escapeJs(performance.name) + '\', \'' + escapeJs(performance.description || '') + '\')">✏️ Düzenle</button>';
            html += '<button class="bkm-btn bkm-btn-small bkm-btn-danger" onclick="deletePerformance(' + performance.id + ')">🗑️ Sil</button>';
            html += '</div></div>';
            performancesList.append(html);
        });
    }
    
    // ===== AYARLAR PANELİ FONKSİYONLARI =====
    // Bu fonksiyonlar artık bkm-utils.js'den geliyor:
    // - toggleSettingsPanel()
    // - switchSettingsTab()
    // ===== AYARLAR PANELİ EVENT LISTENERS =====
    
    // Ayarlar paneli event listener'larını kur
    function setupSettingsEventListeners() {
        console.log('🔧 Ayarlar paneli event listener\'ları kuruluyor...');
        
        // Tab butonları click event
        $(document).off('click', '.settings-tab');
        $(document).on('click', '.settings-tab', function() {
            var tabName = $(this).data('tab');
            console.log('📂 Tab değiştiriliyor:', tabName);
            switchSettingsTab(tabName);
        });
        
        // Kullanıcı formu submit event
        $(document).off('submit', '#bkm-user-form-element');
        $(document).on('submit', '#bkm-user-form-element', handleUserFormSubmit);
        
        console.log('✅ Ayarlar paneli event listener\'ları kuruldu');
    }
    
    // Global fonksiyonları window objesine ekle
    window.toggleCategoriesPanel = toggleCategoriesPanel;
    window.togglePerformancesPanel = togglePerformancesPanel;
    window.toggleSettingsPanel = toggleSettingsPanel;
    window.switchSettingsTab = switchSettingsTab;
    window.clearCategoryForm = clearCategoryForm;
    window.clearPerformanceForm = clearPerformanceForm;
    window.clearUserForm = clearUserForm;
    window.clearCompanyForm = clearCompanyForm;
    window.clearAllSettingsForms = clearAllSettingsForms;
    window.editCategory = editCategory;
    window.editPerformance = editPerformance;
    window.editUser = editUser;
    window.deleteUser = deleteUser;
    window.loadUsers = loadUsers;
    window.refreshCategoryDropdown = refreshCategoryDropdown;
    window.refreshCategoryList = refreshCategoryList;
    window.refreshPerformanceDropdown = refreshPerformanceDropdown;
    window.refreshPerformanceList = refreshPerformanceList;
    window.refreshActions = refreshActions;
    window.updateActionsTable = updateActionsTable;
    window.displayUsers = displayUsers;
    window.handleUserFormSubmit = handleUserFormSubmit;
    window.clearUserForm = clearUserForm;
    window.setupSettingsEventListeners = setupSettingsEventListeners;
    
    // Document ready event handler
    $(document).ready(function() {
        console.log('📋 BKM Frontend JS yüklendi');
        console.log('✅ jQuery versiyonu:', $.fn.jquery);
        console.log('🎯 BKM Container:', $('.bkm-frontend-container').length > 0 ? 'Bulundu' : 'Bulunamadı');
        
        // CSS fix - WordPress tema çakışmalarını çöz
        $('head').append(`
            <style>
                .bkm-frontend-container { 
                    background: #f8f9fa !important; 
                    padding: 20px !important; 
                    margin: 0 auto !important; 
                    max-width: 1200px !important; 
                }
                .bkm-table { 
                    width: 100% !important; 
                    background: #fff !important; 
                    border-collapse: collapse !important; 
                    border-radius: 8px !important; 
                    overflow: hidden !important; 
                    box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important; 
                }
                .bkm-table th, .bkm-table td { 
                    padding: 12px 15px !important; 
                    border-bottom: 1px solid #e9ecef !important; 
                }
                .bkm-table th { 
                    background: #f8f9fa !important; 
                    font-weight: 600 !important; 
                }
                .bkm-btn { 
                    padding: 12px 24px !important; 
                    border-radius: 8px !important; 
                    border: none !important; 
                    cursor: pointer !important; 
                    font-size: 14px !important; 
                }
                .bkm-btn-primary { 
                    background: #007cba !important; 
                    color: #fff !important; 
                }
                .bkm-btn-warning { 
                    background: #ffc107 !important; 
                    color: #212529 !important; 
                }
                .bkm-dashboard-header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important; 
                    color: #fff !important; 
                    padding: 30px !important; 
                    border-radius: 8px !important; 
                    margin-bottom: 20px !important; 
                }
            </style>
        `);
        
        console.log('🎨 CSS düzeltmeleri uygulandı');
        
        // Ayarlar paneli için event listener'ları ekle
        if (typeof setupSettingsEventListeners === 'function') {
            setupSettingsEventListeners();
            console.log('⚙️ Ayarlar paneli event listener\'ları kuruldu');
        }
        
        // Toggle fonksiyonunu test et
        if (typeof toggleSettingsPanel === 'function') {
            console.log('✅ toggleSettingsPanel fonksiyonu hazır');
        } else {
            console.error('❌ toggleSettingsPanel fonksiyonu bulunamadı');
        }

        // Sayfa ilk yüklendiğinde kategoriler ve performanslar AJAX ile yüklensin
        if (typeof refreshCategoryDropdown === 'function') {
            refreshCategoryDropdown();
        }
        if (typeof refreshPerformanceDropdown === 'function') {
            refreshPerformanceDropdown();
        }
    });
    
// jQuery wrapper'ı kapat
})(jQuery);

// ===== GLOBAL FONKSİYONLARI WINDOW OBJESİNE EKLE =====
// Console hatalarını önlemek için tüm fonksiyonları global yapıyoruz

// Form toggle fonksiyonları
window.toggleTaskForm = window.toggleTaskForm || function() {
    console.log('🔧 toggleTaskForm çağrıldı (fallback)');
    jQuery('#bkm-task-form').slideToggle();
};

window.toggleActionForm = window.toggleActionForm || function() {
    console.log('🔧 toggleActionForm çağrıldı (fallback)');
    jQuery('#bkm-action-form').slideToggle();
};

// Ayarlar paneli fonksiyonları
window.toggleSettingsPanel = window.toggleSettingsPanel || function() {
    console.log('🔧 toggleSettingsPanel çağrıldı (fallback)');
    jQuery('#bkm-settings-panel').slideToggle();
};

window.switchSettingsTab = window.switchSettingsTab || function(tabName) {
    console.log('🔧 switchSettingsTab çağrıldı (fallback):', tabName);
};

// Aksiyon ve görev detay fonksiyonları
window.toggleTasks = window.toggleTasks || function(actionId) {
    console.log('🔧 toggleTasks çağrıldı (fallback):', actionId);
    jQuery('#tasks-' + actionId).slideToggle();
};

window.toggleActionDetails = window.toggleActionDetails || function(actionId) {
    console.log('🔧 toggleActionDetails çağrıldı (fallback):', actionId);
    jQuery('#details-' + actionId).slideToggle();
};

// Not fonksiyonları
window.toggleNoteForm = window.toggleNoteForm || function(taskId) {
    console.log('🔧 toggleNoteForm çağrıldı (fallback):', taskId);
    jQuery('#note-form-' + taskId).slideToggle();
};

window.toggleNotes = window.toggleNotes || function(taskId) {
    console.log('🔧 toggleNotes çağrıldı (fallback):', taskId);
    jQuery('#notes-' + taskId).slideToggle();
};

window.toggleReplyForm = window.toggleReplyForm || function(taskId, noteId) {
    console.log('🔧 toggleReplyForm çağrıldı (fallback):', taskId, noteId);
    jQuery('#reply-form-' + taskId + '-' + noteId).slideToggle();
};

// Yazdırma fonksiyonu
window.bkmPrintTable = window.bkmPrintTable || function() {
    console.log('🔧 bkmPrintTable çağrıldı (fallback)');
    window.print();
};

// Form temizleme fonksiyonları
window.clearActionForm = window.clearActionForm || function() {
    console.log('🔧 clearActionForm çağrıldı (fallback)');
    jQuery('#bkm-action-form-element')[0].reset();
};

// Ayarlar paneli yönetim fonksiyonları
window.clearCategoryForm = function() {
    console.log('🔧 clearCategoryForm çağrıldı');
    jQuery('#bkm-category-form-element')[0].reset();
};

window.clearPerformanceForm = function() {
    console.log('🔧 clearPerformanceForm çağrıldı');
    jQuery('#bkm-performance-form-element')[0].reset();
};

window.clearUserForm = function() {
    console.log('🔧 clearUserForm çağrıldı');
    jQuery('#bkm-user-form-element')[0].reset();
};

// Düzenleme fonksiyonları - ÇALIŞAN VERSİYON
window.editCategory = function(id, name, description) {
    console.log('🔧 editCategory çağrıldı:', id, name, description);
    var form = jQuery('#bkm-category-form-element');
    form.find('#category_name').val(name);
    form.find('#category_description').val(description);
    form.find('button[type="submit"]').text('✅ Kategori Güncelle');
    form.data('edit-id', id);
    
    // Form alanını highlight et
    form.find('#category_name').focus();
};

window.editPerformance = function(id, name, description) {
    console.log('🔧 editPerformance çağrıldı:', id, name, description);
    var form = jQuery('#bkm-performance-form-element');
    form.find('#performance_name').val(name);
    form.find('#performance_description').val(description);
    form.find('button[type="submit"]').text('✅ Performans Güncelle');
    form.data('edit-id', id);
    
    // Form alanını highlight et
    form.find('#performance_name').focus();
};

window.editUser = function(id, username, email, first_name, last_name, role) {
    console.log('🔧 editUser çağrıldı:', id, username, email, first_name, last_name, role);
    
    // Kullanıcı tabına geç
    switchSettingsTab('users');
    
    // Form'u bul ve doldur
    var form = jQuery('#bkm-user-form-element');
    if (form.length === 0) {
        console.error('❌ Kullanıcı formu bulunamadı!');
        return;
    }
    
    // Formu temizle
    clearUserForm();
    
    // Form alanlarını doldur
    form.find('#user_username').val(username).prop('disabled', true);
    form.find('#user_email').val(email);
    form.find('#user_first_name').val(first_name || '');
    form.find('#user_last_name').val(last_name || '');
    form.find('#user_role').val(role || '');
    form.find('#user_password').prop('required', false);
    
    // Form başlığını değiştir
    form.prev('h4').text('Kullanıcı Düzenle');
    form.find('button[type="submit"]').text('✅ Kullanıcı Güncelle');
    
    // Edit ID'yi form'a data olarak ekle
    form.data('edit-id', id);
    
    console.log('✅ Kullanıcı düzenleme formu hazırlandı');
};

window.deleteUser = function(id, name) {
    console.log('🔧 deleteUser çağrıldı:', id, name);
    
    if (!name) {
        name = 'Bu kullanıcı';
    }
    
    if (confirm('⚠️ "' + name + '" kullanıcısını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        // AJAX silme işlemi
        jQuery.ajax({
            url: bkmFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'bkm_delete_user',
                user_id: id,
                nonce: bkmFrontend.nonce
            },
            beforeSend: function() {
                console.log('🗑️ Kullanıcı siliniyor...');
                if (typeof showNotification === 'function') {
                    showNotification('Kullanıcı siliniyor...', 'info');
                }
            },
            success: function(response) {
                console.log('✅ Kullanıcı silme yanıtı:', response);
                
                if (response.success) {
                    if (typeof showNotification === 'function') {
                        showNotification('Kullanıcı başarıyla silindi!', 'success');
                    } else {
                        alert('✅ Kullanıcı başarıyla silindi!');
                    }
                    // Kullanıcı silme sonrası AJAX ile listeyi güncelle
                    // loadUsers(); // Geçici olarak kapatıldı - PHP listesi korunuyor
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Kullanıcı silinemedi';
                    if (typeof showNotification === 'function') {
                        showNotification('Hata: ' + errorMsg, 'error');
                    } else {
                        alert('❌ Hata: ' + errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX hatası:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Bağlantı hatası: ' + error, 'error');
                } else {
                    alert('❌ Bağlantı hatası: ' + error);
                }
            }
        });
    }
};

// ===== COMPANY SETTINGS MANAGEMENT =====

// Company form AJAX handler duplicate kaldırıldı - jQuery wrapper içindeki handler kullanılıyor

// Logo file input change handler
jQuery(document).on('change', '#company_logo', function() {
    var file = this.files[0];
    if (file) {
        // Validate file type
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (allowedTypes.indexOf(file.type) === -1) {
            alert('Sadece JPG, PNG ve GIF formatları desteklenmektedir.');
            this.value = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Dosya boyutu 2MB\'dan küçük olmalıdır.');
            this.value = '';
            return;
        }
        
        // Preview image
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = jQuery('#logo-preview');
            preview.html(
                '<img src="' + e.target.result + '" alt="Logo Önizleme" />' +
                '<button type="button" class="bkm-btn bkm-btn-danger bkm-btn-small bkm-remove-logo" onclick="clearNewLogoPreview()">' +
                '🗑️ Kaldır</button>'
            );
        };
        reader.readAsDataURL(file);
    }
});

// Update company info display
function updateCompanyInfoDisplay(companyInfo) {
    console.log('🏢 Firma bilgileri güncelleniyor:', companyInfo);
    var display = jQuery('#company-info-display');
    console.log('🏢 Display element bulundu:', display.length > 0);
    var html = '';
    
    if (companyInfo.name || companyInfo.address || companyInfo.phone || companyInfo.email || companyInfo.logo) {
        html += '<div class="bkm-company-header">';
        
        if (companyInfo.logo) {
            html += '<div class="bkm-company-logo-display">';
            html += '<img src="' + companyInfo.logo + '" alt="' + (companyInfo.name || 'Logo') + '" />';
            html += '</div>';
        }
        
        if (companyInfo.name) {
            html += '<h5>' + companyInfo.name + '</h5>';
        }
        
        html += '</div>';
        html += '<div class="bkm-company-details">';
        
        if (companyInfo.address) {
            html += '<p><strong>📍 Adres:</strong> ' + companyInfo.address + '</p>';
        }
        if (companyInfo.phone) {
            html += '<p><strong>📞 Telefon:</strong> ' + companyInfo.phone + '</p>';
        }
        if (companyInfo.email) {
            html += '<p><strong>📧 E-posta:</strong> ' + companyInfo.email + '</p>';
        }
        
        html += '</div>';
    } else {
        html = '<div class="bkm-no-company-info">';
        html += '<p><em>Henüz firma bilgileri eklenmemiş.</em></p>';
        html += '<p>Lütfen firma bilgilerini doldurun.</p>';
        html += '</div>';
    }
    
    console.log('🏢 Oluşturulan HTML:', html);
    display.html(html);
}

// Clear logo preview
function clearLogoPreview() {
    jQuery('#company_logo').val('');
    jQuery('#logo-preview').html(
        '<div class="bkm-logo-placeholder">' +
        '<i class="dashicons dashicons-camera"></i>' +
        '<p>Logo yüklemek için dosya seçin</p>' +
        '</div>'
    );
}

// Clear new logo preview and restore saved logo if exists
function clearNewLogoPreview() {
    jQuery('#company_logo').val('');
    
    // Check if there's a saved logo to restore
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        data: {
            action: 'bkm_get_company_info'
        },
        success: function(response) {
            if (response.success && response.data.company_info.logo) {
                // Restore saved logo
                jQuery('#logo-preview').html(
                    '<img src="' + response.data.company_info.logo + '" alt="Mevcut Logo" />' +
                    '<button type="button" class="bkm-btn bkm-btn-danger bkm-btn-small bkm-remove-logo" onclick="removeCompanyLogo()">' +
                    '🗑️ Logoyu Kaldır</button>'
                );
            } else {
                // No saved logo, show placeholder
                clearLogoPreview();
            }
        },
        error: function() {
            // Error getting info, show placeholder
            clearLogoPreview();
        }
    });
}

// Remove company logo
function removeCompanyLogo() {
    if (!confirm('Firma logosunu kaldırmak istediğinizden emin misiniz?')) {
        return;
    }
    
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        data: {
            action: 'bkm_remove_company_logo',
            nonce: bkmFrontend.nonce
        },
        success: function(response) {
            if (response.success) {
                if (typeof showNotification === 'function') {
                    showNotification(response.data.message, 'success');
                } else {
                    alert(response.data.message);
                }
                
                // Update logo preview
                clearLogoPreview();
                
                // Update company info display
                var form = jQuery('#bkm-company-form-element');
                var companyInfo = {
                    name: form.find('#company_name').val(),
                    address: form.find('#company_address').val(),
                    phone: form.find('#company_phone').val(),
                    email: form.find('#company_email').val(),
                    logo: ''
                };
                updateCompanyInfoDisplay(companyInfo);
            } else {
                alert('Hata: ' + response.data.message);
            }
        },
        error: function() {
            alert('Logo kaldırılırken bir hata oluştu.');
        }
    });
}

// Reset company form
function resetCompanyForm() {
    if (!confirm('Tüm alanları sıfırlamak istediğinizden emin misiniz?')) {
        return;
    }
    
    var form = jQuery('#bkm-company-form-element');
    form[0].reset();
    clearLogoPreview();
}

// Load company info on tab switch
function loadCompanyInfo() {
    console.log('📊 Firma bilgileri yükleniyor...');
    jQuery.ajax({
        url: bkmFrontend.ajax_url,
        type: 'POST',
        data: {
            action: 'bkm_get_company_info',
            nonce: bkmFrontend.nonce
        },
        success: function(response) {
            console.log('📊 Firma bilgileri AJAX yanıtı:', response);
            if (response.success && response.data && response.data.company_info) {
                var info = response.data.company_info;
                console.log('📊 Alınan firma bilgileri:', info);
                updateCompanyInfoDisplay(info);
                // Update form fields
                var form = jQuery('#bkm-company-form-element');
                form.find('#company_name').val(info.name || '');
                form.find('#company_address').val(info.address || '');
                form.find('#company_phone').val(info.phone || '');
                form.find('#company_email').val(info.email || '');
                // Update logo preview
                if (info.logo) {
                    jQuery('#logo-preview').html(
                        '<img src="' + info.logo + '" alt="Mevcut Logo" />' +
                        '<button type="button" class="bkm-btn bkm-btn-danger bkm-btn-small bkm-remove-logo" onclick="removeCompanyLogo()">' +
                        '🗑️ Logoyu Kaldır</button>'
                    );
                } else {
                    clearLogoPreview();
                }
            } else {
                console.log('❌ Firma bilgileri alınamadı:', response);
                updateCompanyInfoDisplay({});
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Firma bilgileri yükleme hatası:', error, xhr.responseText);
            updateCompanyInfoDisplay({});
        }
    });
}

// Make functions globally available
window.clearLogoPreview = clearLogoPreview;
window.clearNewLogoPreview = clearNewLogoPreview;
window.removeCompanyLogo = removeCompanyLogo;
window.resetCompanyForm = resetCompanyForm;
window.loadCompanyInfo = loadCompanyInfo;
window.updateCompanyInfoDisplay = updateCompanyInfoDisplay;

// Ensure all critical functions are globally available
window.toggleNotes = window.toggleNotes;
window.toggleNoteForm = window.toggleNoteForm;
window.toggleActionDetails = window.toggleActionDetails;
window.toggleTasks = window.toggleTasks;
window.toggleActionForm = window.toggleActionForm;
window.toggleTaskForm = window.toggleTaskForm;
window.toggleSettingsPanel = window.toggleSettingsPanel;
window.loadTaskNotes = window.loadTaskNotes;
window.toggleReplyForm = window.toggleReplyForm;

console.log('✅ Tüm global fonksiyonlar window objesine eklendi');
console.log('🔧 Mevcut global fonksiyonlar:', {
    toggleNotes: typeof window.toggleNotes,
    toggleNoteForm: typeof window.toggleNoteForm,
    toggleActionDetails: typeof window.toggleActionDetails,
    toggleTasks: typeof window.toggleTasks,
    toggleActionForm: typeof window.toggleActionForm,
    toggleTaskForm: typeof window.toggleTaskForm,
    showNotification: typeof window.showNotification,
    loadCompanyInfo: typeof window.loadCompanyInfo,
    updateCompanyInfoDisplay: typeof window.updateCompanyInfoDisplay,
    removeCompanyLogo: typeof window.removeCompanyLogo
});

// Filtreleri temizle fonksiyonu
function clearAllFilters() {
    jQuery('#filter-tanimlayan').val('');
    jQuery('#filter-sorumlu').val('');
    jQuery('#filter-kategori').val('');
    jQuery('#filter-onem').val('');
    jQuery('#filter-durum').val('');
    jQuery('.bkm-filter-select').trigger('change');
}
window.clearAllFilters = clearAllFilters;