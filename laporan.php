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

// --- DATA UNTUK DIAGRAM BATANG (Performa Pengerjaan - sama seperti dashboard) ---
$today_for_default_perf = new DateTimeImmutable();
$performance_start_date_val = $today_for_default_perf->modify('-6 days')->format('Y-m-d');
$performance_end_date_val = $today_for_default_perf->format('Y-m-d');
$active_preset_perf_val = 'last7days'; // Default

// Logika filter tanggal untuk diagram batang (mirip dashboard)
if (isset($_GET['filterReportChartSubmit'])) {
    if (isset($_GET['filterReportDateRange']) && !empty(trim($_GET['filterReportDateRange']))) {
        $range_perf = explode(' - ', $_GET['filterReportDateRange']);
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
         $active_preset_perf_val = ''; // Kosongkan preset jika rentang kustom dipilih
    }
} elseif (isset($_GET['range_type_report_chart'])) { // Menggunakan nama unik untuk preset di laporan
    $active_preset_perf_val = $_GET['range_type_report_chart'];
    $today_for_preset = new DateTimeImmutable();
    switch ($active_preset_perf_val) {
        case 'today': $performance_start_date_val = $today_for_preset->format('Y-m-d'); $performance_end_date_val = $today_for_preset->format('Y-m-d'); break;
        case 'last7days': $performance_end_date_val = $today_for_preset->format('Y-m-d'); $performance_start_date_val = $today_for_preset->modify('-6 days')->format('Y-m-d'); break;
        case 'this_month': $performance_start_date_val = $today_for_preset->format('Y-m-01'); $performance_end_date_val = $today_for_preset->format('Y-m-t'); break;
    }
}

$bar_chart_labels_php = []; $bar_chart_data_completed_php = [];
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $current_date_loop_obj = DateTime::createFromFormat('Y-m-d', $performance_start_date_val);
        $end_date_loop_obj = DateTime::createFromFormat('Y-m-d', $performance_end_date_val);
        if ($current_date_loop_obj && $end_date_loop_obj) {
            if ($current_date_loop_obj > $end_date_loop_obj) { list($current_date_loop_obj, $end_date_loop_obj) = [$end_date_loop_obj, $current_date_loop_obj]; }
            $loop_count = 0; $interval_one_day = new DateInterval('P1D');
            while ($current_date_loop_obj <= $end_date_loop_obj) {
                $date_str_loop = $current_date_loop_obj->format('Y-m-d');
                $bar_chart_labels_php[] = $current_date_loop_obj->format('d M'); // Format label
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
                $bar_chart_data_completed_php[] = $tasks_completed_on_day;
                $current_date_loop_obj->add($interval_one_day); $loop_count++;
                if ($loop_count > 90 ) { if ($current_date_loop_obj <= $end_date_loop_obj) { $bar_chart_labels_php[] = "..."; $bar_chart_data_completed_php[] = null; } break; }
            }
        }
    } catch (Exception $e) { error_log("Laporan (Chart): Exception: " . $e->getMessage()); }
}
if (empty($bar_chart_labels_php)) $bar_chart_labels_php = ['Tidak Ada Data'];
if (empty($bar_chart_data_completed_php)) $bar_chart_data_completed_php = [0];

$report_bar_chart_data_php_final = [
    'labels' => $bar_chart_labels_php,
    'datasets' => [[
        'label' => 'Tugas Selesai','data' => $bar_chart_data_completed_php,
        'backgroundColor' => 'rgba(126, 71, 184, 0.6)', // Warna utama
        'borderColor' => 'rgba(126, 71, 184, 1)',
        'borderWidth' => 1,
        'borderRadius' => 8, // Untuk sudut yang halus
        'borderSkipped' => false,
    ]]
];

?>
<title>Laporan Tugas - List In</title>

<main class="main">
    <div class="page-title-container">
        <h2 class="page-title">Laporan Produktivitas</h2>
        <button id="downloadReportBtn" class="btn btn-primary">
            <i class="fas fa-download"></i> Unduh Laporan
        </button>
    </div>

    <section class="widget report-chart-widget">
        <h3>Performa Pengerjaan Tugas</h3>
        <form method="GET" action="laporan.php" class="performance-filter-form report-performance-filter">
            <label>Rentang:</label>
            <div class="filter-preset-buttons">
                <button type="submit" name="range_type_report_chart" value="today" class="btn btn-sm <?php echo $active_preset_perf_val == 'today' ? 'active' : ''; ?>">Hari Ini</button>
                <button type="submit" name="range_type_report_chart" value="last7days" class="btn btn-sm <?php echo $active_preset_perf_val == 'last7days' ? 'active' : ''; ?>">7 Hari</button>
                <button type="submit" name="range_type_report_chart" value="this_month" class="btn btn-sm <?php echo $active_preset_perf_val == 'this_month' ? 'active' : ''; ?>">Bulan Ini</button>
            </div>
            <input type="text" id="filterReportDateRange" name="filterReportDateRange" placeholder="Kustom..." value="<?php echo htmlspecialchars($_GET['filterReportDateRange'] ?? ''); ?>" style="width:160px;">
            <div class="filter-action-buttons">
                <button type="submit" name="filterReportChartSubmit" value="1" class="btn btn-primary btn-apply-perf btn-sm">Lihat Diagram</button>
            </div>
        </form>
        <div class="widget-content-area chart-container" style="height: 250px; margin-top: 15px;">
             <?php
                $has_valid_bar_chart_data = false;
                if (isset($report_bar_chart_data_php_final['datasets'][0]['data']) && is_array($report_bar_chart_data_php_final['datasets'][0]['data'])) {
                    $filtered_data_bar = array_filter($report_bar_chart_data_php_final['datasets'][0]['data'], function($x) { return $x !== null && $x >=0; });
                    $has_valid_bar_chart_data = !empty($filtered_data_bar) && count($filtered_data_bar) > 0;
                }
                $has_valid_labels_bar = !empty($report_bar_chart_data_php_final['labels']) && !in_array("Error", $report_bar_chart_data_php_final['labels']) && !in_array("Tidak Ada Data", $report_bar_chart_data_php_final['labels']);

                if ($has_valid_bar_chart_data && $has_valid_labels_bar):
            ?>
            <canvas id="reportPerformanceBarChart"></canvas>
            <?php else: ?>
                <p class="no-tasks-message" style="text-align:center; padding-top:20px;">Tidak ada data tugas selesai untuk ditampilkan pada rentang waktu ini.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="report-tasks-by-date-section widget">
        <h3>Detail Tugas Berdasarkan Tanggal Deadline</h3>
        <div class="report-interactive-area">
            <div class="report-task-list-container">
                <p id="selectedDateText" class="selected-date-indicator">Pilih tanggal di kalender untuk melihat detail tugas.</p>
                <div id="reportTasksList" class="widget-content-area scrollable-list">
                    <!-- Tugas akan dimuat di sini oleh AJAX -->
                    <p class="no-tasks-message">Pilih tanggal pada kalender.</p>
                </div>
            </div>
            <div class="report-calendar-container">
                <div id="reportCalendar"></div> <!-- Kalender akan dirender di sini oleh JS -->
            </div>
        </div>
    </section>

</main>

<!-- MODAL UNTUK KUSTOMISASI LAPORAN PDF -->
<div id="reportModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Kustomisasi Laporan PDF</h3>
            <button id="closeReportModalBtn" class="modal-close-btn">&times;</button>
        </div>
        <form id="reportPdfForm" action="generate_report.php" method="POST" target="_blank">
            <div class="modal-body">
                <div class="form-group">
                    <label for="reportPdfDateRange">Rentang Tanggal Laporan</label>
                    <input type="text" id="reportPdfDateRange" name="report_date_range" placeholder="Pilih rentang tanggal..." required>
                </div>
                <div class="form-group">
                    <label>Sertakan Status Tugas:</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="status_all" name="status_all" checked>
                            <label for="status_all">Pilih Semua</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" class="status-filter" id="status_completed" name="statuses[]" value="Completed" checked>
                            <label for="status_completed">Selesai</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" class="status-filter" id="status_in_progress" name="statuses[]" value="In Progress" checked>
                            <label for="status_in_progress">Dikerjakan</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" class="status-filter" id="status_not_started" name="statuses[]" value="Not Started" checked>
                            <label for="status_not_started">Belum Mulai</label>
                        </div>
                         <div class="checkbox-item">
                            <input type="checkbox" class="status-filter" id="status_overdue" name="statuses[]" value="Overdue" checked>
                            <label for="status_overdue">Terlewat</label>
                        </div>
                    </div>
                </div>
                <!-- Hidden input untuk mengirim gambar chart -->
                <input type="hidden" name="chart_image_base64" id="chartImageBase64">
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelReportBtn" class="btn btn-secondary">Batal</button>
                <button type="submit" id="generateReportPdfBtn" class="btn btn-primary">Buat & Unduh PDF</button>
            </div>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    let reportChartInstance; // Variabel global untuk instance Chart

    // --- Script untuk Diagram Batang Laporan ---
    const barCtx = document.getElementById('reportPerformanceBarChart');
    if (barCtx && typeof Chart !== 'undefined') {
        const reportBarData = <?php echo json_encode($report_bar_chart_data_php_final); ?>;
        
        let hasValidBarData = false;
         if (reportBarData && reportBarData.labels && Array.isArray(reportBarData.labels) &&
            reportBarData.datasets && Array.isArray(reportBarData.datasets) && reportBarData.datasets.length > 0 &&
            reportBarData.datasets[0].data && Array.isArray(reportBarData.datasets[0].data) ) {
            let validLabelsExistBar = reportBarData.labels.some(l => l !== 'Error' && l !== 'Tidak Ada Data' && l !== '...');
            let numericDataExistsBar = reportBarData.datasets[0].data.some(d => typeof d === 'number' && d >= 0);
            hasValidBarData = validLabelsExistBar && numericDataExistsBar;
        }

        if (hasValidBarData) {
            reportChartInstance = new Chart(barCtx, {
                type: 'bar',
                data: reportBarData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false, // Penting: nonaktifkan animasi untuk tangkapan gambar yg konsisten
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0, 
                                callback: function(value) {if (Number.isInteger(value)) {return value;}}
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) { label += context.parsed.y + ' tugas'; }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    // ... Sisa script chart (filter preset, dll) ...
    const reportPresetButtons = document.querySelectorAll('.report-performance-filter .filter-preset-buttons .btn');
    const reportDateRangeInput = document.getElementById('filterReportDateRange');
    reportPresetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (reportDateRangeInput) reportDateRangeInput.value = ''; 
        });
    });
     if (reportDateRangeInput) {
        reportDateRangeInput.addEventListener('input', function() { 
            if (this.value !== '') {
                const parentButtonsContainer = this.closest('.report-performance-filter').querySelector('.filter-preset-buttons');
                if(parentButtonsContainer){
                     parentButtonsContainer.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
                }
            }
        });
    }


    // --- Script untuk Kalender Interaktif dan Daftar Tugas ---
    const calendarContainer = document.getElementById('reportCalendar');
    const tasksListContainer = document.getElementById('reportTasksList');
    const selectedDateText = document.getElementById('selectedDateText');
    let currentYear, currentMonth;
    let taskDatesFromServer = { active: [], completed: [], overdue: [] };

    function fetchTaskDatesAndRenderCalendar(year, month) {
        fetch('ajax_get_task_dates_for_calendar.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    taskDatesFromServer = data.dates;
                } else {
                    console.error('Failed to fetch task dates for calendar markers.');
                }
                renderCalendar(year, month);
            })
            .catch(error => {
                console.error('Error fetching task dates:', error);
                renderCalendar(year, month);
            });
    }


    function renderCalendar(year, month) {
        currentYear = year;
        currentMonth = month;
        calendarContainer.innerHTML = ''; 

        const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        const dayNames = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

        const header = document.createElement('div');
        header.classList.add('calendar-header-report');
        header.innerHTML = `
            <button id="prevMonthBtn"><</button>
            <span>${monthNames[month]} ${year}</span>
            <button id="nextMonthBtn">></button>
        `;
        calendarContainer.appendChild(header);

        const daysGrid = document.createElement('div');
        daysGrid.classList.add('calendar-days-grid-report');
        dayNames.forEach(day => {
            const dayNameCell = document.createElement('div');
            dayNameCell.classList.add('calendar-day-name-report');
            dayNameCell.textContent = day;
            daysGrid.appendChild(dayNameCell);
        });

        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyCell = document.createElement('div');
            daysGrid.appendChild(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayCell = document.createElement('div');
            dayCell.classList.add('calendar-day-report');
            
            const dayNumberSpan = document.createElement('span');
            dayNumberSpan.classList.add('day-number');
            dayNumberSpan.textContent = day;
            dayCell.appendChild(dayNumberSpan);

            const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dayCell.dataset.date = currentDateStr;
            
            const today = new Date();
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayCell.classList.add('today');
            }

            const markersContainer = document.createElement('div');
            markersContainer.classList.add('calendar-markers-container');

            if (taskDatesFromServer.active.includes(currentDateStr)) {
                const marker = document.createElement('span');
                marker.classList.add('calendar-marker', 'marker-active');
                marker.title = 'Ada tugas aktif';
                markersContainer.appendChild(marker);
            }
            if (taskDatesFromServer.completed.includes(currentDateStr)) {
                const marker = document.createElement('span');
                marker.classList.add('calendar-marker', 'marker-completed');
                marker.title = 'Ada tugas selesai';
                markersContainer.appendChild(marker);
            }
            if (taskDatesFromServer.overdue.includes(currentDateStr)) {
                const marker = document.createElement('span');
                marker.classList.add('calendar-marker', 'marker-overdue');
                marker.title = 'Ada tugas terlewat';
                markersContainer.appendChild(marker);
            }
            if (markersContainer.hasChildNodes()) {
                dayCell.appendChild(markersContainer);
            }

            dayCell.addEventListener('click', function() {
                document.querySelectorAll('.calendar-day-report.selected').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                loadTasksForDate(this.dataset.date);
            });
            daysGrid.appendChild(dayCell);
        }
        calendarContainer.appendChild(daysGrid);

        document.getElementById('prevMonthBtn').addEventListener('click', () => {
            month--;
            if (month < 0) { month = 11; year--; }
            fetchTaskDatesAndRenderCalendar(year, month);
        });

        document.getElementById('nextMonthBtn').addEventListener('click', () => {
            month++;
            if (month > 11) { month = 0; year++; }
            fetchTaskDatesAndRenderCalendar(year, month);
        });
    }

    function loadTasksForDate(dateStr) {
        const dateObj = new Date(dateStr);
        dateObj.setHours(0,0,0,0);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' };
        selectedDateText.textContent = `Detail Tugas untuk Deadline: ${dateObj.toLocaleDateString('id-ID', options)}`;
        tasksListContainer.innerHTML = '<p class="loading-message">Memuat tugas...</p>';

        fetch(`ajax_get_tasks_for_date.php?date=${dateStr}`)
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok: ' + response.statusText); }
                return response.json();
            })
            .then(data => {
                tasksListContainer.innerHTML = '';
                if (data.success && data.tasks.length > 0) {
                    data.tasks.forEach(taskHtml => {
                        tasksListContainer.insertAdjacentHTML('beforeend', taskHtml);
                    });
                } else if (data.success && data.tasks.length === 0) {
                    tasksListContainer.innerHTML = '<p class="no-tasks-message">Tidak ada tugas dengan deadline pada tanggal ini.</p>';
                } else {
                    tasksListContainer.innerHTML = `<p class="no-tasks-message">Gagal memuat tugas: ${data.message || 'Error tidak diketahui'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error fetching tasks:', error);
                tasksListContainer.innerHTML = `<p class="no-tasks-message">Terjadi kesalahan saat memuat tugas. (${error.message})</p>`;
            });
    }
    const todayInitial = new Date();
    fetchTaskDatesAndRenderCalendar(todayInitial.getFullYear(), todayInitial.getMonth());

    // --- Script untuk Modal Laporan PDF ---
    const downloadBtn = document.getElementById('downloadReportBtn');
    const reportModal = document.getElementById('reportModal');
    const closeReportModalBtn = document.getElementById('closeReportModalBtn');
    const cancelReportBtn = document.getElementById('cancelReportBtn');
    const reportPdfForm = document.getElementById('reportPdfForm');
    const chartImageInput = document.getElementById('chartImageBase64');
    const selectAllStatusCheckbox = document.getElementById('status_all');
    const statusCheckboxes = document.querySelectorAll('.status-filter');

    const openModal = () => reportModal.classList.add('show');
    const closeModal = () => reportModal.classList.remove('show');

    if (downloadBtn) downloadBtn.addEventListener('click', openModal);
    if (closeReportModalBtn) closeReportModalBtn.addEventListener('click', closeModal);
    if (cancelReportBtn) cancelReportBtn.addEventListener('click', closeModal);
    
    reportModal.addEventListener('click', (e) => {
        if (e.target === reportModal) closeModal();
    });

    if (selectAllStatusCheckbox) {
        selectAllStatusCheckbox.addEventListener('change', () => {
            statusCheckboxes.forEach(cb => {
                cb.checked = selectAllStatusCheckbox.checked;
            });
        });
    }
    
    statusCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            if (!cb.checked) {
                selectAllStatusCheckbox.checked = false;
            } else {
                const allChecked = Array.from(statusCheckboxes).every(i => i.checked);
                selectAllStatusCheckbox.checked = allChecked;
            }
        });
    });

    if (reportPdfForm) {
        reportPdfForm.addEventListener('submit', (e) => {
            // Tangkap gambar chart sebelum submit
            if (reportChartInstance) {
                chartImageInput.value = reportChartInstance.toBase64Image();
            } else {
                chartImageInput.value = ''; // Kosongkan jika chart tidak ada
            }
            // Validasi: pastikan setidaknya satu status dipilih
            const anyStatusChecked = Array.from(statusCheckboxes).some(i => i.checked);
            if (!anyStatusChecked) {
                e.preventDefault();
                alert('Pilih setidaknya satu status tugas untuk disertakan dalam laporan.');
                return;
            }

            // Setelah submit, modal akan tetap terbuka. Anda bisa menutupnya di sini jika mau.
            setTimeout(() => {
                closeModal();
            }, 500); // Beri jeda sedikit agar form bisa submit
        });
    }

});
</script>
<?php require_once 'includes/footer.php'; ?>