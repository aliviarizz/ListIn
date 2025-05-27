<?php
// Konfigurasi Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root'); // Ganti jika berbeda
define('DB_PASS', '');     // Ganti jika berbeda
define('DB_NAME', 'db_listin'); // Ganti dengan nama database Anda

// Buat Koneksi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek Koneksi
if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}

// Set timezone (opsional tapi direkomendasikan)
date_default_timezone_set('Asia/Jakarta');

// Mulai session di sini agar tersedia di semua file yang meng-include db.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper function for debugging SQL errors
function check_prepare($stmt, $conn) {
    if ($stmt === false) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
}
?>