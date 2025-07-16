document.addEventListener('DOMContentLoaded', function () {
    // --- منطق تبديل الوضع المظلم ---
    const themeToggle = document.querySelector('.theme-toggle');
    const body = document.body;

    // تحميل الوضع المحفوظ
    const savedTheme = localStorage.getItem('theme') || 'light';
    if (savedTheme === 'dark') {
        body.classList.replace('light-mode', 'dark-mode');
        themeToggle.textContent = '☀️';
    }

    themeToggle.addEventListener('click', () => {
        if (body.classList.contains('light-mode')) {
            body.classList.replace('light-mode', 'dark-mode');
            localStorage.setItem('theme', 'dark-mode');
            themeToggle.textContent = '☀️';
        } else {
            body.classList.replace('dark-mode', 'light-mode');
            localStorage.setItem('theme', 'light-mode');
            themeToggle.textContent = '🌙';
        }
    });

    // --- منطق إظهار وإخفاء شريط البحث ---
    const searchToggle = document.querySelector('.search-toggle');
    const searchBar = document.querySelector('.search-bar');

    searchToggle.addEventListener('click', () => {
        searchBar.classList.toggle('active');
        // للتركيز التلقائي على حقل البحث عند إظهاره
        if (searchBar.classList.contains('active')) {
            searchBar.querySelector('input[type="search"]').focus();
        }
    });
});