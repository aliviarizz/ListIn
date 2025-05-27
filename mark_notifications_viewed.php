<?php
session_start();

// Tandai bahwa notifikasi (badge) telah dilihat
$_SESSION['has_unread_notifications_badge'] = false;

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit();
?>