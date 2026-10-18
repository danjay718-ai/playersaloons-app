import { interfaceText } from './interface-text.js';

export function chatConsole(config) {
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
        subscriptions: new Map(),
        initialized: false,
        destroyed: false,
        inboxHandler: null,
        focusHandler: null,
        readThrough: {},
        messageRequestVersion: 0,
        conversationRequestVersion: 0,
        selectedPlayer: null,
        playerModalOpen: false,
        playerLoading: false,
        activeFilterClass: 'rounded-lg border border-fuchsia-400/40 bg-fuchsia-600/20 px-3 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-fuchsia-100',
        idleFilterClass: 'rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2 font-orbitron text-[10px] font-black uppercase tracking-widest text-zinc-500 hover:text-zinc-200',

        async init() {
            if (!this.initialized) {
                this.initialized = true;
                this.inboxHandler = () => this.syncInbox();
                this.focusHandler = () => {
                    window.playerActiveChat = !document.hidden && document.hasFocus() && !this.loadingMessages ? this.selected?.uuid : null;
                    if (window.playerActiveChat) this.loadSelectedMessages();
                };
                window.addEventListener('chat-inbox-updated', this.inboxHandler);
                window.addEventListener('focus', this.focusHandler);
                window.addEventListener('blur', this.focusHandler);
                document.addEventListener('visibilitychange', this.focusHandler);
            }
            try {
                await this.loadConversations();
                this.connected = Boolean(window.ensurePlayerSaloonsEcho?.());
            } catch (error) {
                this.loadingMessages = false;
                this.bootError = error?.message || interfaceText('Chat could not sync. Refresh the page or try again.');
            }
        },

        async syncInbox() {
            try {
                await this.loadConversations();
                if (!document.hidden && document.hasFocus() && this.selected) await this.loadSelectedMessages();
            } catch { /* Existing messages remain available during reconnection. */ }
        },

        destroy() {
            this.destroyed = true;
            window.playerActiveChat = null;
            window.removeEventListener('chat-inbox-updated', this.inboxHandler);
            window.removeEventListener('focus', this.focusHandler);
            window.removeEventListener('blur', this.focusHandler);
            document.removeEventListener('visibilitychange', this.focusHandler);
            const echo = window.Echo;
            for (const [uuid, handler] of this.subscriptions) {
                echo?.private(`chat.${uuid}`).stopListening('.chat.message.sent', handler);
                echo?.leave(`chat.${uuid}`);
            }
            this.subscriptions.clear();
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
            const version = ++this.conversationRequestVersion;
            const data = await this.request(this.endpoints.conversations);
            if (this.destroyed || version !== this.conversationRequestVersion) return;
            this.conversations = data.conversations || [];
            this.unread = {};
            const allowed = new Set(this.conversations.map((item) => item.uuid));
            const echo = window.Echo;
            for (const [uuid, handler] of this.subscriptions) {
                if (!allowed.has(uuid)) {
                    echo?.private(`chat.${uuid}`).stopListening('.chat.message.sent', handler);
                    echo?.leave(`chat.${uuid}`);
                    this.subscriptions.delete(uuid);
                }
            }
            this.teams = data.teams || [];
            this.currentTeam = data.current_team || null;

            for (const conversation of this.conversations) {
                if (conversation.is_unread) {
                    this.unread[conversation.uuid] = true;
                }
                this.subscribe(conversation.uuid);
            }

            if (!this.selected && this.conversations.length > 0) {
                const requested = new URLSearchParams(window.location.search).get('conversation');
                const target = requested ? this.conversations.find((item) => item.uuid === requested) : this.conversations[0];
                if (!target) {
                    this.error = interfaceText('This conversation is not available.');
                    this.loadingMessages = false;
                    return;
                }
                await this.selectConversation(target);
                return;
            }

            if (this.selected) {
                this.selected = this.conversations.find((item) => item.uuid === this.selected.uuid) || null;
                if (!this.selected) window.playerActiveChat = null;
            }
            this.loadingMessages = false;
        },

        filteredConversations() {
            if (this.filter === 'all') return this.conversations;
            return this.conversations.filter((conversation) => conversation.type === this.filter);
        },

        async selectConversation(conversation) {
            this.selected = conversation;
            this.messages = [];
            this.error = '';
            window.playerActiveChat = null;
            await this.loadSelectedMessages();
        },

        async loadSelectedMessages() {
            const uuid = this.selected?.uuid;
            if (!uuid) return;
            this.loadingMessages = true;
            const version = ++this.messageRequestVersion;
            try {
                const data = await this.request(`${this.endpoints.messages}/${uuid}/messages`);
                if (this.destroyed || this.selected?.uuid !== uuid || version !== this.messageRequestVersion) return;
                const snapshot = data.messages || [];
                const latest = Math.max(0, ...snapshot.map((message) => Number(message.id)));
                this.messages = [...snapshot, ...this.messages.filter((message) => Number(message.id) > latest)]
                    .sort((a, b) => Number(a.id) - Number(b.id));
                Object.assign(this.selected, data.conversation);
                this.subscribe(uuid);
                this.scrollToBottom();
                this.loadingMessages = false;
                await this.$nextTick();
                if (this.destroyed || this.selected?.uuid !== uuid || version !== this.messageRequestVersion) return;
                window.playerActiveChat = !document.hidden && document.hasFocus() ? uuid : null;
                await this.acknowledgeCurrent();
            } catch (error) {
                if (this.selected?.uuid === uuid && version === this.messageRequestVersion) {
                    this.error = error?.message || interfaceText('Could not load messages.');
                    window.playerActiveChat = null;
                }
            } finally {
                if (this.selected?.uuid === uuid && version === this.messageRequestVersion) this.loadingMessages = false;
            }
        },

        async acknowledgeCurrent() {
            const uuid = this.selected?.uuid;
            if (!uuid || this.loadingMessages || document.hidden || !document.hasFocus()) return;
            const latest = Math.max(0, ...this.messages.map((message) => Number(message.id)));
            const read = Math.max(this.readThrough[uuid] || 0, this.selected.last_read_message_id || 0);
            if (latest <= read) return;
            this.readThrough[uuid] = latest;
            try {
                const data = await this.request(`${this.endpoints.messages}/${uuid}/read`, { method: 'POST', body: JSON.stringify({ message_id: latest }) });
                if (this.destroyed) return;
                if (this.selected?.uuid === uuid) this.selected.last_read_message_id = Math.max(latest, this.selected.last_read_message_id || 0);
                this.unread[uuid] = Boolean(data.conversation?.is_unread);
                const conversation = this.conversations.find((item) => item.uuid === uuid);
                if (conversation) conversation.is_unread = this.unread[uuid];
                window.dispatchEvent(new CustomEvent('chat-inbox-refresh'));
            } catch {
                if (this.readThrough[uuid] === latest) this.readThrough[uuid] = read;
            }
        },

        async toggleMute() {
            if (!this.selected) return;
            const uuid = this.selected.uuid;
            try {
                const data = await this.request(`${this.endpoints.messages}/${uuid}/mute`, { method: 'PUT', body: JSON.stringify({ muted: !this.selected.notifications_muted }) });
                if (this.selected?.uuid === uuid) Object.assign(this.selected, data.conversation);
                const conversation = this.conversations.find((item) => item.uuid === uuid);
                if (conversation) Object.assign(conversation, data.conversation);
                window.dispatchEvent(new CustomEvent('chat-inbox-refresh'));
            } catch (error) {
                this.error = error?.message || interfaceText('Could not update chat notifications.');
            }
        },

        subscribe(uuid) {
            if (this.subscriptions.has(uuid)) return;
            const echo = window.ensurePlayerSaloonsEcho?.();
            if (!echo) return;
            const handler = (event) => {
                if (this.destroyed) return;
                if (this.selected?.uuid === uuid) {
                    this.pushMessage(event.message);
                    return;
                }
                if (event.message.user?.uuid === this.currentUserUuid) return;
                this.unread[uuid] = true;
                const conversation = this.conversations.find((item) => item.uuid === uuid);
                if (conversation) conversation.is_unread = true;
            };
            echo.private(`chat.${uuid}`).listen('.chat.message.sent', handler);
            this.subscriptions.set(uuid, handler);
            this.connected = true;
        },

        pushMessage(message) {
            if (this.messages.some((existing) => existing.uuid === message.uuid)) return;
            this.messages.push(message);
            this.scrollToBottom();
            if (message.user?.uuid !== this.currentUserUuid) this.$nextTick(() => this.acknowledgeCurrent());
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
                this.error = error?.errors?.message?.[0] || error?.message || interfaceText('Message failed.');
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
                this.searchError = error?.message || interfaceText('Player search failed.');
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
                this.searchError = error?.errors?.username?.[0] || error?.message || interfaceText('Could not open player chat.');
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
                this.selectedPlayer = { error: error?.message || interfaceText('Could not load player.') };
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
}
