const pageClocks = new WeakMap();

// Sidebars and countdowns on the same page must advance from one server clock.
// A component initialized later must not restart an older render timestamp.
export function serverClock(serverNow) {
    const page = typeof document === 'undefined' ? null : document;
    const hasServerTime = Number.isFinite(serverNow);
    const startedAt = performance.now();
    let clock = page ? pageClocks.get(page) : null;

    if (!clock) {
        clock = {
            epoch: hasServerTime ? serverNow : Date.now(),
            startedAt,
            serverBased: hasServerTime,
        };
        if (page) pageClocks.set(page, clock);
    } else if (hasServerTime && (!clock.serverBased || serverNow > clock.epoch + startedAt - clock.startedAt)) {
        // Fresh list responses also recover elapsed time after device sleep.
        clock.epoch = serverNow;
        clock.startedAt = startedAt;
        clock.serverBased = true;
    }

    return () => clock.epoch + performance.now() - clock.startedAt;
}
