// darkMode.js — Dark mode toggle for SQL Assist

(function () {
    var STORAGE_KEY = 'sqlAssistDarkMode';

    function isDark() {
        return document.documentElement.classList.contains('dark-mode');
    }

    function applyDarkMode(dark) {
        if (dark) {
            document.documentElement.classList.add('dark-mode');
            document.body.classList.add('dark-mode');
            localStorage.setItem(STORAGE_KEY, '1');
            btn.textContent = '☀️ Light';
            btn.title = 'Switch to Light Mode';
        } else {
            document.documentElement.classList.remove('dark-mode');
            document.body.classList.remove('dark-mode');
            localStorage.setItem(STORAGE_KEY, '0');
            btn.textContent = '🌙 Dark';
            btn.title = 'Switch to Dark Mode';
        }
    }

    var btn = document.getElementById('darkModeToggle');
    if (!btn) return;

    // Set initial button label to match current state
    applyDarkMode(isDark());

    btn.addEventListener('click', function () {
        applyDarkMode(!isDark());
    });
})();
