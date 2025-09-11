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

$actions_table = $wpdb->prefix . 'bkm_actions';
$tasks_table = $wpdb->prefix . 'bkm_tasks';
$categories_table = $wpdb->prefix . 'bkm_categories';
$performance_table = $wpdb->prefix . 'bkm_performances';

// Get statistics for charts
$action_stats = $wpdb->get_results(
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN kapanma_tarihi IS NULL THEN 1 END) as open,
        COUNT(CASE WHEN kapanma_tarihi IS NOT NULL THEN 1 END) as closed
     FROM $actions_table"
);

$priority_stats = $wpdb->get_results(
    "SELECT 
        onem_derecesi,
        COUNT(*) as count
     FROM $actions_table 
     GROUP BY onem_derecesi 
     ORDER BY onem_derecesi"
);

$category_stats = $wpdb->get_results(
    "SELECT 
        c.name,
        COUNT(a.id) as count
     FROM $categories_table c 
     LEFT JOIN $actions_table a ON c.id = a.kategori_id 
     GROUP BY c.id, c.name 
     ORDER BY count DESC"
);

$performance_stats = $wpdb->get_results(
    "SELECT 
        p.name,
        COUNT(a.id) as count
     FROM $performance_table p 
     LEFT JOIN $actions_table a ON p.id = a.performans_id 
     GROUP BY p.id, p.name 
     ORDER BY count DESC"
);

// User performance stats
$user_stats = $wpdb->get_results(
    "SELECT 
        u.display_name,
        COUNT(a.id) as total_actions,
        COUNT(CASE WHEN a.kapanma_tarihi IS NOT NULL THEN 1 END) as completed_actions,
        AVG(CASE WHEN a.kapanma_tarihi IS NOT NULL THEN DATEDIFF(a.kapanma_tarihi, a.acilma_tarihi) END) as avg_completion_days
     FROM {$wpdb->users} u 
     LEFT JOIN $actions_table a ON FIND_IN_SET(u.ID, a.sorumlu_ids) > 0
     WHERE u.ID IN (
         SELECT DISTINCT tanımlayan_id FROM $actions_table
         UNION
         SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(sorumlu_ids, ',', numbers.n), ',', -1) as user_id
         FROM $actions_table
         CROSS JOIN (
             SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
             SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL 
             SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
         ) numbers
         WHERE CHAR_LENGTH(sorumlu_ids) - CHAR_LENGTH(REPLACE(sorumlu_ids, ',', '')) >= numbers.n - 1
     )
     GROUP BY u.ID, u.display_name 
     HAVING total_actions > 0
     ORDER BY total_actions DESC"
);

// Monthly action trends
$monthly_stats = $wpdb->get_results(
    "SELECT 
        DATE_FORMAT(acilma_tarihi, '%Y-%m') as month,
        COUNT(*) as total,
        COUNT(CASE WHEN kapanma_tarihi IS NOT NULL THEN 1 END) as completed
     FROM $actions_table 
     WHERE acilma_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(acilma_tarihi, '%Y-%m') 
     ORDER BY month"
);

// Task completion stats
$task_stats = $wpdb->get_results(
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN tamamlandi = 1 THEN 1 END) as completed,
        AVG(ilerleme_durumu) as avg_progress
     FROM $tasks_table"
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="bkm-reports-page">
        <!-- Summary Cards -->
        <div class="bkm-stats-grid">
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $action_stats[0]->total; ?></div>
                <div class="bkm-stat-label">Toplam Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $action_stats[0]->open; ?></div>
                <div class="bkm-stat-label">Açık Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $action_stats[0]->closed; ?></div>
                <div class="bkm-stat-label">Kapalı Aksiyon</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $task_stats[0]->total; ?></div>
                <div class="bkm-stat-label">Toplam Görev</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo $task_stats[0]->completed; ?></div>
                <div class="bkm-stat-label">Tamamlanan Görev</div>
            </div>
            
            <div class="bkm-stat-card">
                <div class="bkm-stat-number"><?php echo round($task_stats[0]->avg_progress, 1); ?>%</div>
                <div class="bkm-stat-label">Ortalama İlerleme</div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="bkm-charts-grid">
            <!-- Action Status Chart -->
            <div class="bkm-chart-container">
                <h3>Aksiyon Durumları</h3>
                <canvas id="actionStatusChart"></canvas>
            </div>
            
            <!-- Priority Distribution Chart -->
            <div class="bkm-chart-container">
                <h3>Önem Derecesi Dağılımı</h3>
                <canvas id="priorityChart"></canvas>
            </div>
            
            <!-- Category Distribution Chart -->
            <div class="bkm-chart-container">
                <h3>Kategori Dağılımı</h3>
                <canvas id="categoryChart"></canvas>
            </div>
            
            <!-- Performance Distribution Chart -->
            <div class="bkm-chart-container">
                <h3>Performans Dağılımı</h3>
                <canvas id="performanceChart"></canvas>
            </div>
            
            <!-- Monthly Trends Chart -->
            <div class="bkm-chart-container bkm-chart-wide">
                <h3>Aylık Aksiyon Trendleri</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- User Performance Table -->
        <div class="bkm-user-performance">
            <h3>Kullanıcı Performans Raporu</h3>
            <div class="bkm-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Toplam Aksiyon</th>
                            <th>Tamamlanan Aksiyon</th>
                            <th>Tamamlama Oranı</th>
                            <th>Ortalama Tamamlama Süresi (Gün)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($user_stats): ?>
                            <?php foreach ($user_stats as $user): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                                    <td><?php echo $user->total_actions; ?></td>
                                    <td><?php echo $user->completed_actions; ?></td>
                                    <td>
                                        <?php 
                                        $completion_rate = $user->total_actions > 0 ? ($user->completed_actions / $user->total_actions) * 100 : 0;
                                        ?>
                                        <div class="bkm-progress">
                                            <div class="bkm-progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                                            <span class="bkm-progress-text"><?php echo round($completion_rate, 1); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $user->avg_completion_days ? round($user->avg_completion_days, 1) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Henüz kullanıcı performans verisi bulunmamaktadır.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
jQuery(document).ready(function($) {
    // Action Status Chart
    var actionStatusCtx = document.getElementById('actionStatusChart').getContext('2d');
    new Chart(actionStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Açık', 'Kapalı'],
            datasets: [{
                data: [<?php echo $action_stats[0]->open; ?>, <?php echo $action_stats[0]->closed; ?>],
                backgroundColor: ['#ff6b6b', '#4ecdc4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Priority Distribution Chart
    var priorityCtx = document.getElementById('priorityChart').getContext('2d');
    new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($priority_stats as $priority): ?>
                    '<?php echo $priority->onem_derecesi == 1 ? "Düşük" : ($priority->onem_derecesi == 2 ? "Orta" : "Yüksek"); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Aksiyon Sayısı',
                data: [
                    <?php foreach ($priority_stats as $priority): ?>
                        <?php echo $priority->count; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: ['#51cf66', '#ffd43b', '#ff6b6b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Category Distribution Chart
    var categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php foreach ($category_stats as $category): ?>
                    '<?php echo esc_js($category->name); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($category_stats as $category): ?>
                        <?php echo $category->count; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b',
                    '#eb4d4b', '#6c5ce7', '#a29bfe', '#fd79a8', '#e17055'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Performance Distribution Chart
    var performanceCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(performanceCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($performance_stats as $performance): ?>
                    '<?php echo esc_js($performance->name); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Aksiyon Sayısı',
                data: [
                    <?php foreach ($performance_stats as $performance): ?>
                        <?php echo $performance->count; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: '#74b9ff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Monthly Trends Chart
    var monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [
                <?php foreach ($monthly_stats as $month): ?>
                    '<?php echo date('M Y', strtotime($month->month . '-01')); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Toplam Aksiyon',
                data: [
                    <?php foreach ($monthly_stats as $month): ?>
                        <?php echo $month->total; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#74b9ff',
                backgroundColor: 'rgba(116, 185, 255, 0.1)',
                tension: 0.4
            }, {
                label: 'Tamamlanan Aksiyon',
                data: [
                    <?php foreach ($monthly_stats as $month): ?>
                        <?php echo $month->completed; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '#00b894',
                backgroundColor: 'rgba(0, 184, 148, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>