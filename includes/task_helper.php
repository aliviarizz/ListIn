<?php
// File: includes/task_helper.php

if (!function_exists('render_task_card')) {
    function render_task_card($task, $page_type = 'dashboardTodo', $current_page_for_redirect = 'dashboard.php') {
        global $conn; 

        $status_options = [
            'Not Started' => 'Belum Mulai',
            'In Progress' => 'Dikerjakan',
            'Completed' => 'Selesai'
        ];
        
        $current_filters_array = [];
        if (isset($_GET['search_term']) && !empty(trim($_GET['search_term']))) $current_filters_array['search_term'] = trim($_GET['search_term']);
        // ... (tambahkan filter lain jika perlu dipertahankan dari halaman lain)

        $query_params_for_redirect = !empty($current_filters_array) ? '?' . http_build_query($current_filters_array) : '';

        ob_start();
        ?>
        <div class="task-item-card <?php echo ($task['status'] == 'Completed') ? 'completed' : ''; ?>">
            <div class="task-details">
                <a href="manajemen_tugas.php?search_term=<?php echo urlencode($task['title']); ?>" class="task-title-link" title="Lihat di Manajemen Tugas">
                    <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                </a>
                <p class="description"><?php echo nl2br(htmlspecialchars($task['description'] ?: 'Tidak ada deskripsi.')); ?></p>
                <p class="meta-info">
                    Prioritas: <span class="priority-<?php echo strtolower(htmlspecialchars($task['priority'])); ?>"><?php echo htmlspecialchars($task['priority']); ?></span> | 
                    Status: <?php echo htmlspecialchars($status_options[$task['status']]); ?> | 
                    Deadline: <?php echo htmlspecialchars($task['due_date_formatted'] ?: 'N/A'); ?>
                </p>
            </div>

            <?php // Hapus semua aksi jika di dashboard
            if ($page_type !== 'dashboardTodo' && $page_type !== 'dashboardCompleted'): ?>
            <div class="task-actions">
                <?php 
                $action_base_url = "manajemen_tugas.php?task_id=" . $task['id'];
                $redirect_param = "&from=" . urlencode($current_page_for_redirect . $query_params_for_redirect);
                ?>

                <?php if ($page_type === 'management'): // Aksi hanya untuk manajemen ?>
                    <form action="<?php echo $action_base_url . '&action=update_status' . $redirect_param; ?>" method="POST" style="display:inline;">
                        <select name="new_status" onchange="this.form.submit()" title="Ubah Status Tugas" class="task-status-select">
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($task['status'] == $value) ? "selected" : ""; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <a href="edit_tugas.php?id=<?php echo $task['id']; ?>" class="edit-btn" title="Edit Tugas"><i class="fas fa-edit"></i></a>
                    <a href="<?php echo $action_base_url . '&action=delete' . $redirect_param; ?>" class="delete-btn" title="Hapus Tugas"><i class="fas fa-trash"></i></a>
                <?php elseif ($page_type === 'history'): // Aksi hanya untuk riwayat ?>
                    <a href="<?php echo $action_base_url . '&action=reopen' . $redirect_param; ?>" class="reopen-btn" title="Buka Kembali Tugas"><i class="fas fa-undo"></i></a>
                    <a href="<?php echo $action_base_url . '&action=delete' . $redirect_param; ?>" class="delete-btn" title="Hapus Permanen"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; // Akhir dari if bukan dashboard ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>