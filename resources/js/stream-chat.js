export function streamChat(wire, streamId, initialMessages = [], initialViewerCount = 0) {
    return {
        showChat: true,
        messages: [],
        viewerCount: initialViewerCount,
        heartbeatTimer: null,
        echo: null,
        deletedIds: new Set(),

        init() {
            this.mergeMessages(initialMessages);
            this.heartbeatTimer = setInterval(() => wire.heartbeat(), 30000);
            this.echo = window.ensurePlayerSaloonsEcho?.();
            this.echo?.channel(`stream.${streamId}`)
                .listen('.StreamMessageSent', (event) => this.mergeMessages([event.message]))
                .listen('.StreamMessageDeleted', (event) => this.removeMessage(event.messageId))
                .listen('.StreamViewerCountUpdated', (event) => { this.viewerCount = event.viewerCount; });
            this.$nextTick(() => this.scrollChat());
        },

        destroy() {
            clearInterval(this.heartbeatTimer);
            this.echo?.leave(`stream.${streamId}`);
        },

        mergeMessages(incoming) {
            const byId = new Map();
            for (const message of [...this.messages, ...(incoming || [])]) {
                if (message?.id == null || this.deletedIds.has(String(message.id))) continue;
                byId.set(String(message.id), message);
            }
            this.messages = [...byId.values()].sort((a, b) => Number(a.id) - Number(b.id)).slice(-60);
            this.$nextTick(() => this.scrollChat());
        },

        syncSnapshot(event) {
            if (Number(event.streamId) !== Number(streamId)) return;
            // Keep socket messages that arrived after the server took this snapshot.
            this.messages = this.messages.filter(message => Number(message.id) > Number(event.lastMessageId));
            this.mergeMessages(event.messages);
        },

        removeMessage(id) {
            this.deletedIds.add(String(id));
            this.messages = this.messages.filter(message => String(message.id) !== String(id));
        },

        onMessageDeleted(event) {
            if (Number(event.streamId) === Number(streamId)) this.removeMessage(event.messageId);
        },

        scrollChat() {
            const element = this.$refs.chatBox;
            if (element) element.scrollTop = element.scrollHeight;
        },

        formatViewers(count) {
            if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
            if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
            return String(count);
        },
    };
}
