<?php
// File: ajax_get_tasks_for_date.php
require_once 'includes/db.php'; // Untuk $conn dan session_start()
require_once 'includes/task_helper.php'; // Untuk render_task_card

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.', 'tasks' => []]);
    exit();
}
$user_id = $_SESSION['user_id'];
$selected_date_str = $_GET['date'] ?? null; // Format YYYY-MM-DD

if (!$selected_date_str) {
    echo json_encode(['success' => false, 'message' => 'Tanggal tidak disediakan.', 'tasks' => []]);
    exit();
}

// Validasi format tanggal YYYY-MM-DD
$date_parts = explode('-', $selected_date_str);
if (count($date_parts) !== 3 || !checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
    echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.', 'tasks' => []]);
    exit();
}

$tasks_html_array = [];
// Modifikasi SQL untuk mengambil semua tugas yang memiliki due_date sama dengan tanggal yang dipilih,
// termasuk yang sudah selesai atau terlewat.
$sql = "SELECT id, title, description, priority, status, DATE_FORMAT(due_date, '%d/%m/%Y') as due_date_formatted, due_date
        FROM tasks
        WHERE user_id = ? AND due_date = ?
        ORDER BY CASE status 
                    WHEN 'In Progress' THEN 1 
                    WHEN 'Not Started' THEN 2 
                    WHEN 'Completed' THEN 3 
                    ELSE 4 
                 END, 
                 CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END, 
                 created_at ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("is", $user_id, $selected_date_str);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $current_page_for_redirect = 'laporan.php'; 

    while ($task = $result->fetch_assoc()) {
        // Gunakan page_type 'laporanItem'. Fungsi render_task_card akan menentukan
        // kelas CSS status berdasarkan data aktual tugas (status dan due_date).
        $tasks_html_array[] = render_task_card($task, 'laporanItem', $current_page_for_redirect . '?selected_date=' . urlencode($selected_date_str));
    }
    $stmt->close();
    echo json_encode(['success' => true, 'tasks' => $tasks_html_array]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error, 'tasks' => []]);
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>