<?php
// File: ajax_get_task_dates_for_calendar.php
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'dates' => ['active' => [], 'completed' => [], 'overdue' => []]]);
    exit();
}
$user_id = $_SESSION['user_id'];

$task_dates = [
    'active' => [],    // Tugas yang masih aktif (Not Started, In Progress) dan belum lewat deadline
    'completed' => [], // Tugas yang sudah Completed
    'overdue' => []    // Tugas yang sudah lewat deadline dan belum Completed
];

// Ambil tanggal untuk tugas aktif (belum selesai dan belum overdue, berdasarkan due_date)
$sql_active = "SELECT DISTINCT DATE_FORMAT(due_date, '%Y-%m-%d') as task_date 
               FROM tasks 
               WHERE user_id = ? AND status != 'Completed' AND due_date >= CURDATE()";
$stmt_active = $conn->prepare($sql_active);
if ($stmt_active) {
    $stmt_active->bind_param("i", $user_id);
    $stmt_active->execute();
    $result_active = $stmt_active->get_result();
    while ($row = $result_active->fetch_assoc()) {
        if (!empty($row['task_date'])) $task_dates['active'][] = $row['task_date'];
    }
    $stmt_active->close();
}

// Ambil tanggal untuk tugas selesai (berdasarkan due_date dari tugas yang completed)
$sql_completed = "SELECT DISTINCT DATE_FORMAT(due_date, '%Y-%m-%d') as task_date 
                  FROM tasks 
                  WHERE user_id = ? AND status = 'Completed'";
$stmt_completed = $conn->prepare($sql_completed);
if ($stmt_completed) {
    $stmt_completed->bind_param("i", $user_id);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    while ($row = $result_completed->fetch_assoc()) {
         if (!empty($row['task_date'])) $task_dates['completed'][] = $row['task_date'];
    }
    $stmt_completed->close();
}

// Ambil tanggal untuk tugas overdue (belum selesai dan sudah lewat deadline, berdasarkan due_date)
$sql_overdue = "SELECT DISTINCT DATE_FORMAT(due_date, '%Y-%m-%d') as task_date 
                FROM tasks 
                WHERE user_id = ? AND status != 'Completed' AND due_date < CURDATE()";
$stmt_overdue = $conn->prepare($sql_overdue);
if ($stmt_overdue) {
    $stmt_overdue->bind_param("i", $user_id);
    $stmt_overdue->execute();
    $result_overdue = $stmt_overdue->get_result();
    while ($row = $result_overdue->fetch_assoc()) {
        if (!empty($row['task_date'])) $task_dates['overdue'][] = $row['task_date'];
    }
    $stmt_overdue->close();
}

// Hapus duplikasi jika sebuah tanggal masuk beberapa kategori (misal, ada tugas aktif dan selesai di tanggal yang sama)
// Client-side JavaScript akan menangani rendering beberapa marker jika perlu.
$task_dates['active'] = array_values(array_unique($task_dates['active']));
$task_dates['completed'] = array_values(array_unique($task_dates['completed']));
$task_dates['overdue'] = array_values(array_unique($task_dates['overdue']));

echo json_encode(['success' => true, 'dates' => $task_dates]);

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>