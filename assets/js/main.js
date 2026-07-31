/**
 * clayon/assets/js/main.js
 * 
 * Shared UI logic for Clayon SMS Reseller Platform.
 * Includes SPA-style page loading and transitions.
 */

window.__sidebarToggleHandledByMainJS = true;

document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const loader = document.getElementById('clayon-loader');
    const loaderBar = loader ? loader.querySelector('.loader-bar') : null;
    const mainContent = document.querySelector('.main-content');

    // Page Entrance Animation
    if (mainContent) {
        mainContent.classList.add('page-transition-in');
    }

    // Sidebar Logic
    if (hamburger && sidebar && overlay) {
        const updateSidebarIcon = (isOpen) => {
            const icon = hamburger.querySelector('i');
            if (!icon) return;
            icon.classList.toggle('fa-bars', !isOpen);
            icon.classList.toggle('fa-times', isOpen);
        };

        const closeSidebar = () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
            updateSidebarIcon(false);
        };

        const toggleSidebar = (event) => {
            if (event && typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            const open = !sidebar.classList.contains('active');
            sidebar.classList.toggle('active', open);
            overlay.classList.toggle('active', open);
            document.body.classList.toggle('sidebar-open', open);
            updateSidebarIcon(open);
        };

        hamburger.addEventListener('click', toggleSidebar, true);
        overlay.addEventListener('click', (event) => {
            if (event && typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            closeSidebar();
        }, true);

        document.querySelectorAll('.nav-item').forEach(navItem => {
            navItem.addEventListener('click', () => {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024 && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    }

    // SPA-Style Loading Bar & Navigation
    const links = document.querySelectorAll('a:not([target="_blank"]):not([href^="#"]):not([href^="javascript"])');
    
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            
            // Skip if it's the same page or external
            if (!href || href === window.location.href || href.includes('logout.php')) return;

            e.preventDefault();
            
            // Show loader
            if (loader && loaderBar) {
                loader.style.display = 'block';
                setTimeout(() => { loaderBar.style.width = '70%'; }, 10);
            }

            // Fade out current content
            if (mainContent) {
                mainContent.classList.add('page-transition-out');
            }

            // Navigate after short delay to let animations start
            setTimeout(() => {
                window.location.href = href;
            }, 300);
        });
    });

    // Handle Page Show (back button case)
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && loader) {
            loader.style.display = 'none';
            if (loaderBar) loaderBar.style.width = '0%';
            if (mainContent) mainContent.classList.remove('page-transition-out');
        }
    });
});
