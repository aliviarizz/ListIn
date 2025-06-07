<?php
// $current_page dari header.php
$no_standard_footer_pages = [
    'login.php', 'register.php', 'forgot_password.php', 'reset_password.php', 'google_auth_callback.php',
];
$is_special_page_footer = in_array($current_page, $no_standard_footer_pages);

?>
<?php if (!$is_special_page_footer): ?>
    </div> <!-- Penutup div.content dari header.php -->

    <div id="notification-popup">
        <div class="notification-header">
             <h4>Notifikasi</h4>
             <button id="clearAllNotificationsBtn" class="btn-clear-notif" title="Hapus semua notifikasi">Hapus Semua</button>
        </div>
        <ul id="notification-list">
            <?php 
            $current_session_notifications_for_popup = [];
            if (isset($_SESSION['notification_messages']) && is_array($_SESSION['notification_messages'])) {
                $current_session_notifications_for_popup = $_SESSION['notification_messages'];
                usort($current_session_notifications_for_popup, function($a, $b) {
                    return ($b['time'] ?? 0) - ($a['time'] ?? 0);
                });
            }
            ?>
            <?php if (!empty($current_session_notifications_for_popup)): ?>
                <?php foreach ($current_session_notifications_for_popup as $notif_item): 
                    $time_ago_popup = 'Beberapa waktu lalu';
                    if (isset($notif_item['time'])) { 
                        try {
                            $timestamp_popup = new DateTime('@' . $notif_item['time']);
                            $now_popup = new DateTime(); $interval_popup = $now_popup->diff($timestamp_popup);
                            if ($interval_popup->y > 0) $time_ago_popup = $interval_popup->y . " thn lalu";
                            elseif ($interval_popup->m > 0) $time_ago_popup = $interval_popup->m . " bln lalu";
                            elseif ($interval_popup->d > 0) $time_ago_popup = $interval_popup->d . " hr lalu";
                            elseif ($interval_popup->h > 0) $time_ago_popup = $interval_popup->h . " jam lalu";
                            elseif ($interval_popup->i > 0) $time_ago_popup = $interval_popup->i . " mnt lalu";
                            else $time_ago_popup = "Baru saja";
                        } catch (Exception $e) { /* Biarkan default */ }
                    }
                    $message_class_popup = '';
                    if(isset($notif_item['type'])) {
                        if($notif_item['type'] == 'success') $message_class_popup = 'notif-success';
                        else if($notif_item['type'] == 'error') $message_class_popup = 'notif-error';
                        else if($notif_item['type'] == 'info') $message_class_popup = 'notif-info';
                        else if($notif_item['type'] == 'deadline_soon') $message_class_popup = 'notif-deadline_soon';
                    }
                ?>
                    <li class="<?php echo htmlspecialchars($message_class_popup); ?>">
                        <?php echo $notif_item['message']; ?> 
                        <small class="notif-time"><?php echo htmlspecialchars($time_ago_popup); ?></small>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="no-notifications">Tidak ada notifikasi baru.</li>
            <?php endif; ?>
        </ul>
    </div>
    <div id="calendar-popup">
        <div id="calendar-container-popup"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bodyElement = document.body; 
            const htmlElement = document.documentElement; 
            const bellIcon = document.getElementById('bellIcon');
            const notificationPopup = document.getElementById('notification-popup');
            const notificationListUl = document.getElementById('notification-list');
            const clearAllNotificationsBtn = document.getElementById('clearAllNotificationsBtn');

            const calendarIcon = document.getElementById('calendarIcon');
            const calendarPopup = document.getElementById('calendar-popup');

            function togglePopup(popupElement, iconElement, otherPopupElement) {
                // Tutup popup lain jika sedang terbuka
                if (otherPopupElement && otherPopupElement.classList.contains('show')) {
                    otherPopupElement.classList.remove('show');
                }
                
                // Toggle popup yang diklik
                popupElement.classList.toggle('show');
                
                // Jika popup notifikasi dibuka, tandai sudah dibaca
                if (popupElement === notificationPopup && popupElement.classList.contains('show')) {
                    if (bellIcon && bellIcon.classList.contains('has-notif')) {
                        fetch('mark_notifications_viewed.php')
                            .then(response => response.json())
                            .then(data => {
                                if(data.success) {
                                     if(bellIcon) bellIcon.classList.remove('has-notif');
                                }
                            }).catch(error => console.error('Error marking notifications viewed:', error));
                    }
                }
            }

            if (bellIcon && notificationPopup) { 
                bellIcon.addEventListener('click', (e) => { 
                    e.stopPropagation(); 
                    togglePopup(notificationPopup, bellIcon, calendarPopup); 
                });
            }

            if (clearAllNotificationsBtn && notificationListUl) {
                clearAllNotificationsBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); 
                    if (confirm('Anda yakin ingin menghapus semua notifikasi?')) {
                        fetch('ajax_clear_all_notifications.php') 
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    notificationListUl.innerHTML = '<li class="no-notifications">Tidak ada notifikasi baru.</li>';
                                    if (bellIcon && bellIcon.classList.contains('has-notif')) {
                                        bellIcon.classList.remove('has-notif'); 
                                    }
                                } else { alert('Gagal menghapus notifikasi.'); }
                            })
                            .catch(error => {
                                console.error('Error clearing all notifications:', error);
                                alert('Terjadi kesalahan saat menghapus notifikasi.');
                            });
                    }
                });
            }

            if (calendarIcon && calendarPopup) { 
                flatpickr(document.getElementById('calendar-container-popup'), { inline: true, dateFormat: "d/m/Y", locale: "id" });
                calendarIcon.addEventListener('click', function (e) { 
                    e.stopPropagation(); 
                    togglePopup(calendarPopup, calendarIcon, notificationPopup); 
                });
            }
            
            // Event listener untuk menutup popup jika klik di luar
            document.addEventListener('click', function (e) { 
                // Cek notifikasi
                if (notificationPopup && notificationPopup.classList.contains('show') && !notificationPopup.contains(e.target) && e.target !== bellIcon) { 
                    notificationPopup.classList.remove('show'); 
                }
                // Cek kalender
                if (calendarPopup && calendarPopup.classList.contains('show') && !calendarPopup.contains(e.target) && e.target !== calendarIcon) { 
                    calendarPopup.classList.remove('show'); 
                }
            });

            const dateInputs = document.querySelectorAll('input[type="text"][id$="Date"], input[type="text"][id$="DueDate"], input[type="text"][id$="DateRange"], input[type="text"][id="filterHistoryDateRange"], input[type="text"][id="filterReportDateRange"], input[type="text"][id="reportPdfDateRange"]');
            dateInputs.forEach(input => {
                let config = { dateFormat: "d/m/Y", locale: "id", allowInput: true };
                if (input.id === 'taskDueDate' || input.id === 'editTaskDueDate') {
                     config.minDate = "today";
                }
                if (input.id === 'filterHistoryDateRange' || input.id === 'filterPerformanceDateRange' || input.id === 'filterReportDateRange' || input.id === 'reportPdfDateRange') { 
                    config.mode = "range";
                } else if (input.id === 'filterDate') { 
                    config.mode = "single";
                }
                flatpickr(input, config);
            });

            const deleteButtons = document.querySelectorAll('a.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    if (!confirm('Anda yakin ingin menghapus item ini?')) {
                        event.preventDefault();
                    }
                });
            });
            
            const editProfileImageInput = document.getElementById('editProfileImageFile');
            const currentImagePreview = document.getElementById('currentImagePreview');
            if (editProfileImageInput && currentImagePreview) {
                editProfileImageInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            currentImagePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            const themeToggleCheckbox = document.getElementById('themeToggleCheckbox');
            function updateThemeOnPage(theme) {
                if (theme === 'dark-theme') {
                    htmlElement.classList.add('dark-theme-active');
                    if (themeToggleCheckbox) themeToggleCheckbox.checked = true;
                } else { 
                    htmlElement.classList.remove('dark-theme-active');
                    if (themeToggleCheckbox) themeToggleCheckbox.checked = false;
                }
            }
            const initialThemeIsDark = htmlElement.classList.contains('dark-theme-active');
            if (themeToggleCheckbox) { 
                if (initialThemeIsDark) {
                    themeToggleCheckbox.checked = true;
                } else {
                    themeToggleCheckbox.checked = false;
                }
                themeToggleCheckbox.addEventListener('change', function() {
                    const newTheme = this.checked ? 'dark-theme' : 'light-theme';
                    updateThemeOnPage(newTheme); 
                    localStorage.setItem('theme', newTheme); 
                });
            }
            
            const appTitleToggle = document.getElementById('appTitleToggle');
            const sidebarElement = document.getElementById('sidebar'); 
            if (appTitleToggle && sidebarElement) {
                function setSidebarState(isHidden) {
                    if (isHidden) bodyElement.classList.add('sidebar-hidden');
                    else bodyElement.classList.remove('sidebar-hidden');
                }
                if (window.innerWidth > 768) {
                    const sidebarHiddenStored = localStorage.getItem('sidebarHidden') === 'true';
                    setSidebarState(sidebarHiddenStored);
                } else {
                    setSidebarState(false); 
                }
                appTitleToggle.addEventListener('click', () => {
                    if (window.innerWidth > 768) { 
                        const isNowHidden = bodyElement.classList.toggle('sidebar-hidden');
                        localStorage.setItem('sidebarHidden', isNowHidden);
                    }
                });
                window.addEventListener('resize', () => {
                    if (window.innerWidth <= 768) {
                        setSidebarState(false); 
                    } else {
                        const sidebarHiddenStored = localStorage.getItem('sidebarHidden') === 'true';
                        setSidebarState(sidebarHiddenStored);
                    }
                });
            }

            if (document.querySelector('.main-content-manajemen')) {
                const taskListContainerManajemen = document.getElementById('managementTaskList');
                if (taskListContainerManajemen) {
                    taskListContainerManajemen.addEventListener('click', function(event) {
                        const card = event.target.closest('.task-item-card[data-task-id]');
                        if (!card) return; 
                        if (event.target.closest('.task-actions') || event.target.closest('a.task-title-link')) {
                            return;
                        }
                        const taskId = card.dataset.taskId;
                        const currentStatus = card.dataset.currentStatus;
                        let nextStatus = '';
                        if (currentStatus === 'Not Started') {
                            nextStatus = 'In Progress';
                        } else if (currentStatus === 'In Progress') {
                            nextStatus = 'Completed';
                        } else {
                            return; 
                        }
                        
                        fetch('ajax_update_task_status.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
                            body: `task_id=${taskId}&new_status=${nextStatus}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const statusTextElement = card.querySelector('.meta-info .task-status-text');
                                if (statusTextElement && data.new_status_text) {
                                    statusTextElement.textContent = data.new_status_text;
                                }
                                card.classList.remove('status-not-started', 'status-in-progress');
                                if(data.new_status_text === 'Dikerjakan') card.classList.add('status-in-progress');
                                else if(data.new_status_text === 'Selesai') card.classList.add('status-completed-history');
                                card.dataset.currentStatus = nextStatus;

                                if (data.is_completed) {
                                    card.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out, max-height 0.5s ease-in-out, padding 0.5s ease-in-out, margin 0.5s ease-in-out';
                                    card.style.opacity = '0'; card.style.transform = 'scale(0.9)';
                                    card.style.maxHeight = '0px'; card.style.paddingTop = '0px';
                                    card.style.paddingBottom = '0px'; card.style.marginBottom = '0px';
                                    setTimeout(() => {
                                        card.remove();
                                        if (taskListContainerManajemen.children.length === 0) {
                                            if (!taskListContainerManajemen.querySelector('.no-tasks-message')) {
                                                taskListContainerManajemen.innerHTML = '<p class="no-tasks-message">Tidak ada tugas aktif yang sesuai.</p>';
                                            }
                                        }
                                    }, 500); 
                                }
                            } else { 
                                alert('Gagal memperbarui status: ' + (data.message || 'Error tidak diketahui.'));
                            }
                        })
                        .catch(error => { 
                            console.error('Error AJAX:', error);
                            alert('Terjadi kesalahan koneksi saat memperbarui status.');
                        });
                    });
                }
            }

        });
    </script>
<?php else: ?>
<?php endif; ?>


</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
// Hapus state tambah tugas dari session server jika halaman direfresh dan bukan POST
if (isset($_SESSION['chatbot_add_task_step']) && $_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['chatbot_add_task_step']);
    unset($_SESSION['chatbot_pending_task_data']);
}
// Hapus juga state konfirmasi umum jika halaman direfresh
if (isset($_SESSION['action_confirmation_pending']) && $_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['action_confirmation_pending']);
    unset($_SESSION['pending_action_details']);
}
?>