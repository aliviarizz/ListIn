<?php
// $current_page sudah didefinisikan di header.php
?>
<?php if (!in_array($current_page, ['login.php', 'register.php'])): ?>
        <aside class="sidebar" id="sidebar">
            <nav class="menu">
                <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dasbor</a>
                <a href="manajemen_tugas.php" class="<?php echo ($current_page == 'manajemen_tugas.php' || $current_page == 'edit_tugas.php') ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Kelola Tugas</a>
                <a href="tambah_tugas.php" class="<?php echo ($current_page == 'tambah_tugas.php') ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Tambah Tugas</a>
                <a href="riwayat.php" class="<?php echo ($current_page == 'riwayat.php') ? 'active' : ''; ?>"><i class="fas fa-history"></i> Riwayat</a>
            </nav>
            <a href="logout.php" class="logout" id="logoutButton"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        </aside>
<?php endif; ?>