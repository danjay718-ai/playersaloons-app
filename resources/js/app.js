/**
 * PlayerSaloons Global Scripts
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.ensurePlayerSaloonsEcho = function () {
    const userUuid = document.querySelector('meta[name="user-uuid"]')?.getAttribute('content');
    const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
    const reverbHost = import.meta.env.VITE_REVERB_HOST;

    if (!userUuid || !reverbKey || !reverbHost) return null;

    window.Echo ??= new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
};

window.matchRoomRealtime = function (wire, matchUuid) {
    return {
        echo: null,
        channelName: `match.${matchUuid}`,

        init() {
            this.echo = window.ensurePlayerSaloonsEcho?.();
            if (!this.echo) return;

            this.echo.private(this.channelName)
                .listen('.match.result.submitted', () => wire.$refresh())
                .listen('.match.rematch.created', (event) => {
                    if (!event.same_match && event.rematch_uuid) {
                        const url = `/matches/${event.rematch_uuid}`;
                        window.Livewire?.navigate ? window.Livewire.navigate(url) : window.location.assign(url);
                        return;
                    }

                    wire.$refresh();
                })
                .listen('.match.completed', () => wire.$refresh());
        },

        destroy() {
            this.echo?.leave(this.channelName);
        },
    };
};

window.imageCropUpload = function (config) {
    return {
        ...config,
        mode: config.mode || 'livewire',
        cropOpen: false,
        processing: false,
        uploading: false,
        progress: 0,
        clientError: '',
        fileName: '',
        sourceWidth: 0,
        sourceHeight: 0,
        zoom: 1,
        positionX: 0,
        positionY: 0,
        image: null,
        originalFile: null,
        objectUrl: null,
        previewUrl: config.previewUrl || '',
        previewObjectUrl: null,
        pendingFileName: '',

        async selectFile(event) {
            const file = event.target.files?.[0];
            this.clientError = '';
            this.fileName = '';

            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                this.rejectFile('Use a JPG, PNG, or WebP image.');
                return;
            }
            this.releaseObjectUrl();
            this.objectUrl = URL.createObjectURL(file);
            const image = new Image();
            image.src = this.objectUrl;

            try {
                await image.decode();
            } catch (_) {
                this.rejectFile('The selected image could not be read.');
                return;
            }

            this.image = image;
            this.originalFile = file;
            this.sourceWidth = image.naturalWidth;
            this.sourceHeight = image.naturalHeight;

            if (this.sourceWidth === this.width && this.sourceHeight === this.height) {
                this.uploadFile(file);
                return;
            }

            this.zoom = 1;
            this.positionX = 0;
            this.positionY = 0;
            this.cropOpen = true;
            this.$nextTick(() => this.drawCrop());
        },

        drawCrop() {
            if (!this.image || !this.$refs.canvas) return;

            const canvas = this.$refs.canvas;
            if (canvas.width !== this.width || canvas.height !== this.height) {
                canvas.width = this.width;
                canvas.height = this.height;
            }
            const context = canvas.getContext('2d');
            const sourceRatio = this.sourceWidth / this.sourceHeight;
            const targetRatio = this.width / this.height;
            let cropWidth;
            let cropHeight;

            if (sourceRatio > targetRatio) {
                cropHeight = this.sourceHeight;
                cropWidth = cropHeight * targetRatio;
            } else {
                cropWidth = this.sourceWidth;
                cropHeight = cropWidth / targetRatio;
            }

            cropWidth /= this.zoom;
            cropHeight /= this.zoom;
            const sourceX = (this.sourceWidth - cropWidth) * ((this.positionX + 100) / 200);
            const sourceY = (this.sourceHeight - cropHeight) * ((this.positionY + 100) / 200);

            context.clearRect(0, 0, this.width, this.height);
            context.drawImage(this.image, sourceX, sourceY, cropWidth, cropHeight, 0, 0, this.width, this.height);
        },

        applyCrop() {
            if (!this.$refs.canvas || this.processing) return;
            this.processing = true;
            this.$refs.canvas.toBlob((blob) => {
                if (!blob) {
                    this.processing = false;
                    this.clientError = 'The cropped image could not be created.';
                    return;
                }
                const baseName = (this.originalFile?.name || 'image').replace(/\.[^.]+$/, '');
                const croppedFile = new File([blob], `${baseName}-${this.width}x${this.height}.webp`, {
                    type: 'image/webp',
                    lastModified: Date.now(),
                });
                this.cropOpen = false;
                this.processing = false;
                this.uploadFile(croppedFile);
            }, 'image/webp', 0.9);
        },

        uploadFile(file) {
            this.clientError = '';
            this.pendingFileName = file.name;

            if (this.mode === 'form') {
                const input = this.$refs.formInput;
                if (!input) {
                    this.clientError = 'The cropped image could not be attached to this form.';
                    return;
                }

                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                this.setPreview(file);
                this.fileName = file.name;
                if (this.$refs.input) this.$refs.input.value = '';
                this.image = null;
                this.originalFile = null;
                this.releaseObjectUrl();

                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.uploadInput.files = transfer.files;
            this.$refs.uploadInput.dispatchEvent(new Event('change', { bubbles: true }));
        },

        finishUpload() {
            this.uploading = false;
            this.progress = 100;
            this.fileName = this.pendingFileName;
            if (this.$refs.input) this.$refs.input.value = '';
            this.image = null;
            this.originalFile = null;
            this.releaseObjectUrl();
            window.dispatchEvent(new CustomEvent('image-crop-upload-finished'));
        },

        failUpload() {
            this.uploading = false;
            this.clientError = 'Upload failed. Please try again.';
        },

        cancelCrop() {
            this.cropOpen = false;
            this.processing = false;
            if (this.$refs.input) this.$refs.input.value = '';
            this.image = null;
            this.originalFile = null;
            this.releaseObjectUrl();
        },

        rejectFile(message) {
            this.clientError = message;
            this.cropOpen = false;
            if (this.$refs.input) this.$refs.input.value = '';
            this.releaseObjectUrl();
        },

        releaseObjectUrl() {
            if (!this.objectUrl) return;
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        },

        setPreview(file) {
            if (this.previewObjectUrl) URL.revokeObjectURL(this.previewObjectUrl);
            this.previewObjectUrl = URL.createObjectURL(file);
            this.previewUrl = this.previewObjectUrl;
        },

        releasePreviewUrl() {
            if (!this.previewObjectUrl) return;
            URL.revokeObjectURL(this.previewObjectUrl);
            this.previewObjectUrl = null;
        },

        destroy() {
            this.releaseObjectUrl();
            this.releasePreviewUrl();
        },
    };
};

window.systemClock = function (config) {
    const serverEpoch = Date.parse(config.now);
    const browserEpoch = Date.now();

    return {
        timezone: config.timezone,
        currentEpoch: Number.isNaN(serverEpoch) ? browserEpoch : serverEpoch,
        timer: null,
        init() {
            this.tick();
            this.timer = window.setInterval(() => this.tick(), 1000);
        },
        tick() {
            this.currentEpoch = (Number.isNaN(serverEpoch) ? browserEpoch : serverEpoch) + (Date.now() - browserEpoch);
        },
        get time() {
            return new Intl.DateTimeFormat(undefined, {
                timeZone: this.timezone,
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true,
            }).format(new Date(this.currentEpoch));
        },
        get date() {
            return new Intl.DateTimeFormat(undefined, {
                timeZone: this.timezone,
                month: 'short', day: '2-digit', year: 'numeric',
            }).format(new Date(this.currentEpoch));
        },
        destroy() {
            if (this.timer) window.clearInterval(this.timer);
        },
    };
};

window.gameManagementUi = function (wire) {
    const setDeferred = (property, value) => wire.$set(property, value, false);

    return {
        gameModalOpen: wire.entangle('showGameModal'),
        deleteModalOpen: wire.entangle('showDeleteModal'),
        activeGameLocale: 'en',
        gameTranslations: {},

        assignGame(game) {
            const locale = game.locale || 'en';
            const translation = game.translations?.[locale] || { name: '', description: '' };

            this.activeGameLocale = locale;
            this.gameTranslations = game.translations || {};

            setDeferred('selectedGameId', game.id ?? null);
            setDeferred('gameLocale', locale);
            setDeferred('gameName', translation.name || '');
            setDeferred('gameSlug', game.slug || '');
            setDeferred('gameDescription', translation.description || '');
            setDeferred('gameBannerPath', game.bannerPath || '');
            setDeferred('gameCardImagePath', game.cardImagePath || '');
            setDeferred('gameIsActive', game.isActive ?? true);
            setDeferred('gamePlatformIds', game.platformIds || []);
            setDeferred('removeGameCardImage', false);
            setDeferred('removeGameBannerImage', false);
            setDeferred('gameCardImage', null);
            setDeferred('gameBannerImage', null);
        },

        openCreateGame() {
            this.assignGame({
                locale: 'en',
                translations: { en: { name: '', description: '' } },
                isActive: true,
            });
            this.gameModalOpen = true;
        },

        openEditGame(game) {
            this.assignGame(game);
            this.gameModalOpen = true;
        },

        closeGameModal() {
            this.gameModalOpen = false;
        },

        switchGameLocale(locale) {
            const translation = this.gameTranslations[locale] || { name: '', description: '' };
            this.activeGameLocale = locale;
            setDeferred('gameLocale', locale);
            setDeferred('gameName', translation.name || '');
            setDeferred('gameDescription', translation.description || '');
        },

        openGameArchive(gameId) {
            setDeferred('deleteTargetType', 'game');
            setDeferred('deleteTargetId', gameId);
            setDeferred('gameDeleteImpact', []);
            this.deleteModalOpen = true;
            wire.confirmDelete('game', gameId);
        },

        closeDeleteModal() {
            this.deleteModalOpen = false;
        },
    };
};

window.chatConsole = function (config) {
    return {
        endpoints: config.endpoints,
        csrf: config.csrf,
        currentUserUuid: config.currentUserUuid,
        conversations: [],
        teams: [],
        currentTeam: null,
        pendingTeamJoin: null,
        teamJoinModalOpen: false,
        selected: null,
        messages: [],
        draft: '',
        playerSearch: '',
        playerResults: [],
        searchError: '',
        error: '',
        unread: {},
        filter: 'all',
        loadingMessages: true,
        bootError: '',
        sending: false,
        connected: false,
        subscriptions: new Set(),
        selectedPlayer: null,
        playerModalOpen: false,
        playerLoading: false,
        activeFilterClass: 'rounded-lg border border-fuchsia-400/40 bg-fuchsia-600/20 px-3 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-fuchsia-100',
        idleFilterClass: 'rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500 hover:text-zinc-200',

        async init() {
            try {
                await this.loadConversations();
                this.connected = Boolean(window.ensurePlayerSaloonsEcho?.());
            } catch (error) {
                this.loadingMessages = false;
                this.bootError = error?.message || 'Chat could not sync. Refresh the page or try again.';
            }
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    ...(options.headers || {})
                },
                credentials: 'same-origin',
                ...options
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw data;
            }

            return data;
        },

        async loadConversations() {
            const data = await this.request(this.endpoints.conversations);
            this.conversations = data.conversations || [];
            this.teams = data.teams || [];
            this.currentTeam = data.current_team || null;

            for (const conversation of this.conversations) {
                if (conversation.is_unread) {
                    this.unread[conversation.uuid] = true;
                }
                this.subscribe(conversation.uuid);
            }

            if (!this.selected && this.conversations.length > 0) {
                await this.selectConversation(this.conversations[0]);
                return;
            }

            this.loadingMessages = false;
        },

        filteredConversations() {
            if (this.filter === 'all') return this.conversations;
            return this.conversations.filter((conversation) => conversation.type === this.filter);
        },

        async selectConversation(conversation) {
            this.selected = conversation;
            this.unread[conversation.uuid] = false;
            conversation.is_unread = false;
            this.loadingMessages = true;
            this.error = '';

            try {
                const data = await this.request(`${this.endpoints.messages}/${conversation.uuid}/messages`);
                this.messages = data.messages || [];
                this.subscribe(conversation.uuid);
                this.scrollToBottom();
            } catch (error) {
                this.error = error?.message || 'Could not load messages.';
            } finally {
                this.loadingMessages = false;
            }
        },

        subscribe(uuid) {
            if (this.subscriptions.has(uuid)) return;

            const echo = window.ensurePlayerSaloonsEcho?.();
            if (!echo) return;

            echo.private(`chat.${uuid}`)
                .listen('.chat.message.sent', (event) => {
                    if (this.selected?.uuid === uuid) {
                        this.pushMessage(event.message);
                        return;
                    }

                    this.unread[uuid] = true;
                    const conversation = this.conversations.find((item) => item.uuid === uuid);
                    if (conversation) {
                        conversation.is_unread = true;
                    }
                });

            this.subscriptions.add(uuid);
            this.connected = true;
        },

        pushMessage(message) {
            if (this.messages.some((existing) => existing.uuid === message.uuid)) return;
            this.messages.push(message);
            this.scrollToBottom();
        },

        async sendMessage() {
            if (!this.selected || this.sending || this.draft.trim().length === 0) return;

            this.sending = true;
            this.error = '';

            try {
                const data = await this.request(`${this.endpoints.messages}/${this.selected.uuid}/messages`, {
                    method: 'POST',
                    body: JSON.stringify({ message: this.draft })
                });
                this.pushMessage(data.message);
                this.draft = '';
            } catch (error) {
                this.error = error?.errors?.message?.[0] || error?.message || 'Message failed.';
            } finally {
                this.sending = false;
            }
        },

        async searchPlayers() {
            this.searchError = '';
            if (this.playerSearch.trim().length < 2) {
                this.playerResults = [];
                return;
            }

            try {
                const query = encodeURIComponent(this.playerSearch.trim());
                const data = await this.request(`${this.endpoints.users}?search=${query}`);
                this.playerResults = data.users || [];
            } catch (error) {
                this.searchError = error?.message || 'Player search failed.';
            }
        },

        async openDirect(username = null) {
            const target = (username || this.playerSearch).trim();
            this.searchError = '';
            if (target.length === 0) return;

            try {
                const data = await this.request(this.endpoints.direct, {
                    method: 'POST',
                    body: JSON.stringify({ username: target })
                });
                await this.upsertAndSelect(data.conversation);
                this.playerSearch = '';
                this.playerResults = [];
                this.playerModalOpen = false;
            } catch (error) {
                this.searchError = error?.errors?.username?.[0] || error?.message || 'Could not open player chat.';
            }
        },

        async openTeam(uuid) {
            const data = await this.request(`${this.endpoints.team}/${uuid}`, { method: 'POST', body: '{}' });
            await this.upsertAndSelect(data.conversation);
        },

        requestJoinTeam(team) {
            this.pendingTeamJoin = team;
            this.teamJoinModalOpen = true;
        },

        async confirmJoinTeam() {
            if (!this.pendingTeamJoin) return;

            const data = await this.request(`${this.endpoints.team}/${this.pendingTeamJoin.uuid}/join`, {
                method: 'POST',
                body: '{}'
            });

            this.teamJoinModalOpen = false;
            this.pendingTeamJoin = null;
            await this.loadConversations();
            await this.upsertAndSelect(data.conversation);
        },

        async openPlayerProfile(uuid) {
            this.playerLoading = true;
            this.playerModalOpen = true;
            this.selectedPlayer = null;

            try {
                const data = await this.request(`${this.endpoints.players}/${uuid}`);
                this.selectedPlayer = data.player;
            } catch (error) {
                this.selectedPlayer = { error: error?.message || 'Could not load player.' };
            } finally {
                this.playerLoading = false;
            }
        },

        async followSelectedPlayer() {
            if (!this.selectedPlayer || this.selectedPlayer.is_self) return;

            const data = await this.request(`${this.endpoints.players}/${this.selectedPlayer.uuid}/follow`, {
                method: 'POST',
                body: '{}'
            });
            this.selectedPlayer = data.player;
        },

        async messageSelectedPlayer() {
            if (!this.selectedPlayer || this.selectedPlayer.is_self) return;
            await this.openDirect(this.selectedPlayer.username);
        },

        async upsertAndSelect(conversation) {
            const index = this.conversations.findIndex((item) => item.uuid === conversation.uuid);
            if (index >= 0) {
                this.conversations[index] = conversation;
            } else {
                this.conversations.unshift(conversation);
            }
            this.subscribe(conversation.uuid);
            await this.selectConversation(conversation);
        },

        scrollToBottom() {
            this.$nextTick(() => {
                if (!this.$refs.messagePane) return;
                this.$refs.messagePane.scrollTop = this.$refs.messagePane.scrollHeight;
                window.lucide?.createIcons();
            });
        }
    };
};

// Register this before DOMContentLoaded. Chrome may make a previously engaged
// visitor installable very early in the page lifecycle.
attachPublicPwaInstallEvents();

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons on first load
    refreshLucideIcons();

    initPublicShell();
    initPlayerShell();

    // Initialize Mobile Bottom Nav "More" Panel
    initMobileMorePanel();
});

document.addEventListener('livewire:navigated', () => {
    // Re-initialize icons after Livewire navigation
    refreshLucideIcons();

    initPublicShell();
    initPlayerShell();

    // Re-initialize mobile more panel after navigation
    initMobileMorePanel();
});

document.addEventListener('livewire:init', () => {
    // Livewire v3 replaces/morphs DOM after actions. Recreate icons only after
    // that commit finishes so newly-rendered buttons and toast icons are kept.
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            refreshLucideIcons();
        });
    });
});

document.addEventListener('livewire:navigate', () => {
    if (window.__playerShowNavigateLoader) {
        showPlayerPageLoader(window.__playerPendingNavigateUrl || null);
    }
});

document.addEventListener('livewire:navigated', () => {
    window.__playerShowNavigateLoader = false;
    window.__playerPendingNavigateUrl = null;
    rememberPlayerPage();
    hidePlayerPageLoader();
    clearPlayerDisabledButtons();
});

/**
 * REALTIME NOTIFICATIONS — subscribe after Livewire initialises so auth user uuid is available
 */
document.addEventListener('livewire:init', () => {
    const userUuid = document.querySelector('meta[name="user-uuid"]')?.getAttribute('content');
    const echo = window.ensurePlayerSaloonsEcho();

    if (!userUuid || !echo) return;

    echo.private(`user.${userUuid}`)
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
    initPublicNav();
    initHeroVideoFallback();

    refreshLucideIcons();
}

function initPlayerShell() {
    rememberPlayerPage();
    initPlayerSubmitButtons();
    initPlayerNavigationLoader();
    hidePlayerPageLoader();
}

/**
 * PUBLIC NAV — Transparent on hero, solid once scrolled
 *
 * On landing pages with a full-viewport hero video, the navbar
 * starts fully transparent and transitions to a solid dark background
 * (matching the esports theme) after the user scrolls past a threshold.
 */
function initPublicNav() {
    const nav = document.getElementById('public-nav');
    if (!nav) return;

    // Clean up any old scroll listener before re-binding
    if (nav._navScrollHandler) {
        window.removeEventListener('scroll', nav._navScrollHandler);
        nav._navScrollHandler = null;
        nav._navScrollInitialised = false;
    }

    // Only apply transparent behaviour when a hero section is present
    const hero = document.querySelector('.landing-hero');
    if (!hero) {
        // On non-landing pages always show solid nav
        nav.classList.remove('nav-transparent');
        nav.classList.add('nav-solid');
        return;
    }

    // Avoid re-binding scroll listener on same page
    if (nav._navScrollInitialised) return;
    nav._navScrollInitialised = true;

    const THRESHOLD = 60; // px before switching to solid

    function updateNav() {
        const scrolled = window.scrollY > THRESHOLD;
        nav.classList.toggle('nav-solid', scrolled);
        nav.classList.toggle('nav-transparent', !scrolled);
    }

    nav._navScrollHandler = updateNav;

    // Set initial state
    updateNav();

    window.addEventListener('scroll', updateNav, { passive: true });
}

/**
 * LANDING HERO VIDEO — Fallback loop listener
 *
 * Some browsers (especially on mobile iOS or when using SPA navigation)
 * will occasionally ignore the native HTML `loop` attribute. This guarantees
 * the hero video will always replay when it finishes.
 *
 * Three-layer approach:
 *   1. Ensure `loop` is set programmatically (guards against Livewire
 *      morphdom stripping the attribute during re-renders).
 *   2. `ended` event — standard replay trigger.
 *   3. `timeupdate` safety net — catches browsers that freeze instead of
 *      firing `ended` when a looped video reaches its duration.
 */
function initHeroVideoFallback() {
    const video = document.getElementById('hero-video');
    if (!video) return;

    // Layer 1: always enforce loop via property (survives attribute morphing)
    video.loop = true;

    // Avoid attaching multiple event listeners on the same element
    if (video._loopInitialised) return;
    video._loopInitialised = true;

    // Layer 2: explicit restart on ended
    video.addEventListener('ended', () => {
        video.currentTime = 0;
        video.play().catch(() => {
            // Ignore auto-play policy errors
        });
    });

    // Layer 3: timeupdate safety net — if the video stalls within 0.3 s of
    // its end without firing `ended`, force a restart manually.
    video.addEventListener('timeupdate', () => {
        if (video.duration && (video.duration - video.currentTime) < 0.3 && video.paused) {
            video.currentTime = 0;
            video.play().catch(() => {});
        }
    });
}


function initPlayerSubmitButtons() {
    document.querySelectorAll('.player-main-content form[wire\\:submit], .player-main-content form[wire\\:submit\\.prevent]').forEach(form => {
        if (form.dataset.playerSubmitInitialised) return;
        form.dataset.playerSubmitInitialised = 'true';

        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                disablePlayerButton(submitButton);
            }
        });
    });
}

function disablePlayerButton(button) {
    button.classList.add('ps-button-is-disabled');
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
}

function clearPlayerDisabledButtons() {
    document.querySelectorAll('.ps-button-is-disabled').forEach(button => {
        button.classList.remove('ps-button-is-disabled');
        button.removeAttribute('aria-busy');
        button.disabled = false;
    });
}

function initPlayerNavigationLoader() {
    document.querySelectorAll('a[wire\\:navigate]').forEach(link => {
        if (link.dataset.playerNavigateInitialised) return;
        link.dataset.playerNavigateInitialised = 'true';

        link.addEventListener('click', event => {
            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (link.closest('.player-tabs') || link.classList.contains('player-tab')) return;
            if (link.target && link.target !== '_self') return;
            if (link.hasAttribute('download')) return;

            const targetUrl = normalisePlayerUrl(link.href);
            if (!targetUrl || targetUrl === normalisePlayerUrl(window.location.href)) return;
            if (isPlayerPageCached(targetUrl)) return;

            window.__playerShowNavigateLoader = true;
            window.__playerPendingNavigateUrl = targetUrl;
            showPlayerPageLoader(targetUrl);
        });
    });
}

function normalisePlayerUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.origin !== window.location.origin) return null;
        return `${parsed.pathname}${parsed.search}`;
    } catch {
        return null;
    }
}

function playerPageCache() {
    try {
        return new Set(JSON.parse(sessionStorage.getItem('playerSaloonsVisitedPages') || '[]'));
    } catch {
        return new Set();
    }
}

function rememberPlayerPage() {
    const currentUrl = normalisePlayerUrl(window.location.href);
    if (!currentUrl) return;

    const cache = playerPageCache();
    cache.add(currentUrl);
    sessionStorage.setItem('playerSaloonsVisitedPages', JSON.stringify([...cache].slice(-40)));
}

function isPlayerPageCached(url) {
    return playerPageCache().has(url);
}

function pageLoaderType(url) {
    const path = url ? url.split('?')[0] : window.location.pathname;

    if (path === '/dashboard') return 'dashboard';
    if (path.startsWith('/wallet')) return 'wallet';
    if (path.startsWith('/profile')) return 'profile';
    if (path.startsWith('/head-to-head') || path.startsWith('/matches')) return 'match';
    if (path.startsWith('/tournaments') || path.startsWith('/my-tournaments')) return 'tournament';
    if (path.startsWith('/leaderboards')) return 'leaderboard';

    return 'default';
}

function showPlayerPageLoader(url = null) {
    const loader = document.getElementById('player-page-loader');
    if (!loader) return;

    loader.dataset.pageType = pageLoaderType(url);
    loader.classList.add('active');
    loader.setAttribute('aria-hidden', 'false');
}

function hidePlayerPageLoader() {
    const loader = document.getElementById('player-page-loader');
    if (loader) {
        loader.classList.remove('active');
        loader.setAttribute('aria-hidden', 'true');
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

            refreshLucideIcons();
        });
    });
}

function refreshLucideIcons() {
    if (!window.lucide) return;

    if (window.__lucideRefreshQueued) return;
    window.__lucideRefreshQueued = true;

    window.requestAnimationFrame(() => {
        window.__lucideRefreshQueued = false;
        window.lucide.createIcons();
    });
}

function attachPublicPwaInstallEvents() {
    if (window.__playerSaloonsPwaListenerAttached) return;

    window.__playerSaloonsPwaListenerAttached = true;

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        window.__playerSaloonsPwaPrompt = event;
        window.__playerSaloonsPwaJustInstalled = false;
        window.__playerSaloonsPwaSyncInstallButtons?.();
    });

    window.addEventListener('appinstalled', () => {
        window.__playerSaloonsPwaPrompt = null;
        window.__playerSaloonsPwaJustInstalled = true;
        window.__playerSaloonsPwaSyncInstallButtons?.();
    });

    window.addEventListener('resize', () => {
        window.__playerSaloonsPwaSyncInstallButtons?.();
    });
}

function initPublicPwaInstall() {
    if ('serviceWorker' in navigator && !window.__playerSaloonsServiceWorkerRegistered) {
        window.__playerSaloonsServiceWorkerRegistered = true;
        let refreshingForServiceWorker = false;
        let serviceWorkerRegistration = null;
        const notifyUpdateReady = () => {
            window.dispatchEvent(new CustomEvent('pwa-update-ready'));
            document.querySelectorAll('[data-pwa-update-prompt]').forEach(prompt => prompt.classList.remove('hidden'));
        };

        document.querySelectorAll('[data-pwa-update-prompt]').forEach(prompt => {
            if (prompt.dataset.bound === 'true') return;
            prompt.dataset.bound = 'true';
            prompt.querySelector('[data-pwa-update-now]')?.addEventListener('click', () => {
                window.dispatchEvent(new CustomEvent('pwa-apply-update'));
            });
            prompt.querySelector('[data-pwa-update-later]')?.addEventListener('click', () => {
                prompt.classList.add('hidden');
            });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (refreshingForServiceWorker) return;

            refreshingForServiceWorker = true;
            window.location.reload();
        });

        window.addEventListener('pwa-apply-update', () => {
            serviceWorkerRegistration?.waiting?.postMessage({ type: 'SKIP_WAITING' });
        });

        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    serviceWorkerRegistration = registration;

                    if (registration.waiting) notifyUpdateReady();

                    registration.addEventListener('updatefound', () => {
                        const installingWorker = registration.installing;
                        installingWorker?.addEventListener('statechange', () => {
                            if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                notifyUpdateReady();
                            }
                        });
                    });

                    return registration.update();
                })
                .catch(err => console.error('SW registration failed:', err));
        });
    }

    const isStandalone = () => {
        return window.navigator.standalone === true
            || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    };

    const isDesktop = () => {
        return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
    };

    const getInstallButtons = () => document.querySelectorAll('.pwa-install-btn');

    const hideButton = button => {
        button.classList.add('hidden');
        button.classList.remove('inline-flex');
    };

    const setButtonState = (button, state) => {
        const labels = {
            ready: 'Install',
            installed: 'Installed',
            manual: 'Install App',
        };
        const ariaLabels = {
            ready: 'Install PlayerSaloons app',
            installed: 'PlayerSaloons is installed. Show reinstall instructions',
            manual: 'Show PlayerSaloons install options',
        };

        button.dataset.pwaState = state;
        button.disabled = false;
        button.setAttribute('aria-label', ariaLabels[state]);
        button.setAttribute('title', ariaLabels[state]);
        button.setAttribute('aria-busy', String(window.__playerSaloonsPwaPrompting === true));
        button.querySelector('[data-pwa-install-label]')?.replaceChildren(labels[state]);
        button.querySelectorAll('[data-pwa-install-icon]').forEach(icon => {
            icon.classList.toggle('hidden', icon.dataset.pwaInstallIcon !== state);
        });
    };

    const showButton = (button, state) => {
        button.classList.remove('hidden');
        button.classList.add('inline-flex');
        setButtonState(button, state);
    };

    const syncInstallButtons = () => {
        const desktop = isDesktop();
        const state = isStandalone() || window.__playerSaloonsPwaJustInstalled
            ? 'installed'
            : (window.__playerSaloonsPwaPrompt ? 'ready' : 'manual');

        getInstallButtons().forEach(button => {
            const isDesktopButton = button.hasAttribute('data-pwa-install-desktop');
            const isMobileButton = button.hasAttribute('data-pwa-install-mobile');

            if ((desktop && isDesktopButton) || (!desktop && isMobileButton)) {
                showButton(button, state);
                return;
            }

            hideButton(button);
        });
    };

    // The global browser events must operate on the newest Livewire-rendered
    // navigation, rather than the button nodes that existed on first load.
    window.__playerSaloonsPwaSyncInstallButtons = syncInstallButtons;
    attachPublicPwaInstallEvents();

    const showInstallNotice = message => {
        let notice = document.getElementById('pwa-install-notice');
        if (!notice) {
            notice = document.createElement('div');
            notice.id = 'pwa-install-notice';
            notice.className = 'fixed inset-x-4 bottom-5 z-[100] mx-auto max-w-md rounded-2xl border border-cyan-300/30 bg-zinc-950/95 px-4 py-3 text-center text-xs font-semibold leading-relaxed text-zinc-100 shadow-[0_0_32px_rgba(34,211,238,0.25)] backdrop-blur-xl sm:bottom-8';
            notice.setAttribute('role', 'status');
            notice.setAttribute('aria-live', 'polite');
            document.body.append(notice);
        }

        notice.textContent = message;
        window.clearTimeout(window.__playerSaloonsPwaNoticeTimer);
        window.__playerSaloonsPwaNoticeTimer = window.setTimeout(() => notice.remove(), 7000);
    };

    const installHelpMessage = () => {
        const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (isStandalone() || window.__playerSaloonsPwaJustInstalled) {
            return 'PlayerSaloons is already installed. To reinstall it, remove the existing app from your device first, then return here and install again.';
        }

        if (isIos) {
            return 'To install on iPhone or iPad, tap Share, then Add to Home Screen. If it is already installed, open PlayerSaloons from your Home Screen.';
        }

        return 'Use your browser menu and choose Install app. If PlayerSaloons is already installed, open it from your desktop or app launcher.';
    };

    getInstallButtons().forEach(button => {
        if (button._pwaInstallInitialised) return;
        button._pwaInstallInitialised = true;

        button.addEventListener('click', async () => {
            if (window.__playerSaloonsPwaPrompting) return;

            const promptEvent = window.__playerSaloonsPwaPrompt;
            if (!promptEvent || isStandalone() || window.__playerSaloonsPwaJustInstalled) {
                showInstallNotice(installHelpMessage());
                return;
            }

            window.__playerSaloonsPwaPrompting = true;
            syncInstallButtons();

            try {
                promptEvent.prompt();
                const choice = await promptEvent.userChoice;
                window.__playerSaloonsPwaPrompt = null;

                if (choice.outcome === 'accepted') {
                    window.__playerSaloonsPwaJustInstalled = true;
                } else {
                    showInstallNotice('Install was dismissed. You can try again later from your browser menu.');
                }
            } catch (error) {
                console.warn('PWA install prompt failed:', error);
                window.__playerSaloonsPwaPrompt = null;
                showInstallNotice(installHelpMessage());
            } finally {
                window.__playerSaloonsPwaPrompting = false;
                syncInstallButtons();
            }
        });
    });

    syncInstallButtons();
}
