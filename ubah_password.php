<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
// $errors = []; // Dihapus

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $currentPassword = $_POST['currentPassword'];
    $newPassword = $_POST['newPassword'];
    $confirmNewPassword = $_POST['confirmNewPassword'];
    $validation_errors_found = false;

    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        add_notification("Semua field wajib diisi.", "error");
        $validation_errors_found = true;
    } else {
        $stmt_check = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $user_data = $result_check->fetch_assoc();
        $stmt_check->close();

        if ($user_data && password_verify($currentPassword, $user_data['password'])) {
            if (strlen($newPassword) < 6) {
                add_notification("Kata sandi baru minimal 6 karakter.", "error");
                $validation_errors_found = true;
            } elseif ($newPassword !== $confirmNewPassword) {
                add_notification("Kata sandi baru dan konfirmasi tidak cocok.", "error");
                $validation_errors_found = true;
            } 
            
            if (!$validation_errors_found) { // Hanya proses jika tidak ada error validasi sebelumnya
                $hashed_new_password = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $hashed_new_password, $user_id);
                if ($stmt_update->execute()) {
                    add_notification("Kata sandi berhasil diubah!", "success");
                    header("Location: profil.php");
                    exit();
                } else {
                    add_notification("Gagal mengubah kata sandi: " . $stmt_update->error, "error");
                }
                $stmt_update->close();
            }
        } else if (!$validation_errors_found) { // Hanya tampilkan error ini jika belum ada error lain
            add_notification("Kata sandi saat ini salah.", "error");
            // $validation_errors_found = true; // Tidak perlu set ini karena sudah di akhir pengecekan branch ini
        }
    }
    // Jika ada error validasi, halaman akan render ulang, notifikasi muncul dari session
}
?>
<title>Ubah Kata Sandi - List In</title>
        <main class="main">
            <h2 class="page-title">Ubah Kata Sandi Akun</h2>

            <?php /* Blok pesan error di sini dihapus */ ?>

            <div class="form-container">
                <form id="changePasswordForm" method="POST" action="ubah_password.php">
                    <div class="form-group">
                        <label for="currentPassword">Kata Sandi Saat Ini</label>
                        <input type="password" id="currentPassword" name="currentPassword" required placeholder="Masukkan kata sandi Anda saat ini">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Kata Sandi Baru</label>
                        <input type="password" id="newPassword" name="newPassword" minlength="6" required placeholder="Minimal 6 karakter">
                    </div>
                    <div class="form-group">
                        <label for="confirmNewPassword">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" id="confirmNewPassword" name="confirmNewPassword" minlength="6" required placeholder="Ulangi kata sandi baru">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan Kata Sandi</button>
                         <a href="profil.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>