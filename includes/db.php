<?php
// File: includes/db.php
require_once dirname(__DIR__) . '/config.php'; // Memuat config.php dari root direktori

// Buat Koneksi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek Koneksi
if ($conn->connect_error) {
    // Jangan tampilkan detail error di produksi
    // error_log("Koneksi Gagal: " . $conn->connect_error); // Log error
    die("Tidak dapat terhubung ke database. Silakan coba lagi nanti."); // Pesan umum untuk user
}

// Set character set (penting untuk UTF-8)
if (!$conn->set_charset("utf8mb4")) {
    // error_log("Error loading character set utf8mb4: " . $conn->error);
    // die("Kesalahan konfigurasi database.");
}

// Session sudah dimulai di config.php
?>