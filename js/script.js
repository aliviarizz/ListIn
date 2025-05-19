// js/script.js
document.addEventListener('DOMContentLoaded', () => {
    const currentUser = JSON.parse(localStorage.getItem('currentUser'));
    if (!currentUser && !window.location.pathname.includes('halaman-masuk.html') && !window.location.pathname.includes('halaman-daftar.html')) {
        window.location.href = 'halaman-masuk.html';
        return;
    }

    let allUsersTasks = JSON.parse(localStorage.getItem('allUsersTasks')) || {};
    let tasks = [];
    if (currentUser) {
        tasks = allUsersTasks[currentUser.id] || [
            { id: Date.now() + 1, title: "Rapat Proyek Mingguan", description: "Diskusi progres dan rencana sprint berikutnya.", priority: "High", status: "Not Started", date: formatDate(new Date(Date.now() + 2 * 24 * 60 * 60 * 1000)) },
            { id: Date.now() + 2, title: "Desain Mockup Aplikasi", description: "Selesaikan desain untuk halaman utama dan profil.", priority: "Medium", status: "In Progress", date: formatDate(new Date(Date.now() + 4 * 24 * 60 * 60 * 1000)) },
            { id: Date.now() + 3, title: "Laporan Keuangan Bulanan", description: "Kumpulkan semua data transaksi dan buat laporan.", priority: "High", status: "Not Started", date: formatDate(new Date(Date.now() + 1 * 24 * 60 * 60 * 1000)) },
            { id: Date.now() + 4, title: "Olah Raga Pagi", description: "Lari pagi di taman selama 30 menit.", priority: "Low", status: "Completed", date: formatDate(new Date(Date.now() - 1 * 24 * 60 * 60 * 1000)) },
        ];
        if (!allUsersTasks[currentUser.id] && tasks.length > 0) {
            allUsersTasks[currentUser.id] = tasks;
            localStorage.setItem('allUsersTasks', JSON.stringify(allUsersTasks));
        }
    }

    function saveTasks() {
        if (currentUser) {
            allUsersTasks[currentUser.id] = tasks;
            localStorage.setItem('allUsersTasks', JSON.stringify(allUsersTasks));
            checkTasksNearDeadline();
        }
    }

    function formatDate(dateObj) {
        if (!dateObj) return '';
        const day = String(dateObj.getDate()).padStart(2, '0');
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const year = dateObj.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function parseDateString(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }
        return null;
    }

    // --- HEADER FUNCTIONALITY ---
    const headerProfilePic = document.getElementById('headerProfilePic');
    if (headerProfilePic && currentUser) {
        headerProfilePic.src = currentUser.profileImage || 'images/placeholder-profile.png';
        headerProfilePic.addEventListener('click', () => {
            window.location.href = 'halaman-profil.html';
        });
    }

    const searchInputGlobal = document.getElementById('searchInputGlobal');
    if (searchInputGlobal) {
        searchInputGlobal.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase().trim();
            filterTasksOnPage(searchTerm);
        });
    }

    function filterTasksOnPage(searchTerm) {
        const taskItems = document.querySelectorAll('.task-item-card');
        let visibleCount = 0;
        taskItems.forEach(item => {
            const titleEl = item.querySelector('.task-details strong');
            const descEl = item.querySelector('.task-details .description');
            const title = titleEl ? titleEl.textContent.toLowerCase() : '';
            const description = descEl ? descEl.textContent.toLowerCase() : '';

            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
         // Tampilkan pesan jika tidak ada hasil pencarian
        const listContainer = taskItems.length > 0 ? taskItems[0].parentElement : null;
        const noResultsMessageId = listContainer ? `no-results-${listContainer.id}` : null;
        let noResultsEl = noResultsMessageId ? document.getElementById(noResultsMessageId) : null;

        if (listContainer && !noResultsEl) {
            noResultsEl = document.createElement('p');
            noResultsEl.id = noResultsMessageId;
            noResultsEl.classList.add('no-tasks-message'); // Gunakan style yang sama
            listContainer.appendChild(noResultsEl);
        }
        
        if (noResultsEl) {
            if (visibleCount === 0 && searchTerm !== "") {
                noResultsEl.textContent = `Tidak ada tugas yang cocok dengan pencarian "${searchTerm}".`;
                noResultsEl.style.display = 'block';
            } else {
                noResultsEl.style.display = 'none';
            }
        }
    }

    const bellIcon = document.getElementById('bellIcon');
    const notificationPopup = document.getElementById('notification-popup');
    const notificationList = document.getElementById('notification-list');

    function checkTasksNearDeadline() {
        if (!notificationList || !bellIcon) return;
        const today = new Date(); today.setHours(0,0,0,0);
        const upcomingTasks = tasks.filter(task => {
            if (task.status === "Completed") return false;
            const taskDate = parseDateString(task.date);
            if (!taskDate) return false;
            taskDate.setHours(0,0,0,0);
            const diffTime = taskDate.getTime() - today.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays >= 0 && diffDays <= 3;
        }).sort((a,b) => (parseDateString(a.date) || 0) - (parseDateString(b.date) || 0) );

        notificationList.innerHTML = "";
        if (upcomingTasks.length > 0) {
            bellIcon.classList.add("has-notif");
            upcomingTasks.forEach(task => {
                const li = document.createElement("li");
                const taskDate = parseDateString(task.date);
                const diffTime = taskDate.getTime() - today.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                let deadlineText = `Deadline: ${task.date}`;
                if (diffDays === 0) deadlineText = `Deadline: Hari Ini!`;
                else if (diffDays === 1) deadlineText = `Deadline: Besok!`;
                else deadlineText = `Deadline: ${diffDays} hari lagi (${task.date})`;
                li.textContent = `${task.title} - ${deadlineText}`;
                notificationList.appendChild(li);
            });
        } else {
            bellIcon.classList.remove("has-notif");
            notificationList.innerHTML = "<li>Tidak ada tugas mendekati deadline.</li>";
        }
    }

    if (bellIcon && notificationPopup) {
        bellIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            if (calendarPopup && calendarPopup.classList.contains('show')) calendarPopup.classList.remove('show');
            notificationPopup.classList.toggle("show");
            if (notificationPopup.classList.contains("show")) checkTasksNearDeadline();
        });
    }

    const calendarIcon = document.getElementById('calendarIcon');
    const calendarPopup = document.getElementById('calendar-popup');
    const calendarContainerPopup = document.getElementById('calendar-container-popup');

    if (calendarIcon && calendarPopup && calendarContainerPopup) {
        flatpickr(calendarContainerPopup, { inline: true, dateFormat: "d/m/Y", locale: "id" });
        calendarIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            if (notificationPopup && notificationPopup.classList.contains('show')) notificationPopup.classList.remove('show');
            calendarPopup.classList.toggle('show');
        });
    }

    document.addEventListener('click', function (e) {
        if (notificationPopup && notificationPopup.classList.contains('show') && !notificationPopup.contains(e.target) && e.target !== bellIcon) {
            notificationPopup.classList.remove('show');
        }
        if (calendarPopup && calendarPopup.style.display === 'block' && !calendarPopup.contains(e.target) && e.target !== calendarIcon) {
            calendarPopup.style.display = 'none'; // Ini mungkin sudah benar atau pakai class .show
        }
         if (calendarPopup && calendarPopup.classList.contains('show') && !calendarPopup.contains(e.target) && e.target !== calendarIcon) {
            calendarPopup.classList.remove('show');
        }
    });

    function updateDateDisplay() {
        const dateContainer = document.querySelector('.date-container');
        if (!dateContainer) return;
        const now = new Date();
        const options = { weekday: 'long', day: 'numeric', month: 'long' }; // Hapus tahun agar lebih pendek
        const formattedFullDate = now.toLocaleDateString('id-ID', options);
        const [dayName, ...dateParts] = formattedFullDate.split(', ');
        const dateStr = dateParts.join(', ');
        dateContainer.innerHTML = `<p>${dayName}</p><span>${dateStr}</span>`;
    }

    // --- GLOBAL TASK MANIPULATION FUNCTIONS ---
    window.updateTaskStatus = (taskId, newStatus, listIdToUpdate) => {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
            const oldStatus = task.status;
            task.status = newStatus;
            
            // Logika khusus jika tugas selesai dari halaman manajemen
            if (oldStatus !== "Completed" && newStatus === "Completed" && window.location.pathname.includes('halaman-manajemen-tugas.html')) {
                // Hapus dari array tasks aktif, save, lalu render ulang manajemen
                tasks = tasks.filter(t => t.id !== taskId); 
                // Perlu pastikan task yg selesai ini masih ada di allUsersTasks[currentUser.id] tapi dengan status completed
                // Jadi, kita update dulu statusnya di array global (allUsersTasks) sebelum filter 'tasks' lokal
                let globalTask = (allUsersTasks[currentUser.id] || []).find(t => t.id === taskId);
                if(globalTask) globalTask.status = "Completed";
                
                saveTasks(); // Simpan allUsersTasks yang sudah diupdate
                renderManagementTasks(); // Ini akan mengambil 'tasks' yg sudah difilter (tanpa yg baru selesai)
                alert(`Tugas "${task.title}" telah ditandai selesai dan dipindahkan ke Riwayat.`);
            } else {
                saveTasks(); // Simpan perubahan status biasa
                 // Re-render halaman yang sesuai
                if (window.location.pathname.includes('halaman-dashboard.html')) renderDashboardTasks();
                else if (window.location.pathname.includes('halaman-manajemen-tugas.html')) renderManagementTasks();
                else if (window.location.pathname.includes('halaman-riwayat.html')) {
                    if (oldStatus === "Completed" && newStatus !== "Completed") renderHistoryTasks();
                }
            }
            updateTaskStatusProgress();
            checkTasksNearDeadline();
        }
    };

    window.deleteTask = (taskId) => {
        if (confirm('Apakah Anda yakin ingin menghapus tugas ini secara permanen?')) {
            tasks = tasks.filter(task => task.id !== taskId);
            saveTasks();
            if (window.location.pathname.includes('halaman-dashboard.html')) renderDashboardTasks();
            else if (window.location.pathname.includes('halaman-manajemen-tugas.html')) renderManagementTasks();
            else if (window.location.pathname.includes('halaman-riwayat.html')) renderHistoryTasks();
            updateTaskStatusProgress();
        }
    };

    window.reopenTask = (taskId) => {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
            task.status = "Not Started";
            saveTasks();
            if (window.location.pathname.includes('halaman-dashboard.html')) renderDashboardTasks();
            else if (window.location.pathname.includes('halaman-riwayat.html')) renderHistoryTasks();
            alert(`Tugas "${task.title}" telah dibuka kembali.`);
            updateTaskStatusProgress();
        }
    };

    // --- TASK CARD CREATION ---
    function createTaskCard(task, pageType) {
        const taskCard = document.createElement('div');
        taskCard.classList.add('task-item-card');
        if (task.status === "Completed") taskCard.classList.add('completed');

        let actionsHtml = '';
        if (pageType === 'dashboardTodo') {
            actionsHtml = `
                <select onchange="window.updateTaskStatus(${task.id}, this.value)" title="Ubah Status Tugas">
                    <option value="Not Started" ${task.status === "Not Started" ? "selected" : ""}>Belum Mulai</option>
                    <option value="In Progress" ${task.status === "In Progress" ? "selected" : ""}>Dikerjakan</option>
                    <option value="Completed" ${task.status === "Completed" ? "selected" : ""}>Selesai</option>
                </select>
                <a href="halaman-edit-tugas.html?id=${task.id}" class="edit-btn" title="Edit Tugas"><i class="fas fa-edit"></i></a>`;
        } else if (pageType === 'management') {
             actionsHtml = `
                <select onchange="window.updateTaskStatus(${task.id}, this.value)" title="Ubah Status Tugas">
                    <option value="Not Started" ${task.status === "Not Started" ? "selected" : ""}>Belum Mulai</option>
                    <option value="In Progress" ${task.status === "In Progress" ? "selected" : ""}>Dikerjakan</option>
                    <option value="Completed" ${task.status === "Completed" ? "selected" : ""}>Selesai</option>
                </select>
                <a href="halaman-edit-tugas.html?id=${task.id}" class="edit-btn" title="Edit Tugas"><i class="fas fa-edit"></i></a>
                <button onclick="window.deleteTask(${task.id})" class="delete-btn" title="Hapus Tugas"><i class="fas fa-trash"></i></button>`;
        } else if (pageType === 'dashboardCompleted' || pageType === 'history') {
             actionsHtml = `
                <button onclick="window.reopenTask(${task.id})" class="reopen-btn" title="Buka Kembali Tugas"><i class="fas fa-undo"></i></button>
                <button onclick="window.deleteTask(${task.id})" class="delete-btn" title="Hapus Permanen"><i class="fas fa-trash"></i></button>`;
        }

        taskCard.innerHTML = `
            <div class="task-details">
                <strong>${task.title}</strong>
                <p class="description">${task.description || 'Tidak ada deskripsi.'}</p>
                <p class="meta-info">
                    Prioritas: <span class="priority-${task.priority}">${task.priority}</span> | Status: ${task.status} | Deadline: ${task.date}
                </p>
            </div>
            <div class="task-actions">${actionsHtml}</div>`;
        return taskCard;
    }

    // --- DASHBOARD ---
    const todoListEl = document.getElementById('todo-list');
    const completedListEl = document.getElementById('completed-list');
    const todoCountEl = document.getElementById('todo-count');
    const completedCountEl = document.getElementById('completed-count');

    function renderDashboardTasks() {
        if (!todoListEl || !completedListEl) return;
        todoListEl.innerHTML = ''; completedListEl.innerHTML = '';
        let todoCounter = 0, completedCounter = 0;
        const sortedTasks = [...tasks].sort((a,b) => (parseDateString(a.date) || 0) - (parseDateString(b.date) || 0));

        sortedTasks.forEach(task => {
            if (task.status === "Completed") {
                completedListEl.appendChild(createTaskCard(task, 'dashboardCompleted'));
                completedCounter++;
            } else {
                todoListEl.appendChild(createTaskCard(task, 'dashboardTodo'));
                todoCounter++;
            }
        });
        if(todoCountEl) todoCountEl.textContent = todoCounter;
        if(completedCountEl) completedCountEl.textContent = completedCounter;
        if (todoCounter === 0 && todoListEl) todoListEl.innerHTML = '<p class="no-tasks-message">Tidak ada tugas aktif.</p>';
        if (completedCounter === 0 && completedListEl) completedListEl.innerHTML = '<p class="no-tasks-message">Belum ada tugas yang selesai.</p>';
        updateTaskStatusProgress();
    }

    function updateTaskStatusProgress() {
        const completedProgressEl = document.getElementById('completed-progress');
        if (!completedProgressEl) return;
        const totalTasks = tasks.length > 0 ? tasks.length : 1; // Hindari pembagian dengan nol
        const completed = tasks.filter(task => task.status === "Completed").length;
        const inProgress = tasks.filter(task => task.status === "In Progress").length;
        const notStarted = tasks.filter(task => task.status === "Not Started").length;

        const completedPercent = Math.round((completed / totalTasks) * 100);
        const inProgressPercent = Math.round((inProgress / totalTasks) * 100);
        const notStartedPercent = Math.round((notStarted / totalTasks) * 100);

        completedProgressEl.style.setProperty('--progress', `${completedPercent}%`);
        completedProgressEl.setAttribute('data-progress', completedPercent);
        const inProgressEl = document.getElementById('in-progress-progress');
        if(inProgressEl) {
            inProgressEl.style.setProperty('--progress', `${inProgressPercent}%`);
            inProgressEl.setAttribute('data-progress', inProgressPercent);
        }
        const notStartedEl = document.getElementById('not-started-progress');
        if(notStartedEl) {
            notStartedEl.style.setProperty('--progress', `${notStartedPercent}%`);
            notStartedEl.setAttribute('data-progress', notStartedPercent);
        }
    }

    // --- TAMBAH TUGAS ---
    const addTaskForm = document.getElementById('addTaskForm');
    if (addTaskForm) {
        flatpickr("#taskDueDate", { dateFormat: "d/m/Y", minDate: "today", locale: "id" });
        addTaskForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const title = document.getElementById('taskTitle').value.trim();
            const description = document.getElementById('taskDescription').value.trim();
            const priority = document.getElementById('taskPriority').value;
            const dueDate = document.getElementById('taskDueDate').value;
            if (!title || !dueDate) { alert("Judul dan Tanggal Deadline wajib diisi!"); return; }
            const newTask = { id: Date.now(), title, description, priority, status: "Not Started", date: dueDate };
            tasks.push(newTask);
            saveTasks();
            alert('Tugas berhasil ditambahkan!');
            window.location.href = 'halaman-manajemen-tugas.html';
        });
    }

    // --- MANAJEMEN TUGAS ---
    const managementTaskListEl = document.getElementById('managementTaskList');
    const filterStatusEl = document.getElementById('filterStatus');
    const filterPriorityEl = document.getElementById('filterPriority');
    const filterDateEl = document.getElementById('filterDate');
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    let fpFilterDate;
    if (filterDateEl) fpFilterDate = flatpickr(filterDateEl, { dateFormat: "d/m/Y", locale: "id", mode: "single" });

    function renderManagementTasks(filteredTasksOverride = null) {
        if (!managementTaskListEl) return;
        managementTaskListEl.innerHTML = '';
        
        // Ambil semua task DARI SUMBER ASLI (allUsersTasks) yang belum selesai
        let activeTasksFromSource = (allUsersTasks[currentUser.id] || []).filter(task => task.status !== "Completed");

        let tasksToDisplay = activeTasksFromSource; // Defaultnya tampilkan semua yang aktif

        if (filteredTasksOverride) { // Jika ada filter dari tombol "Terapkan"
            tasksToDisplay = filteredTasksOverride.filter(task => task.status !== "Completed");
        }

        const sortedTasks = [...tasksToDisplay].sort((a, b) => (parseDateString(a.date) || 0) - (parseDateString(b.date) || 0));

        if (sortedTasks.length === 0) {
            managementTaskListEl.innerHTML = '<p class="no-tasks-message">Tidak ada tugas aktif yang sesuai.</p>';
            return;
        }
        sortedTasks.forEach(task => {
             managementTaskListEl.appendChild(createTaskCard(task, 'management'));
        });
    }

    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', () => {
            const statusFilter = filterStatusEl.value;
            const priorityFilter = filterPriorityEl.value;
            const dateFilter = filterDateEl.value;
            
            // Filter dari SUMBER ASLI (allUsersTasks)
            let tempFilteredTasks = (allUsersTasks[currentUser.id] || []).filter(task => {
                let matchStatus = true;
                let matchPriority = true;
                let matchDate = true;
                if (statusFilter) matchStatus = task.status === statusFilter;
                if (priorityFilter) matchPriority = task.priority === priorityFilter;
                if (dateFilter) matchDate = task.date === dateFilter;
                return matchStatus && matchPriority && matchDate; // Biarkan status "Completed" lolos filter ini
            });
            renderManagementTasks(tempFilteredTasks); // renderManagementTasks akan memfilter yg "Completed"
        });
    }
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', () => {
            if(filterStatusEl) filterStatusEl.value = "";
            if(filterPriorityEl) filterPriorityEl.value = "";
            if(fpFilterDate) fpFilterDate.clear();
            renderManagementTasks();
        });
    }
    if (window.location.pathname.includes('halaman-manajemen-tugas.html')) {
        if (filterStatusEl) { // Hapus opsi "Selesai" dari filter
            for (let i = 0; i < filterStatusEl.options.length; i++) {
                if (filterStatusEl.options[i].value === 'Completed') { filterStatusEl.remove(i); break; }
            }
        }
        renderManagementTasks();
    }

    // --- EDIT TUGAS ---
    const editTaskForm = document.getElementById('editTaskForm');
    if (editTaskForm) {
        const urlParams = new URLSearchParams(window.location.search);
        const taskId = parseInt(urlParams.get('id'));
        const taskToEdit = tasks.find(t => t.id === taskId); // Cari di 'tasks' yang aktif
        if (!taskToEdit && allUsersTasks[currentUser.id]) { // Jika tidak ada di task aktif, coba cari di semua task (termasuk yg completed)
             taskToEdit = allUsersTasks[currentUser.id].find(t => t.id === taskId);
        }

        if (taskToEdit) {
            document.getElementById('editTaskId').value = taskToEdit.id;
            document.getElementById('editTaskTitle').value = taskToEdit.title;
            document.getElementById('editTaskDescription').value = taskToEdit.description;
            document.getElementById('editTaskPriority').value = taskToEdit.priority;
            document.getElementById('editTaskStatus').value = taskToEdit.status;
            flatpickr("#editTaskDueDate", { dateFormat: "d/m/Y", defaultDate: parseDateString(taskToEdit.date), minDate: "today", locale: "id"});

            editTaskForm.addEventListener('submit', (e) => {
                e.preventDefault();
                taskToEdit.title = document.getElementById('editTaskTitle').value.trim();
                taskToEdit.description = document.getElementById('editTaskDescription').value.trim();
                taskToEdit.priority = document.getElementById('editTaskPriority').value;
                taskToEdit.status = document.getElementById('editTaskStatus').value; // Status bisa diubah di sini
                taskToEdit.date = document.getElementById('editTaskDueDate').value;
                if (!taskToEdit.title || !taskToEdit.date) { alert("Judul dan Tanggal Deadline wajib diisi!"); return;}
                
                // Jika status diubah jadi Completed dari halaman edit
                if (taskToEdit.status === "Completed" && !tasks.find(t => t.id === taskId && t.status === "Completed")) {
                    // Hapus dari array 'tasks' aktif jika perlu
                    tasks = tasks.filter(t => t.id !== taskId);
                } else if (taskToEdit.status !== "Completed" && !tasks.find(t => t.id === taskId)) {
                    // Jika di-reopen dari edit (misal task ada di localstorage tapi tidak di 'tasks' karena sudah completed)
                    tasks.push(taskToEdit); // Tambahkan kembali ke 'tasks' aktif
                }

                saveTasks(); // Ini akan menyimpan ke allUsersTasks
                alert('Tugas berhasil diperbarui!');
                window.location.href = 'halaman-manajemen-tugas.html';
            });
        } else {
            alert('Tugas tidak ditemukan!'); window.location.href = 'halaman-manajemen-tugas.html';
        }
    }

    // --- RIWAYAT TUGAS ---
    const historyTaskListEl = document.getElementById('historyTaskList');
    const filterHistoryDateRangeEl = document.getElementById('filterHistoryDateRange');
    const applyHistoryFilterBtn = document.getElementById('applyHistoryFilterBtn');
    const resetHistoryFilterBtn = document.getElementById('resetHistoryFilterBtn');
    let fpHistoryDateRange;
    if (filterHistoryDateRangeEl) fpHistoryDateRange = flatpickr(filterHistoryDateRangeEl, { mode: "range", dateFormat: "d/m/Y", locale: "id" });

    function renderHistoryTasks(filteredHistoryTasks = null) {
        if (!historyTaskListEl) return;
        historyTaskListEl.innerHTML = '';
        
        // Selalu ambil dari SUMBER ASLI (allUsersTasks) untuk riwayat
        let completedTasksFromSource = (allUsersTasks[currentUser.id] || []).filter(task => task.status === "Completed");
        
        let tasksToDisplay = filteredHistoryTasks ? filteredHistoryTasks : completedTasksFromSource;

        const sortedTasks = [...tasksToDisplay].sort((a,b) => (parseDateString(b.date) || 0) - (parseDateString(a.date) || 0));

        if (sortedTasks.length === 0) {
            historyTaskListEl.innerHTML = '<p class="no-tasks-message">Tidak ada riwayat tugas yang selesai.</p>';
            return;
        }
        sortedTasks.forEach(task => {
            historyTaskListEl.appendChild(createTaskCard(task, 'history'));
        });
    }

    if(applyHistoryFilterBtn && fpHistoryDateRange) {
        applyHistoryFilterBtn.addEventListener('click', () => {
            const selectedDates = fpHistoryDateRange.selectedDates;
            let tempFiltered = (allUsersTasks[currentUser.id] || []).filter(task => task.status === "Completed"); // Mulai dengan semua yg completed

            if (selectedDates.length === 2) {
                const startDate = selectedDates[0]; startDate.setHours(0,0,0,0);
                const endDate = selectedDates[1]; endDate.setHours(23,59,59,999);
                tempFiltered = tempFiltered.filter(task => {
                    const taskDate = parseDateString(task.date);
                    return taskDate >= startDate && taskDate <= endDate;
                });
            } else if (selectedDates.length === 1) {
                const singleDate = selectedDates[0];
                tempFiltered = tempFiltered.filter(task => task.date === formatDate(singleDate));
            }
            renderHistoryTasks(tempFiltered);
        });
    }
    if(resetHistoryFilterBtn && fpHistoryDateRange){
        resetHistoryFilterBtn.addEventListener('click', () => {
            fpHistoryDateRange.clear();
            renderHistoryTasks(); // Tampilkan semua dari sumber
        });
    }

    // --- PROFIL & EDIT PROFIL & UBAH PASSWORD (Logic relatif sama) ---
    if (currentUser) {
        const profileNameEl = document.getElementById('profileName');
        if (profileNameEl) profileNameEl.textContent = currentUser.username;
        const profileEmailEl = document.getElementById('profileEmail');
        if (profileEmailEl) profileEmailEl.textContent = currentUser.email;
        const profileImageMainEl = document.getElementById('profileImageMain');
        if (profileImageMainEl) profileImageMainEl.src = currentUser.profileImage || 'images/placeholder-profile.png';

        const editProfileForm = document.getElementById('editProfileForm');
        if (editProfileForm) {
            document.getElementById('editProfileUsername').value = currentUser.username;
            document.getElementById('editProfileEmail').value = currentUser.email;
            const currentImagePreview = document.getElementById('currentImagePreview');
            if(currentImagePreview) currentImagePreview.src = currentUser.profileImage || 'images/placeholder-profile.png';
            const editProfileImageFile = document.getElementById('editProfileImageFile');
            if(editProfileImageFile && currentImagePreview) {
                editProfileImageFile.onchange = evt => {
                    const [file] = editProfileImageFile.files;
                    if (file) currentImagePreview.src = URL.createObjectURL(file);
                }
            }
            editProfileForm.addEventListener('submit', (e) => {
                e.preventDefault();
                currentUser.username = document.getElementById('editProfileUsername').value.trim();
                const newEmail = document.getElementById('editProfileEmail').value.trim();
                let allUsersForEmailCheck = JSON.parse(localStorage.getItem('users')) || [];
                if (newEmail !== currentUser.email && allUsersForEmailCheck.some(u => u.email === newEmail && u.id !== currentUser.id)) {
                    alert('Email sudah digunakan oleh pengguna lain.'); return;
                }
                currentUser.email = newEmail;
                const imageFile = editProfileImageFile.files[0];
                const saveProfile = () => {
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    let allUsers = JSON.parse(localStorage.getItem('users')) || [];
                    const userIndex = allUsers.findIndex(u => u.id === currentUser.id);
                    if (userIndex > -1) allUsers[userIndex] = {...allUsers[userIndex], ...currentUser};
                    localStorage.setItem('users', JSON.stringify(allUsers));
                    alert('Profil berhasil diperbarui!');
                    window.location.href = 'halaman-profil.html';
                };
                if (imageFile) {
                    const reader = new FileReader();
                    reader.onloadend = function() { currentUser.profileImage = reader.result; saveProfile(); }
                    reader.readAsDataURL(imageFile);
                } else saveProfile();
            });
        }
        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const currentPassword = document.getElementById('currentPassword').value;
                const newPassword = document.getElementById('newPassword').value;
                const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                if (currentPassword !== currentUser.password) { alert('Password saat ini salah!'); return; }
                if (newPassword !== confirmNewPassword) { alert('Password baru dan konfirmasi tidak cocok!'); return; }
                if (newPassword.length < 6) { alert('Password baru minimal 6 karakter!'); return; }
                currentUser.password = newPassword;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
                let allUsers = JSON.parse(localStorage.getItem('users')) || [];
                const userIndex = allUsers.findIndex(u => u.id === currentUser.id);
                if (userIndex > -1) allUsers[userIndex].password = newPassword;
                localStorage.setItem('users', JSON.stringify(allUsers));
                alert('Password berhasil diubah!');
                changePasswordForm.reset();
                window.location.href = 'halaman-profil.html';
            });
        }
    }

    // --- INITIAL RENDERS & UPDATES ---
    const currentPagePath = window.location.pathname.split("/").pop();
    if (currentPagePath === 'halaman-dashboard.html' || currentPagePath === '') renderDashboardTasks();
    else if (currentPagePath === 'halaman-manajemen-tugas.html') renderManagementTasks();
    else if (currentPagePath === 'halaman-riwayat.html') renderHistoryTasks();

    checkTasksNearDeadline();
    updateDateDisplay();
    updateTaskStatusProgress(); // Panggil juga di awal untuk dashboard

    const sidebarLinks = document.querySelectorAll('.sidebar .menu a');
    sidebarLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentPagePath || (currentPagePath === "" && link.getAttribute('href') === "halaman-dashboard.html")) {
            link.classList.add('active');
        }
    });
});