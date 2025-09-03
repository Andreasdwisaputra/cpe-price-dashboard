// Ambil semua elemen yang kita butuhkan
const menuToggle = document.getElementById('menu-toggle');
const sidebar = document.querySelector('.sidebar');
const overlay = document.querySelector('.overlay');
const userDropdown = document.querySelector('.user-dropdown');
const dropdown = document.querySelector('.dropdown');

// --- Logika untuk Hamburger Menu ---
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
});

// Tutup sidebar saat overlay di-klik
overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
});


// --- Logika untuk Dropdown User (tetap sama) ---
userDropdown.addEventListener('click', (event) => {
    dropdown.classList.toggle('show');
    event.stopPropagation();
});

window.addEventListener('click', (event) => {
    // Tutup dropdown user jika klik di luar
    if (!userDropdown.contains(event.target)) {
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
});