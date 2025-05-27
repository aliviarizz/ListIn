<?php
require_once 'includes/db.php'; // Mengandung session_start()

$errors = [];

if (isset($_SESSION['user_id'])) { // Jika sudah login, redirect ke dashboard
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
            if (password_verify($password, $user['password'])) {
                // Password cocok
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
            <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <p><?php echo $_SESSION['success_message']; ?></p>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
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
            <button type="submit" class="btn-submit">Masuk Akun</button>
        </form>
        <p class="auth-link">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>

<?php require_once 'includes/footer.php'; ?>