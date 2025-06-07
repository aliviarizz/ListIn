<?php
require_once 'includes/header.php'; // Pastikan add_notification() sudah tersedia
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Ambil data user saat ini
$stmt_curr = $conn->prepare("SELECT username, email, profile_image FROM users WHERE id = ?");
$stmt_curr->bind_param("i", $user_id);
$stmt_curr->execute();
$result_curr = $stmt_curr->get_result();
$current_user_data = $result_curr->fetch_assoc();
$stmt_curr->close();

if (!$current_user_data) {
    // Seharusnya tidak terjadi jika user sudah login
    add_notification("Data pengguna tidak ditemukan.", "error");
    // Mungkin redirect ke logout atau halaman error
    header("Location: profil.php"); 
    exit();
}

// Inisialisasi variabel untuk form, diambil dari DB
$username_val = $current_user_data['username'];
$email_val = $current_user_data['email'];
// Path gambar untuk ditampilkan di form (preview)
$current_profile_image_path_for_preview = (!empty($current_user_data['profile_image']) && file_exists($current_user_data['profile_image'])) 
                                        ? $current_user_data['profile_image'] 
                                        : 'images/placeholder-profile.png';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['editProfileUsername']);
    $new_email = trim($_POST['editProfileEmail']);
    $validation_errors_found = false;
    
    // Update nilai untuk repopulate form jika ada error
    $username_val = $new_username; 
    $email_val = $new_email;

    // Path gambar yang akan disimpan ke database, defaultnya adalah gambar yang sudah ada.
    // Ini akan diubah jika ada upload gambar baru yang berhasil.
    $path_for_db_update = $current_user_data['profile_image']; 

    // --- Logika Upload Gambar Profil ---
    if (isset($_FILES['editProfileImageFile']) && $_FILES['editProfileImageFile']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['editProfileImageFile'];
        $upload_dir = 'uploads/profile_pictures/';

        // Cek dan buat direktori jika belum ada
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) { // Menggunakan 0755, 0777 terlalu permisif
                add_notification("Kritis: Gagal membuat direktori upload. Hubungi administrator.", "error");
                $validation_errors_found = true;
            }
        } elseif (!is_writable($upload_dir)) {
             add_notification("Kritis: Direktori upload tidak dapat ditulis. Hubungi administrator.", "error");
             $validation_errors_found = true;
        }


        if (!$validation_errors_found) { // Lanjutkan hanya jika direktori OK
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);

            if (in_array($file_type, $allowed_types)) {
                if ($file['size'] <= 2000000) { // Max 2MB
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . strtolower($file_extension);
                    $destination = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Gambar baru berhasil diupload
                        $path_for_db_update = $destination; // Path baru ini yang akan disimpan ke DB

                        // Hapus gambar lama jika ada dan bukan placeholder
                        $old_image_from_db = $current_user_data['profile_image'];
                        if ($old_image_from_db && 
                            $old_image_from_db != 'images/placeholder-profile.png' && 
                            file_exists($old_image_from_db) &&
                            $old_image_from_db !== $destination) { // Pastikan tidak menghapus file yang baru saja diupload
                            
                            if (unlink($old_image_from_db)) {
                                // Berhasil hapus file lama
                            } else {
                                add_notification("Info: Gambar profil lama ('" . basename($old_image_from_db) . "') tidak dapat dihapus dari server. Ini mungkin masalah izin file/folder di server.", "info");
                            }
                        }
                        // Update path untuk preview di form jika halaman di-render ulang karena error lain
                        $current_profile_image_path_for_preview = $path_for_db_update;
                    } else {
                        add_notification("Gagal memindahkan file gambar yang diupload. Pastikan folder '$upload_dir' dapat ditulis oleh server.", "error");
                        $validation_errors_found = true;
                    }
                } else {
                    add_notification("Ukuran file gambar maksimal 2MB.", "error");
                    $validation_errors_found = true;
                }
            } else {
                add_notification("Tipe file gambar tidak diizinkan (hanya JPG, PNG, GIF).", "error");
                $validation_errors_found = true;
            }
        }
    }
    // --- Akhir Logika Upload Gambar Profil ---


    // --- Validasi Input Lain (Username, Email) ---
    if (empty($new_username)) {
        add_notification("Nama pengguna wajib diisi.", "error");
        $validation_errors_found = true;
    }
    if (empty($new_email)) {
        add_notification("Alamat email wajib diisi.", "error");
        $validation_errors_found = true;
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        add_notification("Format email tidak valid.", "error");
        $validation_errors_found = true;
    }

    // Cek apakah email sudah digunakan pengguna lain (hanya jika email berubah & tidak ada error sebelumnya)
    if ($new_email !== $current_user_data['email'] && !$validation_errors_found) {
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check_email->bind_param("si", $new_email, $user_id);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            add_notification("Email sudah digunakan oleh pengguna lain.", "error");
            $validation_errors_found = true;
        }
        $stmt_check_email->close();
    }
    // --- Akhir Validasi Input Lain ---


    // --- Update Database ---
    if (!$validation_errors_found) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_image = ? WHERE id = ?");
        // Gunakan $path_for_db_update yang sudah ditentukan (bisa path baru atau path lama jika tidak ada upload)
        $stmt->bind_param("sssi", $new_username, $new_email, $path_for_db_update, $user_id);
        
        if ($stmt->execute()) {
            add_notification("Profil berhasil diperbarui!", "success");
            $_SESSION['username'] = $new_username; 
            $_SESSION['profile_image'] = $path_for_db_update; // Update session dengan path yang disimpan ke DB
            
            header("Location: profil.php");
            exit();
        } else {
            add_notification("Gagal memperbarui profil di database: " . $stmt->error, "error");
        }
        $stmt->close();
    }
    // Jika $validation_errors_found true, script akan lanjut merender form di bawah,
    // $username_val, $email_val, dan $current_profile_image_path_for_preview sudah diupdate
    // untuk menampilkan input pengguna dan preview gambar (jika ada upload baru).
}
?>
<title>Edit Profil - List In</title>
        <main class="main">
            <h2 class="page-title">Edit Informasi Profil</h2>

            <div class="form-container">
                <form id="editProfileForm" method="POST" action="edit_profil.php" enctype="multipart/form-data">
                    <br><br>
                     <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                        <img src="<?php echo htmlspecialchars($current_profile_image_path_for_preview); ?>?t=<?php echo time(); // Cache buster ?>" id="currentImagePreview" alt="Pratinjau Gambar Profil" style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 10px; object-fit: cover; border: 3px solid #eee;">
                        <label for="editProfileImageFile" class="btn btn-secondary" style="cursor:pointer; display:inline-block; padding: 8px 12px; font-size: 0.85rem;">
                            <i class="fas fa-upload"></i> Ganti Foto Profil
                        </label>
                        <input type="file" id="editProfileImageFile" name="editProfileImageFile" accept="image/*" style="display: none;">
                        <small style="display:block; margin-top:5px; color: #777;">Kosongkan jika tidak ingin mengganti foto. Maks 2MB (JPG, PNG, GIF).</small>
                    </div>
                    <div class="form-group">
                        <label for="editProfileUsername">Nama Pengguna</label>
                        <input type="text" id="editProfileUsername" name="editProfileUsername" required placeholder="Masukkan nama pengguna baru" value="<?php echo htmlspecialchars($username_val); ?>">
                    </div>
                    <div class="form-group">
                        <label for="editProfileEmail">Alamat Email</label>
                        <input type="email" id="editProfileEmail" name="editProfileEmail" required placeholder="Masukkan alamat email baru" value="<?php echo htmlspecialchars($email_val); ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="profil.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>