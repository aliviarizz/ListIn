<?php
// File: register.php
require_once 'config.php'; // Untuk session_start() dan konstanta aplikasi
require_once 'includes/db.php'; // Untuk koneksi $conn

// Hapus semua kode yang terkait dengan Google Client di sini

$errors = [];

if (isset($_SESSION['user_id'])) { // Jika sudah login, redirect
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_submit'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    if (empty($username)) $errors[] = "Nama pengguna wajib diisi.";
    if (empty($email)) $errors[] = "Alamat email wajib diisi.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
    if (empty($password)) $errors[] = "Kata sandi wajib diisi.";
    elseif (strlen($password) < 6) $errors[] = "Kata sandi minimal 6 karakter.";
    if ($password !== $confirmPassword) $errors[] = "Password dan konfirmasi password tidak cocok.";

    if (empty($errors)) {
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $errors[] = "Email sudah terdaftar. Gunakan email lain atau masuk.";
        }
        $stmt_check_email->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_profile_image = 'images/placeholder-profile.png';

        // Password sekarang wajib karena tidak ada login Google
        $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password, profile_image, email_verified_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_insert->bind_param("ssss", $username, $email, $hashed_password, $default_profile_image);
        
        if ($stmt_insert->execute()) {
            $_SESSION['success_message'] = "Registrasi berhasil! Silakan masuk dengan akun Anda.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registrasi gagal. Silakan coba lagi. Error: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<title>Daftar Akun - List In</title>

    <div class="auth-container">
        <div class="logo-container">
            <h1>List In</h1>
        </div>
        <h2>Buat Akun Baru Anda</h2>
        <p class="subtitle">Isi form di bawah untuk memulai perjalanan produktif Anda.</p>

        <?php if (!empty($errors)): ?>
            <div class="auth-message error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Nama Pengguna</label>
                <input type="text" id="username" name="username" required placeholder="cth: Amanuel" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" required placeholder="cth: pengguna@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" minlength="6" required placeholder="Minimal 6 karakter">
            </div>
            <div class="form-group">
                <label for="confirmPassword">Konfirmasi Kata Sandi</label>
                <input type="password" id="confirmPassword" name="confirmPassword" minlength="6" required placeholder="Ulangi kata sandi">
            </div>
            <button type="submit" name="register_submit" class="btn-submit">Daftar Akun</button>
        </form>

        <!-- HAPUS BAGIAN LOGIN SOSIAL -->
        <!--
        <div class="social-login-divider">
            <span>ATAU</span>
        </div>
        <a href="#" class="btn-social-login google">
            <i class="fab fa-google"></i> Lanjutkan dengan Google
        </a>
        -->

        <p class="auth-link">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>