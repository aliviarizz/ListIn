<?php
// $current_page dari header.php
?>
<?php if (!in_array($current_page, ['login.php', 'register.php'])): ?>
    </div> <!-- Penutup .content -->

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
                // Urutkan berdasarkan waktu, terbaru di atas
                usort($current_session_notifications_for_popup, function($a, $b) {
                    return ($b['time'] ?? 0) - ($a['time'] ?? 0);
                });
            }
            $has_current_notifications_for_popup = !empty($current_session_notifications_for_popup);
            ?>
            <?php if ($has_current_notifications_for_popup): ?>
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
                        else if($notif_item['type'] == 'info') $message_class_popup = 'notif-info'; // Tambahkan jika perlu
                    }
                ?>
                    <li class="<?php echo $message_class_popup; ?>">
                        <?php echo htmlspecialchars($notif_item['message']); ?> 
                        <small class="notif-time"><?php echo $time_ago_popup; ?></small>
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
            const bellIcon = document.getElementById('bellIcon');
            const notificationPopup = document.getElementById('notification-popup');
            const notificationListUl = document.getElementById('notification-list');
            const clearAllNotificationsBtn = document.getElementById('clearAllNotificationsBtn');

            const calendarIcon = document.getElementById('calendarIcon');
            const calendarPopup = document.getElementById('calendar-popup');

            function togglePopup(popupElement, iconElement, otherPopupElement) {
                if (otherPopupElement && otherPopupElement.classList.contains('show')) {
                    otherPopupElement.classList.remove('show');
                }
                popupElement.classList.toggle('show');
                
                // Jika popup notifikasi dibuka dan ada notifikasi belum dibaca (badge aktif)
                if (popupElement === notificationPopup && popupElement.classList.contains('show')) {
                    if (bellIcon && bellIcon.classList.contains('has-notif')) {
                        fetch('mark_notifications_viewed.php') // Skrip untuk menandai badge sudah dilihat
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
                    e.stopPropagation(); // Mencegah popup tertutup jika tombol di dalamnya
                    if (confirm('Anda yakin ingin menghapus semua notifikasi?')) {
                        fetch('ajax_clear_all_notifications.php') // Skrip untuk menghapus semua notifikasi dari session
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    notificationListUl.innerHTML = '<li class="no-notifications">Tidak ada notifikasi baru.</li>';
                                    if (bellIcon && bellIcon.classList.contains('has-notif')) {
                                        bellIcon.classList.remove('has-notif'); // Pastikan badge juga hilang
                                    }
                                    // Anda mungkin ingin menyembunyikan tombol "Hapus Semua" jika daftar kosong
                                    // clearAllNotificationsBtn.style.display = 'none'; 
                                } else {
                                    alert('Gagal menghapus notifikasi.');
                                }
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

            document.addEventListener('click', function (e) { 
                if (notificationPopup && notificationPopup.classList.contains('show') && !notificationPopup.contains(e.target) && e.target !== bellIcon) { 
                    notificationPopup.classList.remove('show'); 
                }
                if (calendarPopup && calendarPopup.classList.contains('show') && !calendarPopup.contains(e.target) && e.target !== calendarIcon) { 
                    calendarPopup.classList.remove('show'); 
                }
            });

            // Inisialisasi Flatpickr untuk input tanggal di form
            const dateInputs = document.querySelectorAll('input[type="text"][id$="Date"], input[type="text"][id$="DueDate"], input[type="text"][id$="DateRange"]');
            dateInputs.forEach(input => {
                let config = { dateFormat: "d/m/Y", locale: "id", allowInput: true };
                if (input.id === 'taskDueDate' || input.id === 'editTaskDueDate') {
                     config.minDate = "today";
                }
                if (input.id === 'filterHistoryDateRange' || input.id === 'filterPerformanceDateRange') {
                    config.mode = "range";
                } else if (input.id === 'filterDate') {
                    config.mode = "single";
                }
                flatpickr(input, config);
            });

            // Konfirmasi sebelum hapus
            const deleteButtons = document.querySelectorAll('a.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    if (!confirm('Anda yakin ingin menghapus item ini?')) {
                        event.preventDefault();
                    }
                });
            });

            // Handle klik tombol preset rentang waktu
            const presetButtons = document.querySelectorAll('.filter-preset-buttons .btn[data-range]');
            presetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const rangeType = this.dataset.range;
                    const dateRangeInput = document.getElementById('filterPerformanceDateRange') || document.getElementById('filterHistoryDateRange'); // Sesuaikan ID jika perlu
                    if (!dateRangeInput) return;

                    const fpInstance = dateRangeInput._flatpickr;
                    if (!fpInstance) return;

                    let startDate, endDate = new Date(); // endDate selalu hari ini untuk preset
                    
                    switch(rangeType) {
                        case 'today':
                            startDate = new Date();
                            break;
                        case 'yesterday':
                            startDate = new Date();
                            startDate.setDate(startDate.getDate() - 1);
                            endDate = new Date(startDate); // yesterday to yesterday
                            break;
                        case 'last7days':
                            startDate = new Date();
                            startDate.setDate(startDate.getDate() - 6);
                            break;
                        case 'last30days':
                            startDate = new Date();
                            startDate.setDate(startDate.getDate() - 29);
                            break;
                        case 'thismonth':
                            startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1);
                            break;
                        case 'lastmonth':
                            endDate = new Date(endDate.getFullYear(), endDate.getMonth(), 0); // Hari terakhir bulan lalu
                            startDate = new Date(endDate.getFullYear(), endDate.getMonth(), 1); // Hari pertama bulan lalu
                            break;
                        default:
                            return;
                    }
                    fpInstance.setDate([startDate, endDate], true); // true untuk trigger change event
                });
            });

            // JS untuk preview gambar profil di edit_profil.php
            const editProfileImageInput = document.getElementById('editProfileImageFile');
            const currentImagePreview = document.getElementById('currentImagePreview');
            if (editProfileImageInput && currentImagePreview) {
                editProfileImageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            currentImagePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>