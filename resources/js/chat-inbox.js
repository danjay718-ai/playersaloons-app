export function chatInbox(config) {
    return {
        chatUnreadCount: config.initial?.unread_count || 0,
        chatAlert: null,
        inboxTimer: null,
        alertTimer: null,
        channel: null,
        handler: null,
        refreshHandler: null,
        focusHandler: null,
        reconnectHandler: null,
        lastEvents: new Map(),
        requestVersion: 0,
        destroyed: false,
        get chatUnreadLabel() { return this.chatUnreadCount > 99 ? '99+' : String(this.chatUnreadCount); },
        async init() {
            if (config.enabled === false) return;
            this.refreshHandler = () => this.refreshInbox();
            this.focusHandler = () => { if (!document.hidden) this.refreshInbox(); };
            window.addEventListener('chat-inbox-refresh', this.refreshHandler);
            window.addEventListener('focus', this.focusHandler);
            document.addEventListener('visibilitychange', this.focusHandler);
            this.inboxTimer = setInterval(this.focusHandler, 30000);
            const echo = window.ensurePlayerSaloonsEcho?.();
            if (echo) {
                this.channel = echo.private(`user.${config.userUuid}`);
                this.handler = (event) => this.refreshInbox(event);
                this.channel.listen('.chat.inbox.updated', this.handler);
                this.reconnectHandler = () => this.refreshInbox();
                echo.connector?.pusher?.connection?.bind('connected', this.reconnectHandler);
            }
            await this.refreshInbox();
        },
        async refreshInbox(event = null) {
            const version = ++this.requestVersion;
            try {
                const response = await fetch(config.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const summary = await response.json();
                if (this.destroyed || version !== this.requestVersion) return;
                this.chatUnreadCount = summary.unread_count;
                if (this.chatAlert && !summary.conversations.some((item) => item.uuid === this.chatAlert.uuid)) this.chatAlert = null;
                window.Livewire?.dispatch('notification.received');
                window.dispatchEvent(new CustomEvent('chat-inbox-updated', { detail: { event, summary } }));
                const conversation = summary.conversations.find((item) => item.uuid === event?.conversationUuid);
                if (!conversation || !event?.alert || !event.messageId || document.hidden || !document.hasFocus()) return;
                const seen = this.lastEvents.get(conversation.uuid) || 0;
                this.lastEvents.set(conversation.uuid, Math.max(seen, event.messageId));
                if (event.messageId <= seen || window.playerActiveChat === conversation.uuid) return;
                this.chatAlert = conversation;
                clearTimeout(this.alertTimer);
                this.alertTimer = setTimeout(() => { this.chatAlert = null; }, 6000);
            } catch { /* Reconnect, focus, and polling recover without blocking navigation. */ }
        },
        destroy() {
            this.destroyed = true;
            clearInterval(this.inboxTimer);
            clearTimeout(this.alertTimer);
            window.removeEventListener('chat-inbox-refresh', this.refreshHandler);
            window.removeEventListener('focus', this.focusHandler);
            document.removeEventListener('visibilitychange', this.focusHandler);
            if (this.handler) this.channel?.stopListening('.chat.inbox.updated', this.handler);
            const echo = window.Echo;
            if (this.reconnectHandler) echo?.connector?.pusher?.connection?.unbind('connected', this.reconnectHandler);
        },
    };
}
