<?php
session_start(); // Mulai session untuk mengakses $_SESSION

if (isset($_SESSION['notification_messages'])) {
    unset($_SESSION['notification_messages']); // Hapus pesan notifikasi dari session
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit();
?>