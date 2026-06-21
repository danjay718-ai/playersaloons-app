/**
 * PlayerSaloons Global Scripts
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons on first load
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // Initialize Mobile Bottom Nav "More" Panel
    initMobileMorePanel();
});

document.addEventListener('livewire:navigated', () => {
    // Re-initialize icons after Livewire navigation
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // Re-initialize mobile more panel after navigation
    initMobileMorePanel();
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('message.processed', (message, component) => {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    });
});

/**
 * REALTIME NOTIFICATIONS — subscribe after Livewire initialises so auth user uuid is available
 */
document.addEventListener('livewire:init', () => {
    const userUuid = document.querySelector('meta[name="user-uuid"]')?.getAttribute('content');
    if (!userUuid || !window.Echo) return;

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
