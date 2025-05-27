<?php
session_start();

// Hapus semua pesan notifikasi dari session
if (isset($_SESSION['notification_messages'])) {
    unset($_SESSION['notification_messages']);
}
// Juga pastikan badge tidak aktif lagi
$_SESSION['has_unread_notifications_badge'] = false;

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit();
?>