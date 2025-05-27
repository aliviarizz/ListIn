<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// $errors = []; // Dihapus, gunakan add_notification()
$task = null;

if ($task_id > 0) {
    $stmt = $conn->prepare("SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    if (!$task) {
        add_notification("Tugas tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.", "error");
        header("Location: manajemen_tugas.php");
        exit();
    }
} else {
    // Tidak perlu add_notification di sini jika langsung redirect, kecuali ada kasus khusus
    header("Location: manajemen_tugas.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['editTaskTitle']);
    $description = trim($_POST['editTaskDescription']);
    $priority = $_POST['editTaskPriority'];
    $status = $_POST['editTaskStatus'];
    $due_date_str = $_POST['editTaskDueDate']; // Format d/m/Y
    $validation_errors_found = false;

    if (empty($title)) {
        add_notification("Judul tugas wajib diisi.", "error");
        $validation_errors_found = true;
    }
    if (empty($due_date_str)) {
        add_notification("Tanggal deadline wajib diisi.", "error");
        $validation_errors_found = true;
    }
    
    $due_date_mysql = null;
    if (!empty($due_date_str)) {
        $date_parts = explode('/', $due_date_str);
        if (count($date_parts) == 3 && checkdate($date_parts[1], $date_parts[0], $date_parts[2])) {
            $due_date_mysql = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        } else {
            add_notification("Format tanggal deadline tidak valid.", "error");
            $validation_errors_found = true;
        }
    }

    if (!$validation_errors_found) {
        $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, priority = ?, status = ?, due_date = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssssii", $title, $description, $priority, $status, $due_date_mysql, $task_id, $user_id);
        
        if ($stmt->execute()) {
            add_notification("Tugas berhasil diperbarui!", "success");
            header("Location: manajemen_tugas.php");
            exit();
        } else {
            add_notification("Gagal memperbarui tugas: " . $stmt->error, "error");
        }
        $stmt->close();
    } else { 
        // Jika ada error validasi, isi ulang form dengan data POST agar tidak hilang
        // dan notifikasi akan muncul dari session
        $task['title'] = $title;
        $task['description'] = $description;
        $task['priority'] = $priority;
        $task['status'] = $status;
        $task['due_date_formatted'] = $due_date_str;
    }
}
?>
<title>Edit Tugas - List In</title>
        <main class="main">
            <h2 class="page-title">Edit Tugas</h2>

            <?php /* Blok pesan error/sukses di sini dihapus */ ?>

            <div class="form-container">
                <form id="editTaskForm" method="POST" action="edit_tugas.php?id=<?php echo $task_id; ?>">
                    <input type="hidden" id="editTaskId" name="editTaskId" value="<?php echo $task['id']; ?>">
                    <div class="form-group">
                        <label for="editTaskTitle">Judul Tugas</label>
                        <input type="text" id="editTaskTitle" name="editTaskTitle" required value="<?php echo htmlspecialchars($task['title']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="editTaskDescription">Deskripsi (Opsional)</label>
                        <textarea id="editTaskDescription" name="editTaskDescription"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editTaskPriority">Prioritas</label>
                        <select id="editTaskPriority" name="editTaskPriority">
                            <option value="Low" <?php echo ($task['priority'] == 'Low') ? 'selected' : ''; ?>>Rendah</option>
                            <option value="Medium" <?php echo ($task['priority'] == 'Medium') ? 'selected' : ''; ?>>Sedang</option>
                            <option value="High" <?php echo ($task['priority'] == 'High') ? 'selected' : ''; ?>>Tinggi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editTaskStatus">Status</label>
                        <select id="editTaskStatus" name="editTaskStatus">
                            <option value="Not Started" <?php echo ($task['status'] == 'Not Started') ? 'selected' : ''; ?>>Belum Mulai</option>
                            <option value="In Progress" <?php echo ($task['status'] == 'In Progress') ? 'selected' : ''; ?>>Dikerjakan</option>
                            <option value="Completed" <?php echo ($task['status'] == 'Completed') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editTaskDueDate">Tanggal Deadline</label>
                        <input type="text" id="editTaskDueDate" name="editTaskDueDate" placeholder="Pilih tanggal..." required value="<?php echo htmlspecialchars($task['due_date_formatted']); ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <a href="manajemen_tugas.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>