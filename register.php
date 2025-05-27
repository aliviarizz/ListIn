<?php
require_once 'includes/db.php'; // Mengandung session_start()

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Cek apakah email sudah ada
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email sudah terdaftar. Gunakan email lain.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_profile_image = 'images/placeholder-profile.png'; // Default image

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, profile_image) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $default_profile_image);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Registrasi berhasil! Silakan masuk dengan akun Anda.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registrasi gagal. Silakan coba lagi. Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<?php require_once 'includes/header.php'; // Ini akan menampilkan bagian <head> dan awal <body> ?>
<title>Daftar Akun - List In</title> <!-- Set judul spesifik halaman -->

    <div class="auth-container">
        <div class="logo-container">
            <h1>List In</h1>
        </div>
        <h2>Buat Akun Baru Anda</h2>
        <p class="subtitle">Isi form di bawah untuk memulai perjalanan produktif Anda.</p>

        <?php if (!empty($errors)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
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
            <button type="submit" class="btn-submit">Daftar Akun</button>
        </form>
        <p class="auth-link">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>

<?php require_once 'includes/footer.php'; // Ini akan menampilkan penutup <body> dan <html> ?>