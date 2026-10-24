/**
 * BasePHP - Core Application UI Interactions
 * 
 * Handles:
 * - Off-canvas sidebar open, close, and keyboard accessibility
 * - Sidebar grouped screens collapsible accordions
 * - Header user avatar circle popover / dropdown menu
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
    // 4. Global Keyboard Accessibility (Escape key)
    // -------------------------------------------------------------------------
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeSidebar();
            closeUserMenu();
        }
    });
});
