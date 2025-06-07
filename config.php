<?php
// File: config.php

/**
 * PENTING: JANGAN PERNAH MENGISI FILE INI DENGAN KREDENSIAL ASLI
 * JIKA ANDA MENGGUNAKAN VERSION CONTROL SEPERTI GIT.
 * 
 * Gunakan file .env untuk menyimpan data sensitif.
 * File ini hanya sebagai contoh atau fallback jika .env tidak ada.
 */

// Pengaturan Aplikasi
define('APP_URL', 'http://localhost/nama-proyek-anda'); // GANTI DENGAN URL PENGEMBANGAN LOKAL

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'NAMA_PENGGUNA_DATABASE_ANDA');
define('DB_PASS', 'PASSWORD_RAHASIA_ANDA');
define('DB_NAME', 'NAMA_DATABASE_ANDA');

// Pengaturan PHPMailer (untuk Lupa Password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'email_anda@gmail.com');
define('SMTP_PASSWORD', 'TOKEN_APP_PASSWORD_ANDA'); // Gunakan App Password dari Google
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM_ADDRESS', 'email_anda@gmail.com'); 
define('EMAIL_FROM_NAME', 'Nama Aplikasi Anda');

// Pengaturan Error Reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); 
ini_set('display_errors', 1); // Set ke 0 di lingkungan produksi

date_default_timezone_set('Asia/Jakarta');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
