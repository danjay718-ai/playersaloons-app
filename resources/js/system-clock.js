import { serverClock } from './server-clock.js';

export function systemClock(config) {
    const now = serverClock(Date.parse(config.now));

    return {
        timezone: config.timezone,
        currentEpoch: now(),
        timer: null,
        init() {
            this.destroy();
            this.tick();
            this.timer = window.setInterval(() => this.tick(), 1000);
        },
        tick() {
            this.currentEpoch = now();
        },
        get time() {
            return new Intl.DateTimeFormat(undefined, {
                timeZone: this.timezone,
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
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
            this.timer = null;
        },
    };
}
