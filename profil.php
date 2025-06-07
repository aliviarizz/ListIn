<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_profile_data = null; 

if (isset($conn) && $conn) {
    $stmt_profile = $conn->prepare("SELECT username, email, profile_image, created_at FROM users WHERE id = ?");
    if ($stmt_profile) {
        $stmt_profile->bind_param("i", $user_id);
        $stmt_profile->execute();
        $result_profile = $stmt_profile->get_result();
        $user_profile_data = $result_profile->fetch_assoc();
        $stmt_profile->close();
    } else {
        add_notification("Gagal mengambil data profil: " . $conn->error, "error");
    }
} else {
    add_notification("Koneksi database tidak tersedia.", "error");
}

if (!$user_profile_data) { 
    $user_profile_data = ['username' => 'N/A', 'email' => 'N/A', 'profile_image' => 'images/placeholder-profile.png', 'created_at' => null];
}

$profile_image_display_path = (!empty($user_profile_data['profile_image']) && file_exists($user_profile_data['profile_image'])) 
                            ? $user_profile_data['profile_image'] 
                            : 'images/placeholder-profile.png';
$member_since = 'N/A';
if ($user_profile_data['created_at']) {
    try {
        $date_joined = new DateTime($user_profile_data['created_at']);
        $member_since = "Bergabung sejak " . $date_joined->format('d F Y');
    } catch (Exception $e) { /* Biarkan N/A */ }
}
?>
<title>Profil Saya - List In</title>
        <main class="main">
            <h2 class="page-title">Profil</h2>
            
            <div class="profile-page-container">
                <div class="profile-info-card">
                    <br>
                    <br>
                    <img src="<?php echo htmlspecialchars($profile_image_display_path); ?>?t=<?php echo time(); ?>" alt="Foto Profil" id="profileImageMain">
                    <h2 id="profileName"><?php echo htmlspecialchars($user_profile_data['username']); ?></h2>
                    <p id="profileEmail"><?php echo htmlspecialchars($user_profile_data['email']); ?></p>
                    <p class="member-since"><?php echo $member_since; ?></p>
                </div>

                <div class="profile-actions-card">
                    <h3 class="card-title">Pengaturan Akun</h3>
                    <div class="profile-action-buttons">
                        <a href="edit_profil.php" class="btn btn-primary">
                            <i class="fas fa-user-edit"></i> Edit Informasi Profil
                        </a>
                        <a href="ubah_password.php" class="btn btn-secondary">
                            <i class="fas fa-key"></i> Ubah Kata Sandi
                        </a>
                    </div>

                    <h3 class="card-title" style="margin-top: 25px;">Preferensi Tampilan</h3>
                    <div class="profile-theme-settings">
                        <div class="theme-toggle-item">
                            <span><i class="fas fa-palette"></i> Tema Gelap</span>
                            <label class="theme-toggle-switch">
                                <input type="checkbox" id="themeToggleCheckbox">
                                <span class="theme-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>