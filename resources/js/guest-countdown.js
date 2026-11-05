import { serverClock } from './server-clock.js';

export function guestCountdown(iso, labels, serverNow) {
    const now = serverClock(serverNow);
    return {
        now: now(), timer: null,
        init() { this.destroy(); this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
        tick() { this.now = now(); },
        destroy() { clearInterval(this.timer); this.timer = null; },
        get seconds() { return Math.max(0, Math.ceil((Date.parse(iso) - this.now) / 1000)); },
        get parts() {
            const s = this.seconds;
            const pad = (n) => String(n).padStart(2, '0');
            return [
                { label: labels.d, value: pad(Math.floor(s / 86400)) },
                { label: labels.h, value: pad(Math.floor(s / 3600) % 24) },
                { label: labels.m, value: pad(Math.floor(s / 60) % 60) },
                { label: labels.s, value: pad(s % 60) },
            ];
        },
        get text() {
            if (!this.seconds) return labels.started;
            const [d, h, m, s] = this.parts;
            return `${Number(d.value)}${labels.d} ${h.value}${labels.h} ${m.value}${labels.m} ${s.value}${labels.s}`;
        },
    };
}
