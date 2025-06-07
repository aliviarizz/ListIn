<?php
require_once 'config.php';
require_once 'includes/db.php';

$errors = [];
$token = $_GET['token'] ?? '';
$email_from_token = null;
$token_valid = false;

if (empty($token)) {
    $errors[] = "Token reset tidak valid atau tidak ditemukan.";
} else {
    $stmt_check_token = $conn->prepare("SELECT email, created_at FROM password_resets WHERE token = ?");
    $stmt_check_token->bind_param("s", $token);
    $stmt_check_token->execute();
    $result_token = $stmt_check_token->get_result();

    if ($reset_data = $result_token->fetch_assoc()) {
        $email_from_token = $reset_data['email'];
        
        $token_created_at = new DateTime($reset_data['created_at'], new DateTimeZone('UTF'));
        $now = new DateTime('now', new DateTimeZone('UTF'));
        
        $interval = $now->diff($token_created_at);
        $minutes_passed = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        if ($minutes_passed > 60) {
            $errors[] = "Token reset telah kedaluwarsa. Silakan minta tautan reset baru.";
            $stmt_delete_expired = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt_delete_expired->bind_param("s", $token);
            $stmt_delete_expired->execute();
            $stmt_delete_expired->close();
        } else {
            $token_valid = true;
        }
    } else {
        $errors[] = "Token reset tidak valid atau sudah digunakan.";
    }
    $stmt_check_token->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    $new_password = $_POST['newPassword'];
    $confirm_new_password = $_POST['confirmNewPassword'];

    if (empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "Kata sandi baru dan konfirmasi tidak boleh kosong.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "Kata sandi baru minimal 6 karakter.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "Kata sandi baru dan konfirmasi tidak cocok.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt_update_pass->bind_param("ss", $hashed_password, $email_from_token);
        
        if ($stmt_update_pass->execute()) {
            // PERBAIKAN: Hapus HANYA token yang digunakan, bukan semua token milik email tersebut.
            $stmt_delete_used = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt_delete_used->bind_param("s", $token);
            $stmt_delete_used->execute();
            $stmt_delete_used->close();

            $_SESSION['reset_success_message'] = "Kata sandi Anda telah berhasil direset. Silakan masuk dengan kata sandi baru.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Gagal mereset kata sandi. Silakan coba lagi.";
        }
        $stmt_update_pass->close();
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<title>Reset Kata Sandi - List In</title>

<div class="auth-container">
    <div class="logo-container"><h1>List In</h1></div>
    <h2>Reset Kata Sandi Anda</h2>

    <?php if (!empty($errors)): ?>
        <div class="auth-message error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($token_valid && empty($_SESSION['reset_success_message'])): ?>
        <p class="subtitle">Masukkan kata sandi baru untuk akun <strong><?php echo htmlspecialchars($email_from_token); ?></strong>.</p>
        <form id="resetPasswordForm" method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="newPassword">Kata Sandi Baru</label>
                <input type="password" id="newPassword" name="newPassword" minlength="6" required placeholder="Minimal 6 karakter">
            </div>
            <div class="form-group">
                <label for="confirmNewPassword">Konfirmasi Kata Sandi Baru</label>
                <input type="password" id="confirmNewPassword" name="confirmNewPassword" minlength="6" required placeholder="Ulangi kata sandi baru">
            </div>
            <button type="submit" class="btn-submit">Reset Kata Sandi</button>
        </form>
    <?php elseif(!empty($_SESSION['reset_success_message'])): ?>
         <div class="auth-message success"><p><?php echo htmlspecialchars($_SESSION['reset_success_message']); ?></p><?php unset($_SESSION['reset_success_message']); ?></div>
        <p class="auth-link"><a href="login.php">Kembali ke Halaman Masuk</a></p>
    <?php else: ?>
        <p class="auth-link"><a href="forgot_password.php">Minta tautan reset baru</a> atau <a href="login.php">kembali ke Halaman Masuk</a>.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>