<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('edit_posts')) {
    wp_die(__('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'bkm-aksiyon-takip'));
}

global $wpdb;

// Handle form submission
if (isset($_POST['submit_action']) && wp_verify_nonce($_POST['bkm_nonce'], 'bkm_add_action')) {
    $tanımlayan_id = intval($_POST['tanımlayan_id']);
    $onem_derecesi = intval($_POST['onem_derecesi']);
    $acilma_tarihi = sanitize_text_field($_POST['acilma_tarihi']);
    $hafta = intval($_POST['hafta']);
    $kategori_id = intval($_POST['kategori_id']);
    $sorumlu_ids = isset($_POST['sorumlu_ids']) ? implode(',', array_map('intval', $_POST['sorumlu_ids'])) : '';
    $tespit_konusu = sanitize_textarea_field($_POST['tespit_konusu']);
    $aciklama = sanitize_textarea_field($_POST['aciklama']);
    $hedef_tarih = sanitize_text_field($_POST['hedef_tarih']);
    $kapanma_tarihi = !empty($_POST['kapanma_tarihi']) ? sanitize_text_field($_POST['kapanma_tarihi']) : null;
    $performans_id = intval($_POST['performans_id']);
    $ilerleme_durumu = intval($_POST['ilerleme_durumu']);
    $notlar = sanitize_textarea_field($_POST['notlar']);
    
    $actions_table = $wpdb->prefix . 'bkm_actions';
    
    $result = $wpdb->insert(
        $actions_table,
        array(
            'tanımlayan_id' => $tanımlayan_id,
            'onem_derecesi' => $onem_derecesi,
            'acilma_tarihi' => $acilma_tarihi,
            'hafta' => $hafta,
            'kategori_id' => $kategori_id,
            'sorumlu_ids' => $sorumlu_ids,
            'tespit_konusu' => $tespit_konusu,
            'aciklama' => $aciklama,
            'hedef_tarih' => $hedef_tarih,
            'kapanma_tarihi' => $kapanma_tarihi,
            'performans_id' => $performans_id,
            'ilerleme_durumu' => $ilerleme_durumu,
            'notlar' => $notlar
        ),
        array('%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
    );
    
    if ($result !== false) {
        $action_id = $wpdb->insert_id;
        
        // Send email notifications
        $plugin = BKM_Aksiyon_Takip::get_instance();
        $tanımlayan_user = get_user_by('ID', $tanımlayan_id);
        $kategori = $wpdb->get_row($wpdb->prepare("SELECT name FROM $categories_table WHERE id = %d", $kategori_id));
        
        $notification_data = array(
            'id' => $action_id,
            'tanımlayan' => $tanımlayan_user ? $tanımlayan_user->display_name : 'Bilinmiyor',
            'kategori' => $kategori ? $kategori->name : 'Bilinmiyor',
            'aciklama' => $aciklama
        );
        if ($tanımlayan_user && !empty($tanımlayan_user->user_email)) {
            $notification_data['tanımlayan_email'] = $tanımlayan_user->user_email;
        }
        
        // Get responsible users' emails
        if (!empty($sorumlu_ids)) {
            $sorumlu_user_ids = explode(',', $sorumlu_ids);
            $sorumlu_emails = array();
            foreach ($sorumlu_user_ids as $user_id) {
                $user = get_user_by('ID', trim($user_id));
                if ($user) {
                    $sorumlu_emails[] = $user->user_email;
                }
            }
            $notification_data['sorumlu_emails'] = $sorumlu_emails;
        }
        
        $plugin->send_email_notification('action_created', $notification_data);
        
        echo '<div class="notice notice-success"><p>Aksiyon başarıyla eklendi!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Aksiyon eklenirken bir hata oluştu.</p></div>';
    }
}

// Get users for dropdown
$users = get_users(array('role__in' => array('administrator', 'editor', 'author', 'contributor')));

// Get categories
$categories_table = $wpdb->prefix . 'bkm_categories';
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name");

// Get performance data
$performance_table = $wpdb->prefix . 'bkm_performances';
$performances = $wpdb->get_results("SELECT * FROM $performance_table ORDER BY name");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="" class="bkm-form">
        <?php wp_nonce_field('bkm_add_action', 'bkm_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tanımlayan_id">Aksiyonu Tanımlayan *</label>
                </th>
                <td>
                    <select name="tanımlayan_id" id="tanımlayan_id" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="onem_derecesi">Aksiyon Önem Derecesi *</label>
                </th>
                <td>
                    <select name="onem_derecesi" id="onem_derecesi" required>
                        <option value="">Seçiniz...</option>
                        <option value="1">1 - Düşük</option>
                        <option value="2">2 - Orta</option>
                        <option value="3">3 - Yüksek</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acilma_tarihi">Aksiyon Açılma Tarihi *</label>
                </th>
                <td>
                    <input type="text" name="acilma_tarihi" id="acilma_tarihi" class="bkm-datepicker" required />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hafta">Hafta *</label>
                </th>
                <td>
                    <input type="number" name="hafta" id="hafta" min="1" max="53" required />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="kategori_id">Kategori *</label>
                </th>
                <td>
                    <select name="kategori_id" id="kategori_id" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category->id; ?>"><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="sorumlu_ids">Aksiyon Sorumlusu *</label>
                </th>
                <td>
                    <select name="sorumlu_ids[]" id="sorumlu_ids" multiple class="bkm-multi-select" required>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Ctrl tuşuna basarak birden fazla kullanıcı seçebilirsiniz.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="tespit_konusu">Aksiyon Tespitine Neden Olan Konu *</label>
                </th>
                <td>
                    <textarea name="tespit_konusu" id="tespit_konusu" rows="3" cols="50" required></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="aciklama">Aksiyon Açıklaması *</label>
                </th>
                <td>
                    <textarea name="aciklama" id="aciklama" rows="5" cols="50" required></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="hedef_tarih">Hedef Tarih *</label>
                </th>
                <td>
                    <input type="text" name="hedef_tarih" id="hedef_tarih" class="bkm-datepicker" required />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="kapanma_tarihi">Aksiyon Kapanma Tarihi</label>
                </th>
                <td>
                    <input type="text" name="kapanma_tarihi" id="kapanma_tarihi" class="bkm-datepicker" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="performans_id">Performans *</label>
                </th>
                <td>
                    <select name="performans_id" id="performans_id" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach ($performances as $performance): ?>
                            <option value="<?php echo $performance->id; ?>"><?php echo esc_html($performance->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ilerleme_durumu">İlerleme Durumu (%) *</label>
                </th>
                <td>
                    <input type="number" name="ilerleme_durumu" id="ilerleme_durumu" min="0" max="100" value="0" required />
                    <div class="bkm-progress-preview">
                        <div class="bkm-progress">
                            <div class="bkm-progress-bar" id="progress-preview" style="width: 0%"></div>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notlar">Notlar</label>
                </th>
                <td>
                    <textarea name="notlar" id="notlar" rows="5" cols="50"></textarea>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Aksiyon Ekle', 'primary', 'submit_action'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize datepickers
    $('.bkm-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });
    
    // Progress bar preview
    $('#ilerleme_durumu').on('input', function() {
        var value = $(this).val();
        $('#progress-preview').css('width', value + '%');
    });
});
</script>