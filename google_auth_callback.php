<?php
// File: google_auth_callback.php
require_once 'config.php'; // Untuk GOOGLE_CLIENT_ID, dll. dan session_start()
require_once 'includes/db.php'; // Untuk koneksi $conn

// Pastikan Anda telah menginstal Google API Client Library
// Jika via Composer:
require_once __DIR__ . '/vendor/autoload.php';
// Jika manual, sesuaikan path ke autoload Google API Client
// require_once GOOGLE_API_CLIENT_LIBRARY_PATH;


$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        // Error saat mengambil access token
        $_SESSION['login_error_message'] = 'Gagal mendapatkan token dari Google: ' . htmlspecialchars($token['error_description'] ?? $token['error']);
        header('Location: login.php');
        exit();
    }
    $client->setAccessToken($token['access_token']);

    // Dapatkan info profil pengguna
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $google_id = $google_account_info->id;
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $profile_pic_url = $google_account_info->picture;

    // Cek apakah pengguna sudah ada di database
    $stmt = $conn->prepare("SELECT id, username, profile_image FROM users WHERE google_id = ? OR email = ?");
    if (!$stmt) {
        $_SESSION['login_error_message'] = "Database error (prepare): " . $conn->error;
        header('Location: login.php');
        exit();
    }
    $stmt->bind_param("ss", $google_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Pengguna sudah ada, login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username']; // Bisa jadi username lokalnya berbeda
        
        // Update google_id jika login via email tapi google_id belum ada
        if (empty($user['google_id']) && $user['email'] == $email) {
            $stmt_update_gid = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            if($stmt_update_gid){
                $stmt_update_gid->bind_param("si", $google_id, $user['id']);
                $stmt_update_gid->execute();
                $stmt_update_gid->close();
            }
        }
        
        // Ambil gambar profil dari Google jika pengguna belum punya atau masih placeholder
        $current_db_image = $user['profile_image'];
        if ((empty($current_db_image) || $current_db_image == 'images/placeholder-profile.png') && !empty($profile_pic_url)) {
            // Coba unduh dan simpan gambar profil dari Google
            $image_data = @file_get_contents($profile_pic_url);
            if ($image_data !== false) {
                $upload_dir = 'uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $filename = 'google_user_' . $user['id'] . '_' . time() . '.jpg'; // Asumsi jpg
                $filepath = $upload_dir . $filename;
                if (file_put_contents($filepath, $image_data)) {
                    $stmt_update_pic = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    if($stmt_update_pic){
                        $stmt_update_pic->bind_param("si", $filepath, $user['id']);
                        $stmt_update_pic->execute();
                        $stmt_update_pic->close();
                        $_SESSION['profile_image'] = $filepath;
                    }
                }
            }
        } else {
            $_SESSION['profile_image'] = $current_db_image;
        }

        header('Location: dashboard.php');
        exit();
    } else {
        // Pengguna baru, daftarkan
        $default_profile_image_path = 'images/placeholder-profile.png'; // Default
        $new_user_image_path = $default_profile_image_path;

        // Coba unduh dan simpan gambar profil dari Google untuk pengguna baru
        if (!empty($profile_pic_url)) {
            $image_data_new = @file_get_contents($profile_pic_url);
            if ($image_data_new !== false) {
                $upload_dir_new = 'uploads/profile_pictures/';
                 if (!is_dir($upload_dir_new)) {
                    mkdir($upload_dir_new, 0755, true);
                }
                // Perlu ID user baru, jadi kita insert dulu baru update gambar
                // Atau, simpan sementara dan update setelah user ID didapat
            }
        }

        $stmt_insert = $conn->prepare("INSERT INTO users (google_id, username, email, profile_image, email_verified_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt_insert) {
             $_SESSION['login_error_message'] = "Database error (insert prepare): " . $conn->error;
            header('Location: login.php');
            exit();
        }
        // Gunakan $name sebagai username awal, $email, dan $new_user_image_path
        $stmt_insert->bind_param("ssss", $google_id, $name, $email, $new_user_image_path);
        if ($stmt_insert->execute()) {
            $new_user_id = $conn->insert_id;
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $name;

            // Sekarang coba simpan gambar profil jika berhasil diunduh sebelumnya
            if (isset($image_data_new) && $image_data_new !== false) {
                $filename_new = 'google_user_' . $new_user_id . '_' . time() . '.jpg';
                $filepath_new = $upload_dir_new . $filename_new;
                if (file_put_contents($filepath_new, $image_data_new)) {
                    $stmt_update_new_pic = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                     if($stmt_update_new_pic){
                        $stmt_update_new_pic->bind_param("si", $filepath_new, $new_user_id);
                        $stmt_update_new_pic->execute();
                        $stmt_update_new_pic->close();
                        $_SESSION['profile_image'] = $filepath_new;
                     }
                } else {
                     $_SESSION['profile_image'] = $default_profile_image_path;
                }
            } else {
                $_SESSION['profile_image'] = $default_profile_image_path;
            }

            header('Location: dashboard.php'); // Arahkan ke dashboard
            exit();
        } else {
            $_SESSION['login_error_message'] = "Gagal mendaftarkan pengguna baru: " . $stmt_insert->error;
            header('Location: login.php');
            exit();
        }
        $stmt_insert->close();
    }
} else {
    // Tidak ada kode otorisasi, mungkin akses langsung atau error
    $_SESSION['login_error_message'] = 'Akses tidak sah atau otorisasi Google dibatalkan.';
    header('Location: login.php');
    exit();
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>