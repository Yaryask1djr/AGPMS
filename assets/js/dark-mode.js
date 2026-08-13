document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('darkModeToggle');
    if (!toggleBtn) return;

    // Check local storage for preference
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        toggleBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
    }

    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        let isDark = document.body.classList.contains('dark-mode');
        
        if (isDark) {
            localStorage.setItem('theme', 'dark');
            toggleBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
        } else {
            localStorage.setItem('theme', 'light');
            toggleBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
        }
    });
});
