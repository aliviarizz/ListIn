<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
// $errors = []; // Dihapus
// $success_message = ''; // Dihapus

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['taskTitle']);
    $description = trim($_POST['taskDescription']);
    $priority = $_POST['taskPriority'];
    $due_date_str = $_POST['taskDueDate']; 
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
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, priority, status, due_date) VALUES (?, ?, ?, ?, 'Not Started', ?)");
        $stmt->bind_param("issss", $user_id, $title, $description, $priority, $due_date_mysql);
        
        if ($stmt->execute()) {
            add_notification("Tugas berhasil ditambahkan!", "success");
            header("Location: manajemen_tugas.php");
            exit();
        } else {
            add_notification("Gagal menambahkan tugas: " . $stmt->error, "error");
        }
        $stmt->close();
    }
    // Jika ada error validasi, halaman akan render ulang, notifikasi muncul dari session
}
?>
<title>Tambah Tugas Baru - List In</title>
        <main class="main">
            <h2 class="page-title">Tambah Tugas Baru</h2>

            <?php /* Blok pesan error/sukses di sini dihapus */ ?>

            <div class="form-container">
                <form id="addTaskForm" method="POST" action="tambah_tugas.php">
                    <div class="form-group">
                        <label for="taskTitle">Judul Tugas</label>
                        <input type="text" id="taskTitle" name="taskTitle" required placeholder="cth: Selesaikan Laporan Proyek" value="<?php echo isset($_POST['taskTitle']) ? htmlspecialchars($_POST['taskTitle']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="taskDescription">Deskripsi (Opsional)</label>
                        <textarea id="taskDescription" name="taskDescription" placeholder="Detail tugas atau catatan tambahan..."><?php echo isset($_POST['taskDescription']) ? htmlspecialchars($_POST['taskDescription']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="taskPriority">Prioritas</label>
                        <select id="taskPriority" name="taskPriority">
                            <option value="Low" <?php echo (isset($_POST['taskPriority']) && $_POST['taskPriority'] == 'Low') ? 'selected' : ''; ?>>Rendah</option>
                            <option value="Medium" <?php echo (!isset($_POST['taskPriority']) || (isset($_POST['taskPriority']) && $_POST['taskPriority'] == 'Medium')) ? 'selected' : ''; ?>>Sedang</option>
                            <option value="High" <?php echo (isset($_POST['taskPriority']) && $_POST['taskPriority'] == 'High') ? 'selected' : ''; ?>>Tinggi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="taskDueDate">Tanggal Deadline</label>
                        <input type="text" id="taskDueDate" name="taskDueDate" placeholder="Pilih tanggal..." required value="<?php echo isset($_POST['taskDueDate']) ? htmlspecialchars($_POST['taskDueDate']) : ''; ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="manajemen_tugas.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>