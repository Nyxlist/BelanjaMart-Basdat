/**
 * BelanjaMart - small frontend toolkit.
 * Vanilla JS, no build step.  Feel free to extend.
 */
(function () {
    /* --------------------- Theme --------------------- */
    const THEME_KEY = 'bm-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        document.querySelectorAll('[data-theme-toggle] .icon').forEach(el => {
            el.textContent = theme === 'dark' ? 'Light' : 'Dark';
        });
    }

    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(saved || (prefersDark ? 'dark' : 'light'));
        document.querySelectorAll('[data-theme-toggle]').forEach(btn =>
            btn.addEventListener('click', () => {
                const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            })
        );
    }

    /* --------------------- Toast --------------------- */
    function ensureStack() {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    window.toast = function (msg, type = 'info', timeout = 3500) {
        const stack = ensureStack();
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.textContent = msg;
        stack.appendChild(el);
        setTimeout(() => {
            el.style.opacity = 0;
            el.style.transform = 'translateX(20px)';
            setTimeout(() => el.remove(), 250);
        }, timeout);
    };

    /* --------------------- Modal --------------------- */
    window.modalOpen = function (id) {
        const m = document.getElementById(id);
        if (m) m.classList.add('show');
    };
    window.modalClose = function (id) {
        const m = document.getElementById(id);
        if (m) m.classList.remove('show');
    };
    document.addEventListener('click', e => {
        if (e.target.matches('.modal-backdrop')) e.target.classList.remove('show');
        if (e.target.matches('[data-modal-open]')) modalOpen(e.target.dataset.modalOpen);
        if (e.target.matches('[data-modal-close]')) {
            const root = e.target.closest('.modal-backdrop');
            if (root) root.classList.remove('show');
        }
    });

    /* --------------------- Confirm guards --------------------- */
    document.addEventListener('click', e => {
        const link = e.target.closest('[data-confirm]');
        if (!link) return;
        if (!confirm(link.dataset.confirm)) e.preventDefault();
    });

    /* --------------------- Language dropdown --------------------- */
    function initLangSwitcher() {
        const btn = document.getElementById('langToggle');
        const dropdown = document.getElementById('langDropdown');
        if (!btn || !dropdown) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            dropdown.classList.remove('show');
        });

        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /* --------------------- Init --------------------- */
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initLangSwitcher();
    });
})();
