// js/auth.js
document.addEventListener('DOMContentLoaded', () => {
    const users = JSON.parse(localStorage.getItem('users')) || [];
    const currentUser = JSON.parse(localStorage.getItem('currentUser'));
    const currentPage = window.location.pathname.split('/').pop();
    const protectedPages = [
        'halaman-dashboard.html', 'halaman-tambah-tugas.html', 'halaman-manajemen-tugas.html',
        'halaman-edit-tugas.html', 'halaman-riwayat.html', 'halaman-profil.html',
        'halaman-edit-profil.html', 'halaman-ubah-password.html'
    ];

    if (!currentUser && protectedPages.includes(currentPage)) {
        window.location.href = 'halaman-masuk.html';
        return;
    }
    if (currentUser && (currentPage === 'halaman-masuk.html' || currentPage === 'halaman-daftar.html')) {
        window.location.href = 'halaman-dashboard.html';
        return;
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (!username || !email || !password || !confirmPassword) {
                alert('Semua kolom wajib diisi!'); return;
            }
            if (password !== confirmPassword) {
                alert('Password dan konfirmasi password tidak cocok!'); return;
            }
            if (password.length < 6) {
                 alert('Password minimal 6 karakter!'); return;
            }
            if (users.find(user => user.email === email)) {
                alert('Email sudah terdaftar! Gunakan email lain.'); return;
            }
            const newUser = { id: Date.now(), username, email, password, profileImage: 'images/placeholder-profile.png' };
            users.push(newUser);
            localStorage.setItem('users', JSON.stringify(users));
            alert('Registrasi berhasil! Silakan masuk dengan akun Anda.');
            window.location.href = 'halaman-masuk.html';
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            if (!email || !password) {
                alert('Email dan password wajib diisi!'); return;
            }
            const user = users.find(u => u.email === email && u.password === password);
            if (user) {
                localStorage.setItem('currentUser', JSON.stringify(user));
                localStorage.removeItem("dashboardWelcomed"); // Agar welcome message muncul lagi jika ada
                window.location.href = 'halaman-dashboard.html';
            } else {
                alert('Email atau password salah. Silakan coba lagi.');
            }
        });
    }

    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) {
        logoutButton.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Apakah Anda yakin ingin keluar?')) {
                localStorage.removeItem('currentUser');
                // localStorage.removeItem('allUsersTasks'); // Opsional: hapus semua data tugas saat logout
                window.location.href = 'halaman-masuk.html';
            }
        });
    }

    // Update info profil di header (jika ada)
    const headerProfilePicAuth = document.getElementById('headerProfilePic'); // Targetkan elemen di header
    if (headerProfilePicAuth && currentUser) {
        headerProfilePicAuth.src = currentUser.profileImage || 'images/placeholder-profile.png';
    }
});