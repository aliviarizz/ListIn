<?php
// $current_page sudah didefinisikan di header.php
$auth_pages_sidebar = ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'google_auth_callback.php'];
$is_auth_page_sidebar = in_array($current_page, $auth_pages_sidebar);
?>
<?php if (!$is_auth_page_sidebar): ?>
        <aside class="sidebar" id="sidebar">
            <nav class="menu">
                <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> <span>Dasbor</span></a>
                <a href="manajemen_tugas.php" class="<?php echo ($current_page == 'manajemen_tugas.php' || $current_page == 'edit_tugas.php') ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> <span>Kelola Tugas</span></a>
                <a href="tambah_tugas.php" class="<?php echo ($current_page == 'tambah_tugas.php') ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> <span>Tambah Tugas</span></a>
                <a href="laporan.php" class="<?php echo ($current_page == 'laporan.php') ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> <span>Laporan</span></a>
                <a href="riwayat.php" class="<?php echo ($current_page == 'riwayat.php') ? 'active' : ''; ?>"><i class="fas fa-history"></i> <span>Riwayat</span></a>
                <!-- BARU: Menu Chatbot -->
                <a href="bot_manager.php" class="<?php echo ($current_page == 'bot_manager.php') ? 'active' : ''; ?>"><i class="fas fa-robot"></i> <span>Bot Manager</span></a>
            </nav>
            <a href="logout.php" class="logout" id="logoutButton"><i class="fas fa-sign-out-alt"></i> <span>Keluar</span></a>
        </aside>
<?php endif; ?>
