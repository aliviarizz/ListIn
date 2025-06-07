<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php';
require_once 'includes/db.php';
require __DIR__ . '/vendor/autoload.php';

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Masukkan alamat email yang valid.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Email ditemukan, lanjutkan proses
            $token = bin2hex(random_bytes(50));
            
            // Hapus semua token LAMA untuk email ini untuk kebersihan
            $stmt_delete_old = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete_old->bind_param("s", $email);
            $stmt_delete_old->execute();
            $stmt_delete_old->close();

            // Simpan token BARU ke database
            $stmt_insert_token = $conn->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
            $stmt_insert_token->bind_param("ss", $email, $token);
            
            if ($stmt_insert_token->execute()) {
                // Kirim email reset password
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = SMTP_SECURE;
                    $mail->Port       = SMTP_PORT;

                    $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
                    $mail->addAddress($email);

                    $reset_link = APP_URL . "/reset_password.php?token=" . $token;
                    $mail->isHTML(true);
                    $mail->Subject = 'Reset Kata Sandi Akun List In Anda';
                    $mail->Body    = "Halo,<br><br>Kami menerima permintaan untuk mereset kata sandi akun Anda di List In.<br>"
                                   . "Silakan klik tautan di bawah ini untuk mengatur ulang kata sandi Anda (tautan valid selama 60 menit):<br>"
                                   . "<a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>"
                                   . "Jika Anda tidak meminta reset kata sandi, abaikan email ini.<br><br>"
                                   . "Salam,<br>Tim List In";

                    $mail->send();
                    $success_message = 'Email instruksi reset kata sandi telah dikirim. Silakan periksa kotak masuk (dan folder spam) Anda.';
                } catch (Exception $e) {
                    $errors[] = "Gagal mengirim email. Silakan coba lagi nanti.";
                    error_log("Mailer Error: {$mail->ErrorInfo}");
                }
            } else {
                $errors[] = "Gagal memproses permintaan Anda. Silakan coba lagi.";
                error_log("Token save error: " . $stmt_insert_token->error);
            }
            $stmt_insert_token->close();
        } else {
            // Email tidak ditemukan, tapi tampilkan pesan yang sama untuk keamanan
            $success_message = 'Jika alamat email Anda terdaftar, kami telah mengirimkan instruksi reset kata sandi.';
        }
        $stmt->close();
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<title>Lupa Kata Sandi - List In</title>

<div class="auth-container">
    <div class="logo-container"><h1>List In</h1></div>
    <h2>Lupa Kata Sandi?</h2>
    <p class="subtitle">Masukkan alamat email Anda. Kami akan mengirimkan tautan untuk mereset kata sandi Anda.</p>

    <?php if (!empty($success_message)): ?>
        <div class="auth-message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="auth-message error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($success_message)): ?>
    <form id="forgotPasswordForm" method="POST" action="forgot_password.php">
        <div class="form-group">
            <label for="email">Alamat Email</label>
            <input type="email" id="email" name="email" required placeholder="Masukkan email terdaftar Anda">
        </div>
        <button type="submit" class="btn-submit">Kirim Tautan Reset</button>
    </form>
    <?php endif; ?>

    <p class="auth-link">Ingat kata sandi Anda? <a href="login.php">Masuk di sini</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>