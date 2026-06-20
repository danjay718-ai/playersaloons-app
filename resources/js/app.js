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
    
    // Initialize Dashboard Sidebar
    initSidebar();
});

document.addEventListener('livewire:navigated', () => {
    // Re-initialize icons after Livewire navigation
    if (window.lucide) {
        window.lucide.createIcons();
    }
    
    // Re-initialize sidebar logic if elements exist
    initSidebar();
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
 * MOBILE SIDEBAR — open / close / swipe-to-close
 */
function initSidebar() {
    const menuBtn       = document.getElementById('mobile-menu-btn');
    const backdrop      = document.getElementById('mobile-backdrop');
    const mobileSidebar = document.getElementById('mobile-sidebar');

    if (!menuBtn || !mobileSidebar) return;

    function openDrawer() {
        mobileSidebar.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        menuBtn.classList.add('burger-open');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeDrawer() {
        mobileSidebar.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
        menuBtn.classList.remove('burger-open');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    // Toggle drawer
    menuBtn.onclick = () => {
        const isOpen = mobileSidebar.classList.contains('open');
        isOpen ? closeDrawer() : openDrawer();
    };

    // Close by tapping the backdrop overlay
    if (backdrop) {
        backdrop.onclick = closeDrawer;
    }

    // Close on Escape key
    document.onkeydown = (e) => {
        if (e.key === 'Escape' && mobileSidebar.classList.contains('open')) {
            closeDrawer();
        }
    };

    // Close drawer when a nav link is clicked (wire:navigate)
    mobileSidebar.querySelectorAll('a[wire\\:navigate]').forEach(link => {
        link.onclick = closeDrawer;
    });
}
