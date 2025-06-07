<?php
/**
 * File: index.php
 *
 * Ini adalah file entri point untuk aplikasi.
 * Tujuannya adalah untuk mengarahkan pengguna ke halaman yang sesuai
 * berdasarkan status login mereka.
 */

// Mulai session untuk memeriksa apakah pengguna sudah login atau belum.
// session_start() harus dipanggil sebelum ada output apapun.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah variabel session 'user_id' sudah ada.
// Jika ada, berarti pengguna sudah login.
if (isset($_SESSION['user_id'])) {
    // Pengguna sudah login, arahkan ke halaman dashboard.
    // header() digunakan untuk melakukan redirect HTTP.
    header("Location: dashboard.php");
    // Penting untuk memanggil exit() setelah header() untuk menghentikan
    // eksekusi skrip lebih lanjut.
    exit();
} else {
    // Pengguna belum login, arahkan ke halaman login.
    header("Location: login.php");
    exit();
}

// Tidak ada kode lain yang diperlukan di bawah ini.
?>