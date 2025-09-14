    </main>
    <footer>
        <div class="container"> <!-- يجب أن يكون هناك حاوية لتطبيق التنسيقات -->
             <div class="open-source-msg">
                <p>هذا المشروع مفتوح المصدر ومتاح للجميع بهدف خدمة الشعر العربي</p>
                <p><a href="https://github.com/SMSMy/poetry" target="_blank" rel="noopener noreferrer">المشروع على GitHub</a></p>
                <p>© <?= date('Y') ?> ديوان العرب - جميع الحقوق محفوظة</p>
            </div>
        </div>
    </footer>

<script>
// تبديل الوضع المظلم/الفاتح
document.getElementById('theme-toggle').addEventListener('click', function() {
    const body = document.body;
    const isDark = body.classList.contains('dark-mode');
    if (isDark) {
        body.classList.replace('dark-mode', 'light-mode');
        this.textContent = '🌙';
        localStorage.setItem('theme', 'light');
    } else {
        body.classList.replace('light-mode', 'dark-mode');
        this.textContent = '☀️';
        localStorage.setItem('theme', 'dark');
    }
});

// تحميل الوضع المحفوظ
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const body = document.body;
    const themeToggle = document.getElementById('theme-toggle');
    if (savedTheme === 'dark') {
        body.classList.remove('light-mode');
        body.classList.add('dark-mode');
        if(themeToggle) themeToggle.textContent = '☀️';
    } else {
        body.classList.remove('light-mode');
        body.classList.add('light-mode');
        if(themeToggle) themeToggle.textContent = '🌙';
    }
});
</script>

</body>
</html>