<?php
require_once 'includes/db.php'; // Untuk session_start()

// Hancurkan semua data session.
$_SESSION = array();

// Jika diinginkan menghancurkan session, juga hapus cookie session.
// Catatan: Ini akan menghancurkan session, dan bukan hanya data session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Akhirnya, hancurkan session.
session_destroy();

header("Location: login.php");
exit();
?>