<?php
require_once 'db.php'; // Pastikan db.php di-include pertama untuk session_start()

$current_page = basename($_SERVER['SCRIPT_NAME']);
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_profile_image = 'images/placeholder-profile.png'; // Default

if ($current_user_id && isset($conn)) {
    $stmt_user_header = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    if ($stmt_user_header) {
        $stmt_user_header->bind_param("i", $current_user_id);
        $stmt_user_header->execute();
        $result_user_header = $stmt_user_header->get_result();
        if ($user_data_header = $result_user_header->fetch_assoc()) {
            $current_user_profile_image = !empty($user_data_header['profile_image']) && file_exists($user_data_header['profile_image']) ? $user_data_header['profile_image'] : 'images/placeholder-profile.png';
        }
        $stmt_user_header->close();
    }
}

// Fungsi untuk menambahkan notifikasi ke session
function add_notification($message, $type = 'info') { // type bisa 'success', 'error', 'info'
    if (!isset($_SESSION['notification_messages'])) {
        $_SESSION['notification_messages'] = [];
    }
    // Tambahkan timestamp ke pesan agar unik dan bisa diurutkan jika perlu
    $_SESSION['notification_messages'][] = ['message' => $message, 'type' => $type, 'time' => time()];
    // Batasi jumlah notifikasi di session agar tidak terlalu banyak
    if (count($_SESSION['notification_messages']) > 7) { // Misal maksimal 7 notifikasi
        array_shift($_SESSION['notification_messages']); // Hapus yang paling lama
    }
    // Tandai bahwa ada notifikasi baru yang belum dibaca untuk badge
    $_SESSION['has_unread_notifications_badge'] = true;
}

// Tentukan apakah ikon lonceng punya tanda 'has-notif' berdasarkan flag baru
$has_unread_badge_for_icon = $_SESSION['has_unread_notifications_badge'] ?? false;


function format_tanggal_indonesia_header($date_str) {
    if (empty($date_str) || $date_str == '0000-00-00') return 'N/A';
    try { $date = new DateTime($date_str); } catch (Exception $e) { return 'Invalid Date'; }
    $hari_arr = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $bulan_arr = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $hari = $hari_arr[(int)$date->format('w')];
    $tanggal = $date->format('j');
    $bulan = $bulan_arr[(int)$date->format('n')];
    return $hari . ', ' . $tanggal . ' ' . $bulan;
}
$today_display_full = format_tanggal_indonesia_header(date('Y-m-d'));
$parts_today_display = explode(', ', $today_display_full);
$day_name_display = $parts_today_display[0] ?? '';
$date_str_display = $parts_today_display[1] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <?php if (in_array($current_page, ['login.php', 'register.php'])): ?>
        <link rel="stylesheet" href="css/auth.css">
    <?php endif; ?>
</head>
<body class="<?php echo in_array($current_page, ['login.php', 'register.php']) ? 'auth-page' : ''; ?>">
    <?php if (in_array($current_page, ['login.php', 'register.php'])): ?>
        <script> document.documentElement.classList.add('auth-html'); </script>
    <?php endif; ?>

    <?php if (!in_array($current_page, ['login.php', 'register.php'])): ?>
    <header class="header">
        <div class="header-left">
            <a href="profil.php">
                <img src="<?php echo htmlspecialchars($current_user_profile_image); ?>?t=<?php echo time();?>" alt="Foto Profil" class="header-profile-pic" id="headerProfilePic">
            </a>
            <h2>List In</h2>
        </div>
        <?php
        $hide_search_bar_pages = ['profil.php', 'edit_profil.php', 'ubah_password.php', 'tambah_tugas.php', 'edit_tugas.php', 'dashboard.php'];
        $hide_search_bar = in_array($current_page, $hide_search_bar_pages);
        $search_action_page = 'manajemen_tugas.php';
        if ($current_page == 'riwayat.php') $search_action_page = 'riwayat.php';
        ?>
        <div class="search-bar" <?php if ($hide_search_bar) echo 'style="visibility: hidden;"'; ?>>
            <form action="<?php echo $search_action_page; ?>" method="GET" style="display:flex; width:100%;">
                <input type="text" name="search_term" id="searchInputGlobal" placeholder="Cari di <?php echo ($current_page == 'riwayat.php' ? 'Riwayat' : 'Manajemen'); ?>..." value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                <button type="submit" style="background:none; border:none; padding:0 0 0 8px; margin-left:auto; cursor:pointer;"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="header-right">
             <i class="fas fa-bell <?php if ($has_unread_badge_for_icon) echo 'has-notif'; ?>" id="bellIcon" title="Notifikasi"></i>
            <i class="fas fa-calendar-alt" id="calendarIcon" title="Kalender"></i>
            <div class="date-container">
                <p><?php echo htmlspecialchars($day_name_display); ?></p>
                <span><?php echo htmlspecialchars($date_str_display); ?></span>
            </div>
        </div>
    </header>
    <div class="content">
    <?php endif; ?>