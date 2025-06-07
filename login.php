<?php
// File: login.php
require_once 'config.php'; // Untuk session_start() dan konstanta aplikasi
require_once 'includes/db.php'; // Untuk koneksi $conn

// Hapus semua kode yang terkait dengan Google Client di sini

$errors = [];

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_SESSION['login_error_message'])) { // Jika ada error dari callback (meskipun callback dihapus, ini untuk jaga-jaga)
    $errors[] = $_SESSION['login_error_message'];
    unset($_SESSION['login_error_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email)) $errors[] = "Alamat email wajib diisi.";
    if (empty($password)) $errors[] = "Kata sandi wajib diisi.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, password, profile_image FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if ($user['password'] !== null && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['profile_image'] = $user['profile_image'];
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Email atau password salah.";
            }
        } else {
            $errors[] = "Email atau password salah.";
        }
        $stmt->close();
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<title>Masuk - List In</title>

    <div class="auth-container">
        <div class="logo-container">
            <h1>List In</h1>
        </div>
        <h2>Selamat Datang Kembali!</h2>
        <p class="subtitle">Masuk untuk melanjutkan dan mengatur tugas Anda.</p>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="auth-message success">
                <p><?php echo $_SESSION['success_message']; ?></p>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['reset_success_message'])): ?>
            <div class="auth-message success">
                <p><?php echo $_SESSION['reset_success_message']; ?></p>
            </div>
            <?php unset($_SESSION['reset_success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="auth-message error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Alamat Email</label>
                <input type="email" id="email" name="email" required placeholder="cth: pengguna@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan kata sandi Anda">
            </div>
            <div class="form-group forgot-password-link">
                <a href="forgot_password.php">Lupa Kata Sandi?</a>
            </div>
            <button type="submit" name="login_submit" class="btn-submit">Masuk Akun</button>
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

        <p class="auth-link">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>