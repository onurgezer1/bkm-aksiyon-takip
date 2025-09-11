/**
 * BKM Aksiyon Takip - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize datepickers
    if ($.fn.datepicker) {
        $('.bkm-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '2020:2030'
        });
    }
    
    // Progress bar preview
    $('#ilerleme_durumu').on('input', function() {
        var value = $(this).val();
        $('#progress-preview .bkm-progress-bar').css('width', value + '%');
    });
    
    // Multi-select styling
    $('.bkm-multi-select').css({
        'height': '100px',
        'resize': 'vertical'
    });
    
    // Confirm delete actions
    $('.button-link-delete').on('click', function(e) {
        if (!confirm('Bu öğeyi silmek istediğinizden emin misiniz?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-calculate week number from date
    $('#acilma_tarihi').on('change', function() {
        var date = new Date($(this).val());
        if (date) {
            var weekNum = getWeekNumber(date);
            $('#hafta').val(weekNum);
        }
    });
    
    // Form validation
    $('form.bkm-form').on('submit', function(e) {
        var isValid = true;
        var form = $(this);
        
        // Check required fields
        form.find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val()) {
                $(this).css('border-color', '#dc3545');
                isValid = false;
            } else {
                $(this).css('border-color', '#ced4da');
            }
        });
        
        // Check date validity
        form.find('input[type="date"], .bkm-datepicker').each(function() {
            var dateValue = $(this).val();
            if (dateValue && !isValidDate(dateValue)) {
                $(this).css('border-color', '#dc3545');
                isValid = false;
            }
        });
        
        // Check progress percentage
        var progress = form.find('#ilerleme_durumu').val();
        if (progress && (progress < 0 || progress > 100)) {
            form.find('#ilerleme_durumu').css('border-color', '#dc3545');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Lütfen tüm gerekli alanları doğru şekilde doldurun.', 'error');
            return false;
        }
    });
    
    // Chart responsiveness
    if (typeof Chart !== 'undefined') {
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
    }
    
    // Auto-refresh stats every 5 minutes
    if ($('.bkm-dashboard').length > 0) {
        setInterval(function() {
            refreshDashboardStats();
        }, 300000); // 5 minutes
    }
    
    // Helper functions
    function getWeekNumber(date) {
        var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
        return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
    }
    
    function isValidDate(dateString) {
        var regEx = /^\d{4}-\d{2}-\d{2}$/;
        if(!dateString.match(regEx)) return false;
        var d = new Date(dateString);
        var dNum = d.getTime();
        if(!dNum && dNum !== 0) return false;
        return d.toISOString().slice(0,10) === dateString;
    }
    
    // showNotification fonksiyonu artık bkm-utils.js'den geliyor
    // Admin paneli için özel notification stili kullanılıyor
    
    function refreshDashboardStats() {
        if (typeof bkmAjax !== 'undefined') {
            $.ajax({
                url: bkmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_refresh_stats',
                    nonce: bkmAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update stats cards
                        $('.bkm-stat-card .bkm-stat-number').each(function(index) {
                            if (response.data.stats && response.data.stats[index]) {
                                $(this).text(response.data.stats[index]);
                            }
                        });
                    }
                }
            });
        }
    }
    
    // AJAX handlers
    if (typeof bkmAjax !== 'undefined') {
        
        // Delete category/performance via AJAX
        $('.ajax-delete').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var itemType = button.data('type');
            var itemId = button.data('id');
            
            if (!confirm('Bu ' + itemType + ' silmek istediğinizden emin misiniz?')) {
                return;
            }
            
            button.prop('disabled', true).text('Siliniyor...');
            
            $.ajax({
                url: bkmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkm_delete_item',
                    type: itemType,
                    id: itemId,
                    nonce: bkmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                        showNotification(response.data.message, 'success');
                    } else {
                        showNotification(response.data.message, 'error');
                        button.prop('disabled', false).text('Sil');
                    }
                },
                error: function() {
                    showNotification('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
                    button.prop('disabled', false).text('Sil');
                }
            });
        });
    }
    
    // Table search functionality
    $('#bkm-table-search').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.bkm-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // Export functionality
    $('.bkm-export-btn').on('click', function(e) {
        e.preventDefault();
        
        var exportType = $(this).data('type');
        var tableData = [];
        
        $('.bkm-table thead tr th').each(function() {
            tableData.push($(this).text());
        });
        
        $('.bkm-table tbody tr:visible').each(function() {
            var rowData = [];
            $(this).find('td').each(function() {
                rowData.push($(this).text().trim());
            });
            tableData.push(rowData);
        });
        
        if (exportType === 'csv') {
            exportToCSV(tableData);
        } else if (exportType === 'excel') {
            exportToExcel(tableData);
        }
    });
    
    function exportToCSV(data) {
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                return '"' + (cell || '').replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\n');
        
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'bkm-aksiyon-takip-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+N for new action
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'admin.php?page=bkm-aksiyon-ekle';
        }
        
        // Ctrl+R for reports
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            window.location.href = 'admin.php?page=bkm-raporlar';
        }
    });
});

// Global functions
function bkmConfirmDelete(itemName) {
    return confirm('Bu ' + itemName + ' silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');
}

function bkmToggleSection(sectionId) {
    jQuery('#' + sectionId).slideToggle();
}