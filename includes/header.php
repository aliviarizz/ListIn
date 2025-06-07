<?php
// File: includes/header.php
require_once dirname(__DIR__) . '/config.php'; // Memuat config.php dari root (sudah ada session_start)
require_once __DIR__ . '/db.php'; // Koneksi DB

$current_page = basename($_SERVER['SCRIPT_NAME']);
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_profile_image = 'images/placeholder-profile.png'; // Default

if ($current_user_id && isset($conn)) {
    $stmt_user_header = $conn->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    if ($stmt_user_header) {
        $stmt_user_header->bind_param("i", $current_user_id);
        $stmt_user_header->execute();
        $result_user_header = $stmt_user_header->get_result();
        if ($user_data_header = $result_user_header->fetch_assoc()) {
            // Pastikan nama pengguna di session selalu update dari DB saat header load
            $_SESSION['username'] = $user_data_header['username']; 
            
            $current_user_profile_image_db = $user_data_header['profile_image'];
             // Cek jika path gambar dari DB valid dan file ada, jika tidak, gunakan placeholder
            if (!empty($current_user_profile_image_db) && file_exists(dirname(__DIR__) . '/' . $current_user_profile_image_db)) {
                $current_user_profile_image = $current_user_profile_image_db;
            } elseif (!empty($current_user_profile_image_db) && filter_var($current_user_profile_image_db, FILTER_VALIDATE_URL)) {
                // Jika itu URL (misal dari Google), gunakan langsung
                $current_user_profile_image = $current_user_profile_image_db;
            }
            // Update session profile image jika berbeda dari yang di DB (misal setelah login Google)
            if (($_SESSION['profile_image'] ?? '') !== $current_user_profile_image) {
                 $_SESSION['profile_image'] = $current_user_profile_image;
            }

        }
        $stmt_user_header->close();
    }
}


// Fungsi add_notification, pengecekan deadline, dan overdue tetap sama seperti sebelumnya
// ... (Salin fungsi add_notification, cek deadline H-1, cek overdue dari kode Anda sebelumnya) ...
function add_notification($message, $type = 'info') {
    if (!isset($_SESSION['notification_messages'])) {
        $_SESSION['notification_messages'] = [];
    }
    $new_notification = ['message' => $message, 'type' => $type, 'time' => time()];
    
    $is_duplicate_notif = false;
    if ($type === 'deadline_soon' || $type === 'overdue_task') { 
        $message_core_part = '';
        if (preg_match('/Tugas(?:-tugas)?\s*(".*?")\s*(akan jatuh tempo besok|telah melewati batas waktu)/', $new_notification['message'], $matches)) {
            $message_core_part = $matches[1]; 
        } elseif (preg_match('/(".*?")/', $new_notification['message'], $matches_generic)) {
            $message_core_part = $matches_generic[1];
        }

        foreach ($_SESSION['notification_messages'] as $existing_notif) {
            if (isset($existing_notif['message']) &&
                ($message_core_part && strpos($existing_notif['message'], $message_core_part) !== false) && 
                $existing_notif['type'] === $type &&
                (time() - ($existing_notif['time'] ?? 0)) < 3600 * 3) { 
                $is_duplicate_notif = true;
                break;
            }
        }
    }

    if (!$is_duplicate_notif) {
        $_SESSION['notification_messages'][] = $new_notification;
        if (count($_SESSION['notification_messages']) > 10) { 
            array_shift($_SESSION['notification_messages']);
        }
        $_SESSION['has_unread_notifications_badge'] = true;
    }
}

if ($current_user_id && isset($conn) && !in_array($current_page, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'google_auth_callback.php'])) {
    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
    $today_for_notif_check_h1 = date('Y-m-d');
    $notif_key_deadline_h1 = 'deadline_h1_notif_sent_' . $today_for_notif_check_h1 . '_uid' . $current_user_id;

    if (!isset($_SESSION[$notif_key_deadline_h1])) {
        $stmt_deadline_check_h1 = $conn->prepare("SELECT title FROM tasks WHERE user_id = ? AND due_date = ? AND status != 'Completed'");
        if ($stmt_deadline_check_h1) {
            $stmt_deadline_check_h1->bind_param("is", $current_user_id, $tomorrow_date);
            $stmt_deadline_check_h1->execute();
            $result_deadline_tasks_h1 = $stmt_deadline_check_h1->get_result();
            $tasks_deadline_tomorrow = [];
            while ($task_for_deadline_notif_h1 = $result_deadline_tasks_h1->fetch_assoc()) {
                $tasks_deadline_tomorrow[] = htmlspecialchars($task_for_deadline_notif_h1['title']);
            }
            $stmt_deadline_check_h1->close();

            if (!empty($tasks_deadline_tomorrow)) {
                $task_list_str_h1 = implode(", ", array_map(function($title) { return "\"".$title."\""; }, $tasks_deadline_tomorrow));
                $message_plural_h1 = count($tasks_deadline_tomorrow) > 1 ? "Tugas-tugas" : "Tugas";
                $deadline_message_h1 = "<span class='message-content'><strong>PERHATIAN:</strong> $message_plural_h1 $task_list_str_h1 akan jatuh tempo besok!</span>";
                add_notification($deadline_message_h1, "deadline_soon");
                $_SESSION[$notif_key_deadline_h1] = true; 
            }
        } else {
            error_log("Failed to prepare statement for H-1 deadline check: " . $conn->error);
        }
    }
}

if ($current_user_id && isset($conn) && !in_array($current_page, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'google_auth_callback.php'])) {
    $today_for_overdue_check = date('Y-m-d');
    $notif_key_overdue = 'overdue_notif_sent_' . $today_for_overdue_check . '_uid' . $current_user_id;

    if (!isset($_SESSION[$notif_key_overdue])) {
        $stmt_overdue_check = $conn->prepare(
            "SELECT id, title FROM tasks 
             WHERE user_id = ? AND due_date < CURDATE() AND status != 'Completed' 
             AND (last_overdue_notif_sent IS NULL OR DATE(last_overdue_notif_sent) < CURDATE())"
        );
        
        if ($stmt_overdue_check) {
            $stmt_overdue_check->bind_param("i", $current_user_id);
            $stmt_overdue_check->execute();
            $result_overdue_tasks = $stmt_overdue_check->get_result();
            $tasks_overdue = [];
            $task_ids_to_update_notif_sent = [];

            while ($task_overdue = $result_overdue_tasks->fetch_assoc()) {
                $tasks_overdue[] = htmlspecialchars($task_overdue['title']);
                $task_ids_to_update_notif_sent[] = $task_overdue['id'];
            }
            $stmt_overdue_check->close();

            if (!empty($tasks_overdue)) {
                $task_list_str_overdue = implode(", ", array_map(function($title) { return "\"".$title."\""; }, $tasks_overdue));
                $message_plural_overdue = count($tasks_overdue) > 1 ? "Tugas-tugas" : "Tugas";
                $overdue_message = "<span class='message-content'><strong>TERLEWAT:</strong> $message_plural_overdue $task_list_str_overdue telah melewati batas waktu!</span>";
                add_notification($overdue_message, "deadline_soon"); 

                if (!empty($task_ids_to_update_notif_sent)) {
                    $ids_placeholder = implode(',', array_fill(0, count($task_ids_to_update_notif_sent), '?'));
                    $stmt_update_notif_date = $conn->prepare(
                        "UPDATE tasks SET last_overdue_notif_sent = NOW() WHERE id IN ($ids_placeholder) AND user_id = ?"
                    );
                    if ($stmt_update_notif_date) {
                        $types_update = str_repeat('i', count($task_ids_to_update_notif_sent)) . 'i';
                        $params_update = array_merge($task_ids_to_update_notif_sent, [$current_user_id]);
                        $stmt_update_notif_date->bind_param($types_update, ...$params_update);
                        if (!$stmt_update_notif_date->execute()) {
                            error_log("Failed to update last_overdue_notif_sent: " . $stmt_update_notif_date->error);
                        }
                        $stmt_update_notif_date->close();
                    }
                }
                $_SESSION[$notif_key_overdue] = true; 
            }
        } else {
            error_log("Failed to prepare statement for overdue check: " . $conn->error);
        }
    }
}

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

// Halaman-halaman yang dianggap sebagai bagian "auth" flow
$auth_pages = ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'google_auth_callback.php'];
$is_auth_page = in_array($current_page, $auth_pages);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      // Skrip FOUC Prevent
      (function() {
        const theme = localStorage.getItem('theme');
        const htmlEl = document.documentElement;
        if (theme === 'dark-theme' || (!theme && window.matchMedia?.('(prefers-color-scheme: dark)').matches)) {
          htmlEl.classList.add('dark-theme-active');
        }
        <?php if ($is_auth_page): ?>
          htmlEl.classList.add('auth-html');
        <?php endif; ?>
      })();
    </script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <?php if ($is_auth_page): ?>
        <link rel="stylesheet" href="css/auth.css?v=<?php echo time(); ?>">
    <?php endif; ?>

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=3">
    <link rel="manifest" href="/site.webmanifest?v=2">
    
</head>
<body class="<?php echo $is_auth_page ? 'auth-page' : ''; ?>">

    <?php if (!$is_auth_page): ?>
    <header class="header">
        <div class="header-left">
            <a href="profil.php">
                 <img src="<?php echo htmlspecialchars($current_user_profile_image); ?>?t=<?php echo time();?>" alt="Foto Profil" class="header-profile-pic" id="headerProfilePic">
            </a>
            <h2 class="app-title-toggle" id="appTitleToggle" title="Toggle Sidebar">List In</h2>
        </div>
        <?php
        $hide_search_bar_pages = ['profil.php', 'edit_profil.php', 'ubah_password.php', 'tambah_tugas.php', 'edit_tugas.php', 'dashboard.php', 'laporan.php']; // Tambah laporan.php
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