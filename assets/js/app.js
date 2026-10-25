/**
 * MarketSentinel - Core Application UI Interactions
 * 
 * Handles:
 * - Off-canvas sidebar open, close, and keyboard accessibility
 * - Sidebar grouped screens collapsible accordions
 * - Header user avatar circle popover / dropdown menu
 * - Theme switcher (Executive Dark Slate/Navy <-> Crisp Light Finance) with localStorage persistence
 */

document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------------------
    // 1. Sidebar Toggle & Off-Canvas Management
    // -------------------------------------------------------------------------
    const sidebar = document.getElementById('app-sidebar');
    const sidebarToggleBtn = document.getElementById('btn-sidebar-toggle');
    const sidebarCloseBtn = document.getElementById('btn-sidebar-close');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('is-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.add('is-open');
        document.body.classList.add('sidebar-active');
        if (sidebarToggleBtn) sidebarToggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('is-open');
        document.body.classList.remove('sidebar-active');
        if (sidebarToggleBtn) sidebarToggleBtn.setAttribute('aria-expanded', 'false');
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeSidebar();
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            closeSidebar();
        });
    }

    // -------------------------------------------------------------------------
    // 2. Sidebar Group Accordion Toggle
    // -------------------------------------------------------------------------
    const groupHeaders = document.querySelectorAll('.nav-group-header');
    groupHeaders.forEach(header => {
        header.addEventListener('click', (e) => {
            e.preventDefault();
            const group = header.closest('.nav-group');
            if (!group) return;
            const isOpen = group.classList.toggle('is-open');
            header.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    // -------------------------------------------------------------------------
    // 3. Header User Avatar Menu Dropdown
    // -------------------------------------------------------------------------
    const userMenuWrapper = document.getElementById('user-menu-wrapper');
    const userAvatarBtn = document.getElementById('btn-user-avatar');

    function closeUserMenu() {
        if (!userMenuWrapper) return;
        userMenuWrapper.classList.remove('is-open');
        if (userAvatarBtn) userAvatarBtn.setAttribute('aria-expanded', 'false');
    }

    if (userAvatarBtn && userMenuWrapper) {
        userAvatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = userMenuWrapper.classList.toggle('is-open');
            userAvatarBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuWrapper.contains(e.target)) {
                closeUserMenu();
            }
        });
    }

    // -------------------------------------------------------------------------
    // 4. Institutional Theme Switcher (Dark Executive / Light Finance)
    // -------------------------------------------------------------------------
    const themeToggleBtn = document.getElementById('btn-theme-toggle');
    const savedTheme = localStorage.getItem('marketsentinel_theme');

    function updateThemeIcon(isLight) {
        if (!themeToggleBtn) return;
        if (isLight) {
            themeToggleBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
            `;
            themeToggleBtn.title = "Switch to Executive Dark Slate Mode";
        } else {
            themeToggleBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
                </svg>
            `;
            themeToggleBtn.title = "Switch to Crisp Light Finance Mode";
        }
    }

    if (savedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
        updateThemeIcon(true);
    } else {
        updateThemeIcon(false);
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            if (newTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('marketsentinel_theme', 'light');
                updateThemeIcon(true);
            } else {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('marketsentinel_theme', 'dark');
                updateThemeIcon(false);
            }
        });
    }

    // -------------------------------------------------------------------------
    // 5. Global Keyboard Accessibility (Escape key)
    // -------------------------------------------------------------------------
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeSidebar();
            closeUserMenu();
        }
    });
});
