export function systemClock(config) {
    const serverEpoch = Date.parse(config.now);
    const browserEpoch = Date.now();
    const clockStartedAt = performance.now();

    return {
        timezone: config.timezone,
        currentEpoch: Number.isNaN(serverEpoch) ? browserEpoch : serverEpoch,
        timer: null,
        init() {
            this.tick();
            this.timer = window.setInterval(() => this.tick(), 1000);
        },
        tick() {
            this.currentEpoch = (Number.isNaN(serverEpoch) ? browserEpoch : serverEpoch) + (performance.now() - clockStartedAt);
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
        },
    };
}
