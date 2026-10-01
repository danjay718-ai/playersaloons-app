export function tournamentListRealtime(refresh, { poll = false } = {}) {
    return {
        listChannel: null,
        listRefreshTimer: null,
        listPollTimer: null,
        listRefreshing: false,
        listRefreshPending: false,
        listListener: null,
        listDestroyed: false,

        init() {
            const echo = window.ensurePlayerSaloonsEcho?.();
            if (echo) {
                this.listChannel = echo.channel('tournaments');
                this.listListener = () => this.queueListRefresh();
                this.listChannel.listen('.tournament.updated', this.listListener);
            }
            if (poll) {
                this.listPollTimer = setInterval(() => {
                    if (document.visibilityState === 'visible') this.queueListRefresh();
                }, 10000);
            }
        },

        queueListRefresh() {
            // Coalesce the multiple lifecycle events emitted when a bracket starts.
            if (this.listDestroyed || this.listRefreshTimer !== null) return;
            this.listRefreshTimer = setTimeout(async () => {
                this.listRefreshTimer = null;
                if (this.listRefreshing) {
                    this.listRefreshPending = true;
                    return;
                }
                this.listRefreshing = true;
                try {
                    await refresh();
                } catch {
                    // Polling retries when the connection recovers.
                } finally {
                    this.listRefreshing = false;
                    if (this.listRefreshPending) {
                        this.listRefreshPending = false;
                        this.queueListRefresh();
                    }
                }
            }, 150);
        },

        destroy() {
            this.listDestroyed = true;
            clearTimeout(this.listRefreshTimer);
            clearInterval(this.listPollTimer);
            // Stop this listener only: another component can share the channel.
            this.listChannel?.stopListening('.tournament.updated', this.listListener);
        },
    };
}

export async function refreshAdminHeadToHead() {
    const url = window.location.href;
    const response = await fetch(url, {
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok || response.redirected) return;
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (window.location.href !== url) return;
    for (const id of ['h2h-status-cards', 'h2h-schedules']) {
        const current = document.getElementById(id);
        const updated = page.getElementById(id);
        if (current && updated) window.Alpine.morph(current, updated.outerHTML);
    }
    window.lucide?.createIcons();
}
