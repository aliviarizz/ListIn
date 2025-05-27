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

// --- LOGIKA AKSI (UPDATE STATUS, DELETE, REOPEN) ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
    
    $from_page_url = $_GET['from'] ?? 'manajemen_tugas.php'; 
    $url_parts = parse_url($from_page_url);
    $redirect_page_target = $url_parts['path'] ?? 'manajemen_tugas.php';
    $redirect_query_string = $url_parts['query'] ?? '';
    if (!empty($redirect_query_string)) {
        $redirect_page_target .= '?' . $redirect_query_string;
    }

    if ($task_id > 0 && isset($conn)) { 
        $stmt_check_owner = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $can_action = false;
        if($stmt_check_owner){
            $stmt_check_owner->bind_param("ii", $task_id, $user_id);
            $stmt_check_owner->execute();
            $stmt_check_owner->store_result();
            $can_action = $stmt_check_owner->num_rows > 0;
            $stmt_check_owner->close();
        }

        if ($can_action) {
            if ($action == 'update_status' && isset($_POST['new_status'])) {
                $new_status = $_POST['new_status'];
                $valid_statuses = ['Not Started', 'In Progress', 'Completed'];
                if (in_array($new_status, $valid_statuses)) {
                    $stmt_update = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?"); 
                    if($stmt_update){
                        $stmt_update->bind_param("si", $new_status, $task_id);
                        if ($stmt_update->execute()) {
                            add_notification("Status tugas berhasil diperbarui.", "success");
                        } else {
                            add_notification("Gagal memperbarui status: " . $stmt_update->error, "error");
                        }
                        $stmt_update->close();
                    } else {
                         add_notification("Gagal mempersiapkan update status: " . $conn->error, "error");
                    }
                } else {
                    add_notification("Status baru tidak valid.", "error");
                }
            } elseif ($action == 'delete') {
                $stmt_delete = $conn->prepare("DELETE FROM tasks WHERE id = ?"); 
                 if($stmt_delete){
                    $stmt_delete->bind_param("i", $task_id);
                    if ($stmt_delete->execute()) {
                        add_notification("Tugas berhasil dihapus.", "success");
                    } else {
                        add_notification("Gagal menghapus tugas: " . $stmt_delete->error, "error");
                    }
                    $stmt_delete->close();
                } else {
                    add_notification("Gagal mempersiapkan hapus tugas: " . $conn->error, "error");
                }
            } elseif ($action == 'reopen') {
                $stmt_reopen = $conn->prepare("UPDATE tasks SET status = 'Not Started' WHERE id = ? AND status = 'Completed'");
                if($stmt_reopen){
                    $stmt_reopen->bind_param("i", $task_id);
                    if ($stmt_reopen->execute() && $stmt_reopen->affected_rows > 0) {
                        add_notification("Tugas berhasil dibuka kembali.", "success");
                    } else if ($stmt_reopen->affected_rows == 0) {
                        add_notification("Tugas tidak dalam status 'Selesai' atau gagal dibuka kembali.", "info");
                    } else {
                        add_notification("Gagal membuka kembali tugas: " . $stmt_reopen->error, "error");
                    }
                    $stmt_reopen->close();
                } else {
                    add_notification("Gagal mempersiapkan reopen tugas: " . $conn->error, "error");
                }
            }
        } else {
             add_notification("Aksi tidak diizinkan atau tugas tidak ditemukan.", "error");
        }
    } else if ($task_id <= 0) {
        add_notification("ID Tugas tidak valid.", "error");
    }
    header("Location: " . $redirect_page_target);
    exit();
}

// --- LOGIKA FILTER & PENCARIAN ---
$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$filter_status_val = isset($_GET['filterStatus']) ? $_GET['filterStatus'] : '';
$filter_priority_val = isset($_GET['filterPriority']) ? $_GET['filterPriority'] : '';
$filter_date_str_val = isset($_GET['filterDate']) ? $_GET['filterDate'] : ''; 

$sql_conditions_array = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if (!empty($search_term)) {
    $sql_conditions_array[] = "(title LIKE ? OR description LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

if (!empty($filter_status_val)) {
    $sql_conditions_array[] = "status = ?";
    $params[] = $filter_status_val;
    $types .= "s";
} else {
    $sql_conditions_array[] = "status != 'Completed'"; 
}


if (!empty($filter_priority_val)) {
    $sql_conditions_array[] = "priority = ?";
    $params[] = $filter_priority_val;
    $types .= "s";
}
if (!empty($filter_date_str_val)) {
    $date_parts = explode('/', $filter_date_str_val);
    if (count($date_parts) == 3 && checkdate((int)$date_parts[1], (int)$date_parts[0], (int)$date_parts[2])) {
        $filter_date_mysql = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        $sql_conditions_array[] = "due_date = ?";
        $params[] = $filter_date_mysql;
        $types .= "s";
    } else if (!empty($filter_date_str_val)) { // Jika ada input tapi tidak valid
        add_notification("Format tanggal filter tidak valid: " . htmlspecialchars($filter_date_str_val), "error");
    }
}

$sql_conditions_string = implode(" AND ", $sql_conditions_array);
$sql = "SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted, due_date 
        FROM tasks 
        WHERE $sql_conditions_string 
        ORDER BY due_date ASC, CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END";

$stmt_tasks = $conn->prepare($sql);
$management_tasks = [];
if ($stmt_tasks) {
    if (!empty($params)) { 
        $stmt_tasks->bind_param($types, ...$params);
    }
    $stmt_tasks->execute();
    $result_tasks = $stmt_tasks->get_result();
    while ($row_task = $result_tasks->fetch_assoc()) {
        $management_tasks[] = $row_task;
    }
    $stmt_tasks->close();
} else {
    // Error mempersiapkan query sudah ditangani oleh add_notification() jika terjadi, 
    // atau bisa tambahkan di sini jika koneksi $conn null
    if (!isset($conn) || !$conn) {
       add_notification("Koneksi database tidak tersedia.", "error");
    } else {
       add_notification("Error mempersiapkan query tugas: " . $conn->error, "error");
    }
}
?>
<title>Kelola Tugas - List In</title>
        <main class="main">
            <h2 class="page-title">Kelola Tugas</h2>

            <?php /* Blok pesan di sini dihapus, akan muncul di popup notifikasi */ ?>
            
            <div class="filters-container">
                <form method="GET" action="manajemen_tugas.php" style="display: contents; flex-grow:1; flex-wrap:wrap; gap:10px;">
                    <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="form-group">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" name="filterStatus">
                            <option value="">Semua Aktif</option>
                            <option value="Not Started" <?php echo ($filter_status_val == 'Not Started') ? 'selected' : ''; ?>>Belum Mulai</option>
                            <option value="In Progress" <?php echo ($filter_status_val == 'In Progress') ? 'selected' : ''; ?>>Dikerjakan</option>
                            <option value="Completed" <?php echo ($filter_status_val == 'Completed') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterPriority">Prioritas</label>
                        <select id="filterPriority" name="filterPriority">
                            <option value="">Semua Prioritas</option>
                            <option value="High" <?php echo ($filter_priority_val == 'High') ? 'selected' : ''; ?>>Tinggi</option>
                            <option value="Medium" <?php echo ($filter_priority_val == 'Medium') ? 'selected' : ''; ?>>Sedang</option>
                            <option value="Low" <?php echo ($filter_priority_val == 'Low') ? 'selected' : ''; ?>>Rendah</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterDate">Deadline</label>
                        <input type="text" id="filterDate" name="filterDate" placeholder="Pilih Tanggal" value="<?php echo htmlspecialchars($filter_date_str_val); ?>">
                    </div>
                    <div class="btn-filter-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="manajemen_tugas.php?search_term=<?php echo urlencode($search_term);?>" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <div id="managementTaskList">
                    <?php if (empty($management_tasks)): ?>
                        <p class="no-tasks-message">Tidak ada tugas yang sesuai.</p>
                    <?php else: ?>
                        <?php foreach ($management_tasks as $task) echo render_task_card($task, 'management', $current_page_for_redirect . '?search_term=' . urlencode($search_term) . '&filterStatus=' . urlencode($filter_status_val) . '&filterPriority=' . urlencode($filter_priority_val) . '&filterDate=' . urlencode($filter_date_str_val) ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>