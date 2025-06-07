<?php
require_once 'includes/db.php'; // Untuk session_start()

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$action = $_GET['action'] ?? null;

if ($action === 'mark_notifications_read') {
    if (isset($_SESSION['notification_messages']) && !empty($_SESSION['notification_messages'])) {
        foreach ($_SESSION['notification_messages'] as $key => $notification) {
            // Hanya ubah status jika 'unread' untuk efisiensi
            if (isset($_SESSION['notification_messages'][$key]['status']) && $_SESSION['notification_messages'][$key]['status'] === 'unread') {
                $_SESSION['notification_messages'][$key]['status'] = 'read';
            }
        }
    }
    echo json_encode(['success' => true]);
    exit();
} elseif ($action === 'clear_notifications') {
    $_SESSION['notification_messages'] = [];
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit();
