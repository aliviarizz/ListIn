<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_profile_data = null; // Inisialisasi

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

if (!$user_profile_data) { // Jika data tidak ditemukan atau ada error koneksi
    // Redirect atau tampilkan pesan error, tapi pastikan $user_profile_data tidak null
    // Untuk menghindari error di HTML, kita bisa set default array kosong
    $user_profile_data = ['username' => 'N/A', 'email' => 'N/A', 'profile_image' => 'images/placeholder-profile.png', 'created_at' => null];
    // Seharusnya tidak terjadi jika session valid dan koneksi DB baik
    // Jika user_id di session ada tapi data user tidak ada di DB, itu masalah integritas data
}

$profile_image_display_path = (!empty($user_profile_data['profile_image']) && file_exists($user_profile_data['profile_image'])) 
                            ? $user_profile_data['profile_image'] 
                            : 'images/placeholder-profile.png';
$member_since = 'N/A';
if ($user_profile_data['created_at']) {
    try {
        $date_joined = new DateTime($user_profile_data['created_at']);
        $member_since = "Bergabung sejak: " . $date_joined->format('d F Y');
    } catch (Exception $e) { /* Biarkan N/A */ }
}

?>
<title>Profil Saya - List In</title>
        <main class="main">
            <h2 class="page-title">Profil Saya</h2>
            <!-- Tidak ada lagi blok notifikasi di sini -->

            <div class="profile-page-container">
                <div class="profile-card-display">
                    <img src="<?php echo htmlspecialchars($profile_image_display_path); ?>?t=<?php echo time(); // Cache buster ?>" alt="Foto Profil" id="profileImageMain">
                    <h2 id="profileName"><?php echo htmlspecialchars($user_profile_data['username']); ?></h2>
                    <p id="profileEmail"><?php echo htmlspecialchars($user_profile_data['email']); ?></p>
                    <p style="font-size:0.85rem; color:#777; margin-top:10px;"><?php echo $member_since; ?></p>
                    <div class="profile-actions">
                        <a href="edit_profil.php" class="btn btn-primary"><i class="fas fa-user-edit"></i> Edit Profil</a>
                        <a href="ubah_password.php" class="btn btn-secondary"><i class="fas fa-key"></i> Ubah Kata Sandi</a>
                    </div>
                </div>

                <!-- <div class="profile-actions-and-info">
                    <h3 class="section-title">Pengaturan Akun</h3>
                    <div class="profile-actions">
                        <a href="edit_profil.php" class="btn btn-primary"><i class="fas fa-user-edit"></i> Edit Profil</a>
                        <a href="ubah_password.php" class="btn btn-secondary"><i class="fas fa-key"></i> Ubah Kata Sandi</a>
                    </div> 
                    Anda bisa menambahkan info lain di sini jika perlu-->
                <!-- </div> -->
            </div>
        </main>
<?php require_once 'includes/footer.php'; ?>