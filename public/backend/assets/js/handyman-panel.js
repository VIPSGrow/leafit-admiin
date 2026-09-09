/* handyman-panel.js — Sidebar toggle logic (Bootstrap 5, no STISLA) */
(function () {
    'use strict';

    var MINI_STORAGE_KEY = 'handyman-sidebar-mini';

    function isMobile() {
        return window.innerWidth <= 991;
    }

    function toggleSidebar() {
        if (isMobile()) {
            document.body.classList.toggle('sidebar-show');
        } else {
            var isMini = document.body.classList.toggle('sidebar-mini');
            try { localStorage.setItem(MINI_STORAGE_KEY, isMini ? '1' : '0'); } catch (e) { /* storage unavailable */ }
        }
    }

    function closeMobileSearch() {
        var searchEl = document.querySelector('.search-element');
        if (!searchEl) return;
        searchEl.classList.remove('search-active');
        window.dispatchEvent(new CustomEvent('handyman-search-close'));
    }

    var globalsInitialized = false;
    function initGlobals() {
        if (globalsInitialized) return;
        globalsInitialized = true;

        // Close sidebar on overlay click or close button (mobile)
        document.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('sidebar-overlay')) {
                document.body.classList.remove('sidebar-show');
            }
        });

        // Close search result (and mobile search) when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.search-element')) {
                window.dispatchEvent(new CustomEvent('handyman-search-close'));
                if (isMobile()) closeMobileSearch();
            }
        });

        // Reset sidebar-show and mobile search when resizing to desktop
        window.addEventListener('resize', function () {
            if (!isMobile()) {
                document.body.classList.remove('sidebar-show');
                closeMobileSearch();
            }
        });
    }

    function initPageElements() {
        // Wire sidebar toggle buttons.
        // stopImmediatePropagation() blocks the legacy STISLA sidebar-toggle
        // handler in the shared scripts.js (also bound to [data-toggle="sidebar"])
        // from firing too — both handlers toggling body classes independently
        // was cancelling out the collapse on desktop.
        document.querySelectorAll('[data-toggle="sidebar"]').forEach(function (btn) {
            if (btn.dataset.handymanBound) return;
            btn.dataset.handymanBound = '1';

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                toggleSidebar();
            });
        });

        var closeBtn = document.getElementById('handyman-sidebar-close');
        if (closeBtn && !closeBtn.dataset.handymanBound) {
            closeBtn.dataset.handymanBound = '1';
            closeBtn.addEventListener('click', function () {
                document.body.classList.remove('sidebar-show');
            });
        }

        // Mobile search toggle
        var searchEl = document.querySelector('.search-element');
        var searchInput = document.getElementById('menu-search');
        var searchBtn = searchEl ? searchEl.querySelector('.btn') : null;

        if (searchBtn && searchInput && !searchBtn.dataset.handymanBound) {
            searchBtn.dataset.handymanBound = '1';
            searchBtn.addEventListener('click', function (e) {
                if (!isMobile()) return;
                e.stopPropagation();
                if (searchEl.classList.contains('search-active')) {
                    closeMobileSearch();
                } else {
                    searchEl.classList.add('search-active');
                    window.dispatchEvent(new CustomEvent('handyman-search-open'));
                    setTimeout(function () { searchInput.focus(); }, 50);
                }
            });

            if (!searchInput.dataset.handymanBound) {
                searchInput.dataset.handymanBound = '1';
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && isMobile()) closeMobileSearch();
                });
            }
        }
    }

    function setup() {
        initGlobals();
        initPageElements();
    }

    document.addEventListener('turbo:load', setup);
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.Turbo === 'undefined') {
            setup();
        }
    });
})();
