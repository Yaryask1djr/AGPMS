// Immediate theme apply to prevent flash of unstyled theme
(function() {
    try {
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
            if (document.body) document.body.classList.add('dark-mode');
        }
    } catch (e) {}
})();

document.addEventListener('DOMContentLoaded', () => {
    // Ensure body has the class if documentElement has it
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }

    const toggleBtn = document.getElementById('darkModeToggle');
    if (!toggleBtn) return;

    // Set initial icon
    if (document.body.classList.contains('dark-mode')) {
        toggleBtn.innerHTML = '<i class="bi bi-sun-fill text-warning"></i>';
        toggleBtn.setAttribute('title', 'Switch to Light Mode');
    } else {
        toggleBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
        toggleBtn.setAttribute('title', 'Switch to Dark Mode');
    }

    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        
        if (isDark) {
            localStorage.setItem('theme', 'dark');
            toggleBtn.innerHTML = '<i class="bi bi-sun-fill text-warning"></i>';
            toggleBtn.setAttribute('title', 'Switch to Light Mode');
        } else {
            localStorage.setItem('theme', 'light');
            toggleBtn.innerHTML = '<i class="bi bi-moon-fill"></i>';
            toggleBtn.setAttribute('title', 'Switch to Dark Mode');
        }
    });
});
