<?php
require_once 'includes/db.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';

$valid_statuses_for_cycle = ['Not Started', 'In Progress', 'Completed'];

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Tugas tidak valid.']);
    exit();
}
if (!in_array($new_status, $valid_statuses_for_cycle)) {
    echo json_encode(['success' => false, 'message' => 'Status baru tidak valid.']);
    exit();
}

$stmt_check = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
if (!$stmt_check) {
    echo json_encode(['success' => false, 'message' => 'Database error (check): ' . $conn->error]);
    exit();
}
$stmt_check->bind_param("ii", $task_id, $user_id);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan atau Anda tidak berhak mengubahnya.']);
    $stmt_check->close();
    exit();
}
$stmt_check->close();

$stmt_update = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?"); // updated_at akan otomatis
if (!$stmt_update) {
    echo json_encode(['success' => false, 'message' => 'Database error (update): ' . $conn->error]);
    exit();
}
$stmt_update->bind_param("si", $new_status, $task_id);

if ($stmt_update->execute()) {
    $is_completed = ($new_status === 'Completed');
    echo json_encode([
        'success' => true,
        'message' => 'Status berhasil diperbarui.',
        'new_status_text' => ($new_status === 'Not Started' ? 'Belum Mulai' : ($new_status === 'In Progress' ? 'Dikerjakan' : 'Selesai')),
        'is_completed' => $is_completed
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status: ' . $stmt_update->error]);
}
$stmt_update->close();
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
exit();
?>