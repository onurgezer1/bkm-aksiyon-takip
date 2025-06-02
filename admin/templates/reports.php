<?php
global $wpdb;

// Rapor verilerini hazırla
$total_aksiyonlar = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar");
$acik_aksiyonlar = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bkm_aksiyonlar WHERE kapanma_tarihi IS NULL OR kapanma_tarihi = ''");
$tamamlanan_aksiyonlar = $total_aksiyonlar - $acik_aksiyonlar;

// Kategori bazında dağılım
$kategori_dagilim = $wpdb->get_results("
    SELECT k.kategori_adi, COUNT(a.id) as sayi
    FROM {$wpdb->prefix}bkm_kategoriler k
    LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON k.id = a.kategori_id
    GROUP BY k.id, k.kategori_adi
    ORDER BY sayi DESC
");

// Kullanıcı performansı
$kullanici_performans = $wpdb->get_results("
    SELECT u.display_name, 
           COUNT(a.id) as toplam_aksiyon,
           COUNT(CASE WHEN a.kapanma_tarihi IS NOT NULL AND a.kapanma_tarihi != '' THEN 1 END) as tamamlanan,
           AVG(a.ilerleme_durumu) as ortalama_ilerleme
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->prefix}bkm_aksiyonlar a ON u.ID = a.aksiyonu_tanimlayan
    WHERE u.ID IN (SELECT DISTINCT aksiyonu_tanimlayan FROM {$wpdb->prefix}bkm_aksiyonlar)
    GROUP BY u.ID, u.display_name
    ORDER BY toplam_aksiyon DESC
");

// Önem derecesi dağılımı
$onem_dagilim = $wpdb->get_results("
    SELECT onem_derecesi, COUNT(*) as sayi
    FROM {$wpdb->prefix}bkm_aksiyonlar
    GROUP BY onem_derecesi
    ORDER BY onem_derecesi
");

// Geciken aksiyonlar
$geciken_aksiyonlar = $wpdb->get_results("
    SELECT a.*, u.display_name, k.kategori_adi
    FROM {$wpdb->prefix}bkm_aksiyonlar a
    LEFT JOIN {$wpdb->users} u ON a.aksiyonu_tanimlayan = u.ID
    LEFT JOIN {$wpdb->prefix}bkm_kategoriler k ON a.kategori_id = k.id
    WHERE (a.kapanma_tarihi IS NULL OR a.kapanma_tarihi = '') 
    AND a.hedef_tarih < CURDATE()
    ORDER BY a.hedef_tarih ASC
");
?>

<div class="wrap">
    <h1>Raporlar ve Analizler</h1>

    <!-- Özet Kartları -->
    <div class="bkm-stats-cards">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_aksiyonlar; ?></h3>
                <p>Toplam Aksiyon</p>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $tamamlanan_aksiyonlar; ?></h3>
                <p>Tamamlanan</p>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $acik_aksiyonlar; ?></h3>
                <p>Açık Aksiyonlar</p>
            </div>
        </div>

        <div class="stat-card danger">
            <div class="stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo count($geciken_aksiyonlar); ?></h3>
                <p>Geciken</p>
            </div>
        </div>
    </div>

    <div class="bkm-reports-grid">
        <!-- Grafik Alanları -->
        <div class="bkm-report-card">
            <h2>Kategori Dağılımı</h2>
            <canvas id="kategoriChart" width="400" height="200"></canvas>
        </div>

        <div class="bkm-report-card">
            <h2>Önem Derecesi Dağılımı</h2>
            <canvas id="onemChart" width="400" height="200"></canvas>
        </div>

        <div class="bkm-report-card">
            <h2>Aylık İlerleme Trendi</h2>
            <canvas id="trendChart" width="400" height="200"></canvas>
        </div>

        <div class="bkm-report-card">
            <h2>Tamamlanma Oranı</h2>
            <div class="completion-rate">
                <?php 
                $tamamlanma_orani = $total_aksiyonlar > 0 ? round(($tamamlanan_aksiyonlar / $total_aksiyonlar) * 100, 1) : 0;
                ?>
                <div class="circular-progress" data-percentage="<?php echo $tamamlanma_orani; ?>">
                    <span class="percentage"><?php echo $tamamlanma_orani; ?>%</span>
                </div>
                <p>Genel Tamamlanma Oranı</p>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Performans Tablosu -->
    <div class="bkm-report-card full-width">
        <h2>Kullanıcı Performans Analizi</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Toplam Aksiyon</th>
                    <th>Tamamlanan</th>
                    <th>Tamamlanma Oranı</th>
                    <th>Ortalama İlerleme</th>
                    <th>Performans</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($kullanici_performans as $performans): ?>
                <?php 
                $tamamlanma_orani = $performans->toplam_aksiyon > 0 ? 
                    round(($performans->tamamlanan / $performans->toplam_aksiyon) * 100, 1) : 0;
                $ortalama_ilerleme = round($performans->ortalama_ilerleme, 1);
                
                // Performans seviyesi belirleme
                $performans_seviye = 'düşük';
                $performans_class = 'danger';
                if($tamamlanma_orani >= 80) {
                    $performans_seviye = 'yüksek';
                    $performans_class = 'success';
                } elseif($tamamlanma_orani >= 60) {
                    $performans_seviye = 'orta';
                    $performans_class = 'warning';
                }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($performans->display_name); ?></strong></td>
                    <td><?php echo $performans->toplam_aksiyon; ?></td>
                    <td><?php echo $performans->tamamlanan; ?></td>
                    <td>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $performans_class; ?>" 
                                     style="width: <?php echo $tamamlanma_orani; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $tamamlanma_orani; ?>%</span>
                        </div>
                    </td>
                    <td><?php echo $ortalama_ilerleme; ?>%</td>
                    <td>
                        <span class="performance-badge <?php echo $performans_class; ?>">
                            <?php echo ucfirst($performans_seviye); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Geciken Aksiyonlar -->
    <?php if(!empty($geciken_aksiyonlar)): ?>
    <div class="bkm-report-card full-width">
        <h2>Geciken Aksiyonlar (<?php echo count($geciken_aksiyonlar); ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Sıra No</th>
                    <th>Aksiyon</th>
                    <th>Tanımlayan</th>
                    <th>Kategori</th>
                    <th>Hedef Tarih</th>
                    <th>Gecikme (Gün)</th>
                    <th>İlerleme</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($geciken_aksiyonlar as $aksiyon): ?>
                <?php 
                $gecikme_gun = floor((time() - strtotime($aksiyon->hedef_tarih)) / (60 * 60 * 24));
                ?>
                <tr>
                    <td><?php echo $aksiyon->sira_no; ?></td>
                    <td><?php echo wp_trim_words($aksiyon->aksiyon_aciklamasi, 8); ?></td>
                    <td><?php echo esc_html($aksiyon->display_name); ?></td>
                    <td><?php echo esc_html($aksiyon->kategori_adi); ?></td>
                    <td class="text-danger">
                        <?php echo date('d.m.Y', strtotime($aksiyon->hedef_tarih)); ?>
                    </td>
                    <td class="text-danger">
                        <strong><?php echo $gecikme_gun; ?> gün</strong>
                    </td>
                    <td>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $aksiyon->ilerleme_durumu; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $aksiyon->ilerleme_durumu; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
// Chart.js verileri hazırla
const kategoriData = {
    labels: [<?php echo "'" . implode("','", array_column($kategori_dagilim, 'kategori_adi')) . "'"; ?>],
    datasets: [{
        data: [<?php echo implode(',', array_column($kategori_dagilim, 'sayi')); ?>],
        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
    }]
};

const onemData = {
    labels: ['Düşük (1)', 'Orta (2)', 'Yüksek (3)'],
    datasets: [{
        data: [
            <?php 
            $onem_array = array_column($onem_dagilim, 'sayi', 'onem_derecesi');
            echo ($onem_array[1] ?? 0) . ',' . ($onem_array[2] ?? 0) . ',' . ($onem_array[3] ?? 0);
            ?>
        ],
        backgroundColor: ['#4CAF50', '#FF9800', '#F44336']
    }]
};
</script>