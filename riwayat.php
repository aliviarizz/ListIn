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

// --- LOGIKA FILTER & PENCARIAN (HALAMAN RIWAYAT - DIPERBAIKI) ---
$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$filter_date_range_str = isset($_GET['filterHistoryDateRange']) ? trim($_GET['filterHistoryDateRange']) : '';

// Kondisi dasar untuk halaman riwayat
$sql_conditions_array = ["user_id = ?", "status = 'Completed'"];
$params = [$user_id];
$types = "i"; // 'i' untuk user_id

// Tambahkan kondisi pencarian jika ada
if (!empty($search_term)) {
    $sql_conditions_array[] = "(title LIKE ? OR description LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss"; // 's' untuk string, dua kali
}

// Tambahkan kondisi filter rentang tanggal jika ada
if (!empty($filter_date_range_str)) {
    $dates = explode(' - ', $filter_date_range_str);
    $start_date_mysql = null;
    $end_date_mysql = null;
    $date_filter_error = false;

    // Parsing tanggal mulai
    if (count($dates) >= 1 && !empty(trim($dates[0]))) {
        $date_parts_start = explode('/', trim($dates[0]));
        if (count($date_parts_start) == 3 && checkdate((int)$date_parts_start[1], (int)$date_parts_start[0], (int)$date_parts_start[2])) {
            // Format: dd/mm/yyyy -> yyyy-mm-dd
            $start_date_mysql = $date_parts_start[2] . '-' . $date_parts_start[1] . '-' . $date_parts_start[0];
        } else {
            $date_filter_error = true;
        }
    }

    // Parsing tanggal akhir (jika rentang disediakan)
    if (count($dates) == 2 && !empty(trim($dates[1]))) {
        $date_parts_end = explode('/', trim($dates[1]));
        if (count($date_parts_end) == 3 && checkdate((int)$date_parts_end[1], (int)$date_parts_end[0], (int)$date_parts_end[2])) {
            // Format: dd/mm/yyyy -> yyyy-mm-dd
            $end_date_mysql = $date_parts_end[2] . '-' . $date_parts_end[1] . '-' . $date_parts_end[0];
        } else {
            $date_filter_error = true;
        }
    }
    // Tidak perlu `elseif (count($dates) == 1 && $start_date_mysql)` di sini,
    // karena penanganan tanggal tunggal vs rentang akan dilakukan di bawah.

    if ($date_filter_error) {
        add_notification("Format rentang tanggal filter tidak valid: " . htmlspecialchars($filter_date_range_str), "error");
    } else {
        if ($start_date_mysql && $end_date_mysql) {
            // KEDUA tanggal (mulai dan akhir) valid -> gunakan BETWEEN
            // Pastikan start_date <= end_date
            if (strtotime($start_date_mysql) > strtotime($end_date_mysql)) {
                list($start_date_mysql, $end_date_mysql) = [$end_date_mysql, $start_date_mysql]; // Tukar jika salah urutan
                add_notification("Tanggal awal filter lebih besar dari tanggal akhir, urutan dibalik.", "info");
            }
            // Menggunakan DATE(due_date) untuk mengabaikan bagian waktu jika due_date adalah DATETIME
            $sql_conditions_array[] = "DATE(due_date) BETWEEN ? AND ?";
            $params[] = $start_date_mysql;
            $params[] = $end_date_mysql;
            $types .= "ss";
        } elseif ($start_date_mysql) {
            // HANYA tanggal mulai yang valid (atau hanya satu tanggal yang diberikan) -> gunakan =
            $sql_conditions_array[] = "DATE(due_date) = ?";
            $params[] = $start_date_mysql;
            $types .= "s";
        }
        // Jika hanya end_date_mysql yang valid (misal, input " - 10/10/2024"),
        // logika ini tidak akan menambahkannya. Ini perilaku yang wajar untuk filter rentang
        // yang biasanya mengharapkan tanggal mulai atau pasangan mulai-akhir.
    }
}

// Gabungkan semua kondisi
$sql_conditions_string = implode(" AND ", $sql_conditions_array);

// Susun query SQL akhir
// Kolom yang difilter adalah `due_date`. Jika Anda ingin memfilter berdasarkan kapan tugas *diselesaikan*,
// Anda mungkin memerlukan kolom `completed_at` atau `updated_at` (jika `updated_at` diperbarui saat status menjadi 'Completed').
// Untuk saat ini, kita tetap menggunakan `due_date` sesuai kode asli.
$sql = "SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted, due_date
        FROM tasks
        WHERE $sql_conditions_string
        ORDER BY due_date DESC, id DESC"; // Menambahkan id DESC untuk urutan sekunder yang konsisten

$stmt_history = $conn->prepare($sql);
$history_tasks = [];

if ($stmt_history) {
    // Bind parameter hanya jika ada parameter yang perlu di-bind
    if (!empty($params) && count($params) > 1) { // user_id selalu ada, jadi count > 1
         $stmt_history->bind_param($types, ...$params);
    } elseif (!empty($params) && count($params) == 1 && $types == "i") { // Hanya user_id
        $stmt_history->bind_param($types, $params[0]);
    }

    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row_history = $result_history->fetch_assoc()) {
        $history_tasks[] = $row_history;
    }
    $stmt_history->close();
} else {
    if (!isset($conn) || !$conn) {
       add_notification("Koneksi database tidak tersedia.", "error");
    } else {
       add_notification("Error mempersiapkan query riwayat: " . $conn->error, "error");
    }
}
?>
<title>Riwayat Tugas - List In</title>
        <main class="main">
            <h2 class="page-title">Riwayat Tugas Selesai</h2>
            
            <?php /* Blok pesan di sini dihapus */ ?>

            <div class="filters-container">
                <form method="GET" action="riwayat.php" style="display: contents; flex-grow:1; flex-wrap:wrap; gap:10px;">
                     <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="form-group">
                        <label for="filterHistoryDateRange">Rentang Tanggal Selesai</label>
                        <input type="text" id="filterHistoryDateRange" name="filterHistoryDateRange" placeholder="Pilih Rentang" value="<?php echo htmlspecialchars($filter_date_range_str); ?>">
                    </div>
                    <div class="btn-filter-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="riwayat.php?search_term=<?php echo urlencode($search_term); ?>" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-container">
                 <div id="historyTaskList">
                    <?php if (empty($history_tasks)): ?>
                        <p class="no-tasks-message">Tidak ada riwayat tugas.</p>
                    <?php else: ?>
                        <?php foreach ($history_tasks as $task) echo render_task_card($task, 'history', $current_page_for_redirect . '?search_term=' . urlencode($search_term) . '&filterHistoryDateRange=' . urlencode($filter_date_range_str) ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>