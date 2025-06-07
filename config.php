<?php
// File: config.php

// Pengaturan Aplikasi
define('APP_URL', 'http://listin.rf.gd'); // GANTI DENGAN URL PROYEK ANDA

// Konfigurasi Database
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_39177077');
define('DB_PASS', '1kpQrb45OMC'); // Ganti jika password root MySQL Anda berbeda
define('DB_NAME', 'if0_39177077_db_listin');

// Pengaturan PHPMailer (untuk Lupa Password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'irhamnajibazimulqowi@gmail.com');
define('SMTP_PASSWORD', 'bval qyel goie apvq'); // INI ADALAH APP PASSWORD ANDA
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM_ADDRESS', 'irhamnajibazimulqowi@gmail.com'); 
define('EMAIL_FROM_NAME', 'List In App');


// chatbot
define('CHATBOT_API_KEY', 'AIzaSyBKbfQyNil6MSuwWovQ0tz_ZsVtZipWXiE'); // << GANTI DENGAN API KEY GEMINI ANDA YANG VALID

// Pengaturan Error Reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); 
ini_set('display_errors', 1); 

date_default_timezone_set('Asia/Jakarta');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>