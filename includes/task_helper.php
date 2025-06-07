<?php
// File: includes/task_helper.php

if (!function_exists('render_task_card')) {
    /**
     * Merender kartu tugas HTML.
     *
     * @param array $task Data tugas (harus memiliki 'id', 'title', 'description', 'priority', 'status', 'due_date_formatted', 'due_date').
     * @param string $page_type Konteks halaman ('dashboardTodo', 'dashboardCompleted', 'management', 'history', 'laporanItem').
     * @param string $current_page_for_redirect_url URL lengkap halaman saat ini (dengan query string) untuk parameter 'from' pada link aksi.
     * @param string $history_card_type Khusus untuk $page_type = 'history', menentukan jenis card ('completed' atau 'overdue_uncompleted').
     * @return string HTML string dari kartu tugas.
     */
    function render_task_card($task, $page_type = 'dashboardTodo', $current_page_for_redirect_url = 'dashboard.php', $history_card_type = '') {

        $status_options_display = [
            'Not Started' => 'Belum Mulai',
            'In Progress' => 'Dikerjakan',
            'Completed' => 'Selesai'
        ];
        $current_status_key = $task['status'] ?? 'Not Started';
        $current_status_text = $status_options_display[$current_status_key] ?? $current_status_key;

        $card_status_class = '';
        $is_actually_overdue = $current_status_key != 'Completed' && !empty($task['due_date']) && strtotime($task['due_date']) < strtotime(date('Y-m-d'));

        if ($current_status_key == 'Completed') {
            $card_status_class = 'status-completed-history';
        } elseif ($is_actually_overdue) {
            $card_status_class = 'status-overdue-uncompleted-history';
        } elseif ($current_status_key == 'In Progress') {
            $card_status_class = 'status-in-progress';
        } elseif ($current_status_key == 'Not Started') {
            $card_status_class = 'status-not-started';
        }
        
        // Jika page_type spesifik history, override jika perlu (meskipun logika di atas sudah cukup umum)
        if ($page_type === 'history') {
            if ($history_card_type === 'completed' && $current_status_key == 'Completed') { // Pastikan statusnya memang completed
                $card_status_class = 'status-completed-history';
            } elseif ($history_card_type === 'overdue_uncompleted' && $is_actually_overdue) { // Pastikan memang overdue
                $card_status_class = 'status-overdue-uncompleted-history';
            }
        }


        $task_title_html = '<strong>' . htmlspecialchars($task['title'] ?? 'Tanpa Judul') . '</strong>';

        ob_start();
        ?>
        <div class="task-item-card <?php echo htmlspecialchars($card_status_class); ?>" 
             data-task-id="<?php echo htmlspecialchars($task['id'] ?? 0); ?>" 
             data-current-status="<?php echo htmlspecialchars($current_status_key); ?>">
            <div class="task-details">
                <?php echo $task_title_html; ?>
                <p class="description"><?php echo nl2br(htmlspecialchars(($task['description'] ?? '') ?: 'Tidak ada deskripsi.')); ?></p>
                <p class="meta-info">
                    Prioritas: <span class="priority-<?php echo strtolower(htmlspecialchars($task['priority'] ?? 'Medium')); ?>"><?php echo htmlspecialchars($task['priority'] ?? 'Medium'); ?></span> | 
                    Status: <span class="task-status-text"><?php echo htmlspecialchars($current_status_text); ?></span> | 
                    Deadline: <?php echo htmlspecialchars(($task['due_date_formatted'] ?? '') ?: 'N/A'); ?>
                </p>
            </div>

            <div class="task-actions">
                <?php if ($page_type === 'management'): ?>
                    <?php
                    $edit_action_url = 'edit_tugas.php?id=' . ($task['id'] ?? 0);
                    $delete_action_url = $current_page_for_redirect_url; 
                    $query_separator_delete = (strpos($delete_action_url, '?') === false) ? '?' : '&';
                    $delete_action_url .= $query_separator_delete . 'action=delete&task_id=' . ($task['id'] ?? 0);
                    ?>
                    <a href="<?php echo htmlspecialchars($edit_action_url); ?>" class="edit-btn" title="Edit Tugas"><i class="fas fa-edit"></i></a>
                    <a href="<?php echo htmlspecialchars($delete_action_url); ?>" class="delete-btn" title="Hapus Tugas"><i class="fas fa-trash"></i></a>
                
                <?php elseif ($page_type === 'history'): ?>
                    <?php 
                    $action_handler_page = 'manajemen_tugas.php'; 
                    $reopen_action_url = $action_handler_page . "?task_id=" . ($task['id'] ?? 0) . "&action=reopen&from=" . urlencode($current_page_for_redirect_url);
                    $delete_history_action_url = $action_handler_page . "?task_id=" . ($task['id'] ?? 0) . "&action=delete&from=" . urlencode($current_page_for_redirect_url);
                    ?>
                    <a href="<?php echo htmlspecialchars($reopen_action_url); ?>" class="reopen-btn" title="Buka Kembali Tugas"><i class="fas fa-undo"></i></a>
                    <a href="<?php echo htmlspecialchars($delete_history_action_url); ?>" class="delete-btn" title="Hapus Permanen"><i class="fas fa-trash"></i></a>
                
                <?php elseif ($page_type === 'laporanItem' || $page_type === 'dashboardTodo' || $page_type === 'dashboardCompleted'): ?>
                    <?php // Tidak ada aksi default untuk item laporan atau dashboard di sini ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>