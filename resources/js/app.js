/**
 * PlayerSaloons Global Scripts
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons on first load
    if (window.lucide) {
        window.lucide.createIcons();
    }

    initPublicShell();

    // Initialize Mobile Bottom Nav "More" Panel
    initMobileMorePanel();
});

document.addEventListener('livewire:navigated', () => {
    // Re-initialize icons after Livewire navigation
    if (window.lucide) {
        window.lucide.createIcons();
    }

    initPublicShell();

    // Re-initialize mobile more panel after navigation
    initMobileMorePanel();
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('message.processed', (message, component) => {
        if (window.lucide) {
            window.lucide.createIcons();
        }

        initPublicShell();
    });
});

/**
 * REALTIME NOTIFICATIONS — subscribe after Livewire initialises so auth user uuid is available
 */
document.addEventListener('livewire:init', () => {
    const userUuid = document.querySelector('meta[name="user-uuid"]')?.getAttribute('content');
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const reverbHost = import.meta.env.VITE_REVERB_HOST;

    if (!userUuid || !reverbKey || !reverbHost) return;

    window.Echo ??= new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo.private(`user.${userUuid}`)
        .listen('.notification.received', () => {
            Livewire.dispatch('notification.received');
        });
});

/**
 * MOBILE BOTTOM NAV — "More" Panel open / close
 *
 * A slide-up sheet appears when the "More" button is tapped,
 * presenting secondary navigation items in a 3-column grid.
 * Closing happens via backdrop tap, the close handle, Escape key,
 * or when any nav link inside the panel is activated.
 */
function initMobileMorePanel() {
    const moreBtn    = document.getElementById('mobile-more-btn');
    const backdrop   = document.getElementById('mobile-more-backdrop');
    const panel      = document.getElementById('mobile-more-panel');

    // Guard: elements may not exist on pages that don't use this layout
    if (!moreBtn || !panel) return;

    // Avoid binding duplicate event listeners on re-init (livewire:navigated)
    if (moreBtn._moreInitialised) return;
    moreBtn._moreInitialised = true;

    function openMore() {
        panel.classList.add('open');
        if (backdrop) backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        moreBtn.setAttribute('aria-expanded', 'true');
        moreBtn.classList.add('active');
    }

    function closeMore() {
        panel.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        document.body.style.overflow = '';
        moreBtn.setAttribute('aria-expanded', 'false');
        moreBtn.classList.remove('active');
    }

    // Toggle panel on "More" button click
    moreBtn.addEventListener('click', () => {
        const isOpen = panel.classList.contains('open');
        isOpen ? closeMore() : openMore();
    });

    // Close via backdrop tap
    if (backdrop) {
        backdrop.addEventListener('click', closeMore);
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('open')) {
            closeMore();
        }
    });

    // Close panel when a nav link inside it is activated
    panel.querySelectorAll('a[wire\\:navigate]').forEach(link => {
        link.addEventListener('click', closeMore);
    });

    // Close panel when any form in the panel is submitted (logout)
    panel.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', closeMore);
    });
}

function initPublicShell() {
    initPublicMobileMenu();
    initPublicPwaInstall();

    if (window.lucide) {
        window.lucide.createIcons();
    }
}

function initPublicMobileMenu() {
    document.querySelectorAll('[data-public-menu-button]').forEach(button => {
        if (button._publicMenuInitialised) return;
        button._publicMenuInitialised = true;

        const header = button.closest('header');
        const menu = header ? header.querySelector('[data-public-mobile-menu]') : null;
        const openIcon = button.querySelector('[data-menu-icon-open]');
        const closeIcon = button.querySelector('[data-menu-icon-close]');

        button.addEventListener('click', () => {
            if (!menu) return;

            const isOpen = !menu.classList.contains('hidden');
            menu.classList.toggle('hidden', isOpen);
            button.setAttribute('aria-expanded', String(!isOpen));
            openIcon?.classList.toggle('hidden', !isOpen);
            closeIcon?.classList.toggle('hidden', isOpen);

            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    });
}

function initPublicPwaInstall() {
    if ('serviceWorker' in navigator && !window.__playerSaloonsServiceWorkerRegistered) {
        window.__playerSaloonsServiceWorkerRegistered = true;
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(err => console.error('SW registration failed:', err));
        });
    }

    const installBtns = document.querySelectorAll('.pwa-install-btn');
    if (!installBtns.length) return;

    const isStandalone = () => {
        return window.navigator.standalone === true
            || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    };

    const isDesktop = () => {
        return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
    };

    const hideButton = button => {
        button.classList.add('hidden');
        button.classList.remove('inline-flex');
        button.disabled = true;
    };

    const showButton = button => {
        button.classList.remove('hidden');
        button.classList.add('inline-flex');
        button.disabled = !window.__playerSaloonsPwaPrompt;
    };

    const syncInstallButtons = () => {
        if (isStandalone()) {
            installBtns.forEach(hideButton);
            return;
        }

        const desktop = isDesktop();
        installBtns.forEach(button => {
            const isDesktopButton = button.hasAttribute('data-pwa-install-desktop');
            const isMobileButton = button.hasAttribute('data-pwa-install-mobile');
            const isDashboardButton = button.hasAttribute('data-pwa-install-dashboard');

            if ((desktop && isDesktopButton) || (!desktop && isMobileButton) || isDashboardButton) {
                showButton(button);
                return;
            }

            hideButton(button);
        });
    };

    if (!window.__playerSaloonsPwaListenerAttached) {
        window.__playerSaloonsPwaListenerAttached = true;

        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault();
            window.__playerSaloonsPwaPrompt = event;
            syncInstallButtons();
        });

        window.addEventListener('appinstalled', () => {
            window.__playerSaloonsPwaPrompt = null;
            installBtns.forEach(hideButton);
        });

        window.addEventListener('resize', syncInstallButtons);
    }

    installBtns.forEach(button => {
        if (button._pwaInstallInitialised) return;
        button._pwaInstallInitialised = true;

        button.addEventListener('click', async () => {
            if (!window.__playerSaloonsPwaPrompt) return;

            window.__playerSaloonsPwaPrompt.prompt();
            await window.__playerSaloonsPwaPrompt.userChoice;
            window.__playerSaloonsPwaPrompt = null;
            installBtns.forEach(hideButton);
        });
    });

    syncInstallButtons();
}
