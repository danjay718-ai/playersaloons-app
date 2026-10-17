import { serverClock } from './server-clock.js';

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
    const now = serverClock(serverNow);

    return {
        now: now(),
        clockTimer: null,

        init() {
            this.destroy();
            this.updateClock();
            this.clockTimer = setInterval(() => this.updateClock(), 1000);
        },

        updateClock() {
            this.now = now();
        },

        countdown(startAt, status) {
            return formatSlotCountdown(startAt, status, this.now, labels);
        },

        destroy() {
            clearInterval(this.clockTimer);
            this.clockTimer = null;
        },
    };
}

export function countdownTimer(targetEpoch, serverNow) {
    const currentTime = serverClock(serverNow);

    return {
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0,
        expired: false,
        interval: null,
        start() {
            this.destroy();
            this.tick();
            if (!this.expired) this.interval = setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = currentTime();
            const remaining = Math.max(0, Math.ceil((targetEpoch - now) / 1000));
            this.expired = remaining === 0;
            this.days = Math.floor(remaining / 86400);
            this.hours = Math.floor((remaining % 86400) / 3600);
            this.minutes = Math.floor((remaining % 3600) / 60);
            this.seconds = remaining % 60;
            if (this.expired) this.destroy();
        },
        destroy() {
            clearInterval(this.interval);
            this.interval = null;
        },
    };
}
