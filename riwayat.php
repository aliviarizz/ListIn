<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/task_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$current_page_name = basename($_SERVER['SCRIPT_NAME']);

$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$filter_date_range_str = isset($_GET['filterHistoryDateRange']) ? trim($_GET['filterHistoryDateRange']) : '';
$filter_history_type_val = isset($_GET['filterHistoryType']) ? $_GET['filterHistoryType'] : 'all'; 

$sql_conditions_array = ["user_id = ?"];
$params = [$user_id];
$types = "i"; 

if ($filter_history_type_val === 'completed') {
    $sql_conditions_array[] = "status = 'Completed'";
} elseif ($filter_history_type_val === 'overdue_uncompleted') {
    $sql_conditions_array[] = "(due_date < CURDATE() AND status != 'Completed')";
} else { 
    $sql_conditions_array[] = "(status = 'Completed' OR (due_date < CURDATE() AND status != 'Completed'))";
}

if (!empty($search_term)) {
    $sql_conditions_array[] = "(title LIKE ? OR description LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like; $params[] = $search_like;
    $types .= "ss";
}

if (!empty($filter_date_range_str)) {
    $dates = explode(' - ', $filter_date_range_str);
    $start_date_mysql = null; $end_date_mysql = null; $date_filter_error = false;

    if (count($dates) >= 1 && !empty(trim($dates[0]))) {
        $date_parts_start = explode('/', trim($dates[0]));
        if (count($date_parts_start) == 3 && checkdate((int)$date_parts_start[1], (int)$date_parts_start[0], (int)$date_parts_start[2])) {
            $start_date_mysql = $date_parts_start[2] . '-' . $date_parts_start[1] . '-' . $date_parts_start[0];
        } else { $date_filter_error = true; }
    }
    if (count($dates) == 2 && !empty(trim($dates[1]))) {
        $date_parts_end = explode('/', trim($dates[1]));
        if (count($date_parts_end) == 3 && checkdate((int)$date_parts_end[1], (int)$date_parts_end[0], (int)$date_parts_end[2])) {
            $end_date_mysql = $date_parts_end[2] . '-' . $date_parts_end[1] . '-' . $date_parts_end[0];
        } else { $date_filter_error = true; }
    } elseif (count($dates) == 1 && $start_date_mysql) { 
        $end_date_mysql = $start_date_mysql;
    }

    if ($date_filter_error) {
        add_notification("Format rentang tanggal filter tidak valid: " . htmlspecialchars($filter_date_range_str), "error");
    } else {
        if ($start_date_mysql && $end_date_mysql) {
            if (strtotime($start_date_mysql) > strtotime($end_date_mysql)) { 
                list($start_date_mysql, $end_date_mysql) = [$end_date_mysql, $start_date_mysql]; 
            }
            if ($filter_history_type_val === 'completed') {
                 $sql_conditions_array[] = "DATE(updated_at) BETWEEN ? AND ?"; 
            } else { 
                 $sql_conditions_array[] = "DATE(due_date) BETWEEN ? AND ?";
            }
            $params[] = $start_date_mysql; $params[] = $end_date_mysql; $types .= "ss";
        } elseif ($start_date_mysql) { 
            if ($filter_history_type_val === 'completed') {
                $sql_conditions_array[] = "DATE(updated_at) = ?";
            } else {
                $sql_conditions_array[] = "DATE(due_date) = ?";
            }
            $params[] = $start_date_mysql; $types .= "s";
        }
    }
}

$sql_conditions_string = implode(" AND ", $sql_conditions_array);
$sql = "SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted, due_date, updated_at 
        FROM tasks
        WHERE $sql_conditions_string
        ORDER BY CASE 
                    WHEN status = 'Completed' THEN updated_at 
                    ELSE due_date 
                 END DESC, 
                 id DESC"; 

$stmt_history = $conn->prepare($sql);
$history_tasks = [];

if ($stmt_history) {
    if (count($params) > 0) { $stmt_history->bind_param($types, ...$params); }
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row_history = $result_history->fetch_assoc()) { $history_tasks[] = $row_history; }
    $stmt_history->close();
} else {
    if (!isset($conn) || !$conn) { add_notification("Koneksi database tidak tersedia.", "error"); }
    else { add_notification("Error mempersiapkan query riwayat: " . $conn->error, "error"); }
}
?>
<title>Riwayat Tugas - List In</title>
        <main class="main">
            <h2 class="page-title">Riwayat Tugas</h2>

            <div class="filters-container">
                <form method="GET" action="riwayat.php" style="display: contents; flex-grow:1; flex-wrap:wrap; gap:10px;">
                     <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="form-group">
                        <label for="filterHistoryType">Jenis Riwayat</label>
                        <select id="filterHistoryType" name="filterHistoryType">
                            <option value="all" <?php echo ($filter_history_type_val == 'all') ? 'selected' : ''; ?>>Semua Riwayat</option>
                            <option value="completed" <?php echo ($filter_history_type_val == 'completed') ? 'selected' : ''; ?>>Selesai Dikerjakan</option>
                            <option value="overdue_uncompleted" <?php echo ($filter_history_type_val == 'overdue_uncompleted') ? 'selected' : ''; ?>>Terlewat & Belum Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterHistoryDateRange">Rentang Tanggal</label>
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
                        <p class="no-tasks-message">Tidak ada riwayat tugas yang sesuai dengan filter.</p>
                    <?php else: ?>
                        <?php 
                        $current_filters_query_array_hist = [];
                        if(!empty($search_term)) $current_filters_query_array_hist['search_term'] = $search_term;
                        if(!empty($filter_history_type_val)) $current_filters_query_array_hist['filterHistoryType'] = $filter_history_type_val;
                        if(!empty($filter_date_range_str)) $current_filters_query_array_hist['filterHistoryDateRange'] = $filter_date_range_str;
                        $current_filters_query_string_hist = !empty($current_filters_query_array_hist) ? '?' . http_build_query($current_filters_query_array_hist) : '';
                        $redirect_url_with_filters_hist = $current_page_name . $current_filters_query_string_hist;

                        foreach ($history_tasks as $task) {
                            $history_card_type_render = '';
                            if ($task['status'] == 'Completed') {
                                $history_card_type_render = 'completed';
                            } elseif ($task['status'] != 'Completed' && !empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d'))) { 
                                $history_card_type_render = 'overdue_uncompleted';
                            }
                            echo render_task_card($task, 'history', $redirect_url_with_filters_hist, $history_card_type_render);
                        } 
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>