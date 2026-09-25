export function formatSlotCountdown(startAt, status, now, labels) {
    if (status === 'ONGOING') return labels.ongoing;
    if (status === 'COMPLETED') return labels.completed;
    if (status === 'CANCELLED') return labels.cancelled;
    if (status === 'REFUNDED') return labels.refunded;
    if (startAt === null || !Number.isFinite(Number(startAt))) return labels.pending;

    const seconds = Math.ceil((Number(startAt) - now) / 1000);
    if (seconds <= 0) return labels.reached;

    const days = Math.floor(seconds / 86400);
    const hours = String(Math.floor((seconds % 86400) / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    const remainder = String(seconds % 60).padStart(2, '0');
    return `${labels.startsIn} ${days > 0 ? `${days}d ` : ''}${hours}h ${minutes}m ${remainder}s`;
}

export function tournamentCountdown(serverNow, labels) {
    const clockStartedAt = performance.now();

    return {
        now: serverNow,
        clockTimer: null,

        init() {
            this.updateClock();
            this.clockTimer = setInterval(() => this.updateClock(), 1000);
        },

        updateClock() {
            this.now = serverNow + performance.now() - clockStartedAt;
        },

        countdown(startAt, status) {
            return formatSlotCountdown(startAt, status, this.now, labels);
        },

        destroy() {
            clearInterval(this.clockTimer);
        },
    };
}
