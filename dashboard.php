<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/task_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$current_page_for_redirect = basename($_SERVER['SCRIPT_NAME']);

// --- DATA UNTUK STATUS TUGAS (Progress Circles) ---
$total_tasks_all_time = 0; $completed_all_time = 0; $in_progress_all_time = 0; $not_started_all_time = 0;
if (isset($conn) && $conn instanceof mysqli) {
    // Query untuk statistik masih mengambil semua tugas (termasuk yang overdue)
    $stmt_stats_all = $conn->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status");
    if ($stmt_stats_all) {
        $stmt_stats_all->bind_param("i", $user_id);
        if ($stmt_stats_all->execute()) {
            $result_stats_all = $stmt_stats_all->get_result();
            if ($result_stats_all) {
                while ($row_stat_all = $result_stats_all->fetch_assoc()) {
                    $total_tasks_all_time += (int)$row_stat_all['count'];
                    if ($row_stat_all['status'] == 'Completed') $completed_all_time = (int)$row_stat_all['count'];
                    if ($row_stat_all['status'] == 'In Progress') $in_progress_all_time = (int)$row_stat_all['count'];
                    if ($row_stat_all['status'] == 'Not Started') $not_started_all_time = (int)$row_stat_all['count'];
                }
            }
        }
        $stmt_stats_all->close();
    }
}
$progress_circles_data = [
    'Selesai' => ['count' => $completed_all_time, 'percent' => ($total_tasks_all_time > 0) ? round(($completed_all_time / $total_tasks_all_time) * 100) : 0, 'color' => '#4caf50'],
    'Dikerjakan' => ['count' => $in_progress_all_time, 'percent' => ($total_tasks_all_time > 0) ? round(($in_progress_all_time / $total_tasks_all_time) * 100) : 0, 'color' => '#2196f3'],
    'Belum Mulai' => ['count' => $not_started_all_time, 'percent' => ($total_tasks_all_time > 0) ? round(($not_started_all_time / $total_tasks_all_time) * 100) : 0, 'color' => '#f44336'],
];

// --- DATA UNTUK DIAGRAM GARIS (Performa Pengerjaan) ---
// ... (Logika chart performa sama seperti sebelumnya) ...
$today_for_default_perf = new DateTimeImmutable();
$performance_start_date_val = $today_for_default_perf->modify('-6 days')->format('Y-m-d');
$performance_end_date_val = $today_for_default_perf->format('Y-m-d');
$active_preset_perf_val = 'last7days';

if (isset($_GET['filterPerformanceSubmit'])) {
    if (isset($_GET['filterPerformanceDateRange']) && !empty(trim($_GET['filterPerformanceDateRange']))) {
        $range_perf = explode(' - ', $_GET['filterPerformanceDateRange']);
        if (count($range_perf) >= 1) {
            $date_start_parts_perf = explode('/', trim($range_perf[0]));
            if (count($date_start_parts_perf) == 3 && checkdate((int)$date_start_parts_perf[1], (int)$date_start_parts_perf[0], (int)$date_start_parts_perf[2])) {
                $performance_start_date_val = $date_start_parts_perf[2] . '-' . $date_start_parts_perf[1] . '-' . $date_start_parts_perf[0];
            }
            if (count($range_perf) == 2) {
                $date_end_parts_perf = explode('/', trim($range_perf[1]));
                 if (count($date_end_parts_perf) == 3 && checkdate((int)$date_end_parts_perf[1], (int)$date_end_parts_perf[0], (int)$date_end_parts_perf[2])) {
                    $performance_end_date_val = $date_end_parts_perf[2] . '-' . $date_end_parts_perf[1] . '-' . $date_end_parts_perf[0];
                }
            } else { 
                if (DateTime::createFromFormat('Y-m-d', $performance_start_date_val)) { 
                    $performance_end_date_val = $performance_start_date_val;
                }
            }
        }
    }
} elseif (isset($_GET['range_type_perf'])) {
    $active_preset_perf_val = $_GET['range_type_perf'];
    $today_for_preset = new DateTimeImmutable();
    switch ($active_preset_perf_val) {
        case 'today': $performance_start_date_val = $today_for_preset->format('Y-m-d'); $performance_end_date_val = $today_for_preset->format('Y-m-d'); break;
        case 'last7days': $performance_end_date_val = $today_for_preset->format('Y-m-d'); $performance_start_date_val = $today_for_preset->modify('-6 days')->format('Y-m-d'); break;
        case 'this_month': $performance_start_date_val = $today_for_preset->format('Y-m-01'); $performance_end_date_val = $today_for_preset->format('Y-m-t'); break;
    }
}
$line_chart_labels_php = []; $line_chart_data_completed_php = [];
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $current_date_loop_obj = DateTime::createFromFormat('Y-m-d', $performance_start_date_val);
        $end_date_loop_obj = DateTime::createFromFormat('Y-m-d', $performance_end_date_val);
        if ($current_date_loop_obj && $end_date_loop_obj) {
            if ($current_date_loop_obj > $end_date_loop_obj) { list($current_date_loop_obj, $end_date_loop_obj) = [$end_date_loop_obj, $current_date_loop_obj]; }
            $loop_count = 0; $interval_one_day = new DateInterval('P1D');
            while ($current_date_loop_obj <= $end_date_loop_obj) {
                $date_str_loop = $current_date_loop_obj->format('Y-m-d');
                $line_chart_labels_php[] = $current_date_loop_obj->format('d M');
                $stmt_completed_on_date = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status = 'Completed' AND DATE(updated_at) = ?");
                $tasks_completed_on_day = 0;
                if($stmt_completed_on_date) {
                    $stmt_completed_on_date->bind_param("is", $user_id, $date_str_loop);
                    if($stmt_completed_on_date->execute()){ 
                        $result_completed_on_date = $stmt_completed_on_date->get_result();
                        if($result_completed_on_date && $result_completed_on_date->num_rows > 0) {
                            $row_completed = $result_completed_on_date->fetch_assoc();
                            $tasks_completed_on_day = isset($row_completed['count']) ? (int)$row_completed['count'] : 0;
                        }
                    }
                    $stmt_completed_on_date->close();
                }
                $line_chart_data_completed_php[] = $tasks_completed_on_day;
                $current_date_loop_obj->add($interval_one_day); $loop_count++;
                if ($loop_count > 90 ) { if ($current_date_loop_obj <= $end_date_loop_obj) { $line_chart_labels_php[] = "..."; $line_chart_data_completed_php[] = null; } break; }
            }
        }
    } catch (Exception $e) { error_log("Dashboard (Performa): Exception: " . $e->getMessage()); }
}
if (empty($line_chart_labels_php)) $line_chart_labels_php = ['Tidak Ada Data'];
if (empty($line_chart_data_completed_php)) $line_chart_data_completed_php = [0];
$performance_chart_data_php_final = [
    'labels' => $line_chart_labels_php,
    'datasets' => [[
        'label' => 'Tugas Selesai','data' => $line_chart_data_completed_php,
        'borderColor' => '#7e47b8','backgroundColor' => 'rgba(126, 71, 184, 0.1)',
        'fill' => true,'tension' => 0.2
    ]]
];


// REVISI FUNGSI get_dashboard_all_active_tasks
function get_dashboard_all_active_tasks($db_connection, $current_user_id_func) {
    $active_tasks_data = [];
    if (!$db_connection || !($db_connection instanceof mysqli)) {
        error_log("Fungsi get_dashboard_all_active_tasks: Koneksi DB tidak valid.");
        return $active_tasks_data;
    }
    $sql = "SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted
            FROM tasks
            WHERE user_id = ? AND status != 'Completed' AND (due_date >= CURDATE() OR due_date IS NULL)
            ORDER BY due_date ASC,
                     CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END";
    $stmt = $db_connection->prepare($sql);
    if (!$stmt) {
        error_log("Fungsi get_dashboard_all_active_tasks: Prepare failed: " . $db_connection->error);
        return $active_tasks_data;
    }
    $stmt->bind_param("i", $current_user_id_func);
    if (!$stmt->execute()) {
        error_log("Fungsi get_dashboard_all_active_tasks: Execute failed: " . $stmt->error);
        $stmt->close(); return $active_tasks_data;
    }
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) { $active_tasks_data[] = $row; }
    } else { error_log("Fungsi get_dashboard_all_active_tasks: get_result() failed: " . $stmt->error); }
    $stmt->close();
    return $active_tasks_data;
}
$dashboard_all_active_tasks = get_dashboard_all_active_tasks($conn, $user_id);
?>
<title>Dasbor - List In</title>

<main class="main">
    <div class="dashboard-grid">
        <div class="page-title-container">
            <h2 class="page-title">Dasbor Saya</h2> 
        </div>

        <div class="dashboard-left-column">
            <section class="widget">
                <h3>Status Tugas</h3><br>

                <div class="widget-content-area status-progress-container">
                    <?php if ($total_tasks_all_time > 0): ?>
                        <?php foreach ($progress_circles_data as $label => $data): ?>
                        <div class="progress-item">
                            <div class="progress-circle"
                                 style="--progress-color: <?php echo $data['color']; ?>; --progress-percent: <?php echo $data['percent']; ?>%;"
                                 data-progress="<?php echo $data['percent']; ?>">
                            </div>
                            <p><?php echo $label; ?> (<?php echo $data['count']; ?>)</p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-tasks-message" style="text-align:center; width:100%;">Belum ada data tugas.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="widget">
                <h3>Performa Pengerjaan</h3>
                 <form method="GET" action="dashboard.php" class="performance-filter-form">
                    <label>Rentang:</label>
                    <div class="filter-preset-buttons">
                        <button type="submit" name="range_type_perf" value="today" class="btn btn-sm <?php echo $active_preset_perf_val == 'today' ? 'active' : ''; ?>">Hari Ini</button>
                        <button type="submit" name="range_type_perf" value="last7days" class="btn btn-sm <?php echo $active_preset_perf_val == 'last7days' ? 'active' : ''; ?>">7 Hari</button>
                        <button type="submit" name="range_type_perf" value="this_month" class="btn btn-sm <?php echo $active_preset_perf_val == 'this_month' ? 'active' : ''; ?>">Bulan Ini</button>
                    </div>
                    <input type="text" id="filterPerformanceDateRange" name="filterPerformanceDateRange" placeholder="Kustom..." value="<?php echo htmlspecialchars($_GET['filterPerformanceDateRange'] ?? ''); ?>" style="width:160px;">
                    <div class="filter-action-buttons">
                        <button type="submit" name="filterPerformanceSubmit" value="1" class="btn btn-primary btn-apply-perf btn-sm">Lihat</button>
                    </div>
                </form>
                <div class="widget-content-area chart-container">
                    <?php
                     $has_valid_line_chart_data = false;
                     if (isset($performance_chart_data_php_final['datasets'][0]['data']) && is_array($performance_chart_data_php_final['datasets'][0]['data'])) {
                         $filtered_data = array_filter($performance_chart_data_php_final['datasets'][0]['data'], function($x) { return $x !== null && $x >=0; });
                         $has_valid_line_chart_data = !empty($filtered_data) && count($filtered_data) > 0;
                     }
                     $has_valid_labels = !empty($performance_chart_data_php_final['labels']) && !in_array("Error", $performance_chart_data_php_final['labels']) && !in_array("Tidak Ada Data", $performance_chart_data_php_final['labels']);
                     if ($has_valid_line_chart_data && $has_valid_labels):
                     ?>
                        <canvas id="performanceLineChart"></canvas>
                    <?php else: ?>
                        <p class="no-tasks-message" style="text-align:center; padding-top:20px;">Tidak ada data tugas selesai untuk ditampilkan pada rentang waktu ini.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="dashboard-right-column">
            <section class="widget" style="height:100%;">
                <h3>Tugas Aktif (<?php echo count($dashboard_all_active_tasks); ?>)</h3>
                <div class="widget-content-area" id="dashboard-active-tasks-list" style="padding-top:0;">
                    <?php if (empty($dashboard_all_active_tasks)): ?>
                        <p class="no-tasks-message">Tidak ada tugas aktif.</p>
                    <?php else: ?>
                        <?php foreach ($dashboard_all_active_tasks as $task_item_active) echo render_task_card($task_item_active, 'dashboardTodo', $current_page_for_redirect); ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
// ... (JavaScript untuk Chart dan tombol preset performa sama seperti sebelumnya) ...
document.addEventListener('DOMContentLoaded', () => {
    const lineCtx = document.getElementById('performanceLineChart');
    if (lineCtx && typeof Chart !== 'undefined') {
        const performanceData = <?php echo json_encode($performance_chart_data_php_final); ?>;
        let hasValidLineData = false;
        if (performanceData && performanceData.labels && Array.isArray(performanceData.labels) &&
            performanceData.datasets && Array.isArray(performanceData.datasets) && performanceData.datasets.length > 0 &&
            performanceData.datasets[0].data && Array.isArray(performanceData.datasets[0].data) ) {
            let validLabelsExist = performanceData.labels.some(l => l !== 'Error' && l !== 'Tidak Ada Data' && l !== '...');
            let numericDataExists = performanceData.datasets[0].data.some(d => typeof d === 'number' && d >= 0);
            hasValidLineData = validLabelsExist && numericDataExists;
        }
        if (hasValidLineData) {
            new Chart(lineCtx, {
                type: 'line', data: performanceData,
                options: { responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0, callback: function(value) {if (Number.isInteger(value)) {return value;}}} } },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { let label = context.dataset.label || ''; if (label) { label += ': '; } if (context.parsed.y !== null) { label += context.parsed.y; } return label; }}}}
                }
            });
        }
    }
    const perfPresetButtons = document.querySelectorAll('.performance-filter-form .filter-preset-buttons .btn');
    const perfDateRangeInput = document.getElementById('filterPerformanceDateRange');
    perfPresetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (perfDateRangeInput) perfDateRangeInput.value = ''; 
            const parentButtonsContainer = this.closest('.filter-preset-buttons');
            if(parentButtonsContainer){
                parentButtonsContainer.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
            }
            this.classList.add('active');
        });
    });
    if (perfDateRangeInput) {
        perfDateRangeInput.addEventListener('input', function() {
            if (this.value !== '') {
                const parentButtonsContainer = this.closest('.performance-filter-form').querySelector('.filter-preset-buttons');
                if(parentButtonsContainer){
                     parentButtonsContainer.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
                }
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>