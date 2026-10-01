import assert from 'node:assert/strict';
import test from 'node:test';
import { tournamentListRealtime, refreshAdminHeadToHead } from '../../resources/js/tournament-list-realtime.js';

function setup(t, { realtime = true } = {}) {
    const listeners = new Map();
    const channel = {
        listen(event, callback) { listeners.set(callback, event); return this; },
        stopListening(event, callback) { listeners.delete(callback); },
    };
    const timeouts = new Map();
    const intervals = new Map();
    let sequence = 0;
    t.mock.method(globalThis, 'setTimeout', (callback) => { const id = ++sequence; timeouts.set(id, callback); return id; });
    t.mock.method(globalThis, 'clearTimeout', (id) => timeouts.delete(id));
    t.mock.method(globalThis, 'setInterval', (callback) => { const id = ++sequence; intervals.set(id, callback); return id; });
    t.mock.method(globalThis, 'clearInterval', (id) => intervals.delete(id));
    globalThis.window = { ensurePlayerSaloonsEcho: () => realtime ? { channel: () => channel } : null };
    globalThis.document = { visibilityState: 'visible' };
    t.after(() => { delete globalThis.window; delete globalThis.document; });
    return {
        listeners, intervals, timeouts,
        async flush() {
            for (const [id, callback] of [...timeouts]) {
                timeouts.delete(id);
                await callback();
            }
        },
    };
}

test('cancellation and bracket lifecycle events refresh open lists without navigation', async (t) => {
    const harness = setup(t);
    let refreshed = 0;
    const list = tournamentListRealtime(async () => { refreshed++; });
    list.init();
    for (const callback of harness.listeners.keys()) {
        callback({ change: 'TournamentCancelled' });
        callback({ change: 'TournamentBracketGenerated' });
        callback({ change: 'TournamentStarted' });
    }
    await harness.flush();
    assert.equal(refreshed, 1);
    list.destroy();
});

test('admin H2H polling refreshes visible lists when realtime is unavailable', async (t) => {
    const harness = setup(t, { realtime: false });
    let refreshed = 0;
    const list = tournamentListRealtime(async () => { refreshed++; }, { poll: true });
    list.init();
    for (const tick of harness.intervals.values()) tick();
    await harness.flush();
    assert.equal(refreshed, 1);
    document.visibilityState = 'hidden';
    for (const tick of harness.intervals.values()) tick();
    await harness.flush();
    assert.equal(refreshed, 1);
    list.destroy();
    assert.equal(harness.intervals.size, 0);
});

test('leaving a list removes its listener while other open components keep receiving changes', (t) => {
    const harness = setup(t);
    const first = tournamentListRealtime(async () => {});
    const second = tournamentListRealtime(async () => {});
    first.init();
    second.init();
    first.destroy();
    assert.equal(harness.listeners.size, 1);
    first.queueListRefresh();
    assert.equal(harness.timeouts.size, 0);
    second.destroy();
});

test('admin H2H updates counts and schedules while keeping filter controls intact', async (t) => {
    setup(t);
    const updated = [];
    window.location = { href: 'https://example.test/admin/head-to-head?tab=daily' };
    window.Alpine = { morph(current, html) { updated.push([current.id, html]); } };
    document.getElementById = (id) => ({ id });
    globalThis.DOMParser = class {
        parseFromString() { return { getElementById: (id) => ({ outerHTML: `<div id="${id}">Updated</div>` }) }; }
    };
    t.after(() => { delete globalThis.DOMParser; });
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, redirected: false, text: async () => '<html></html>' }));
    await refreshAdminHeadToHead();
    assert.deepEqual(updated.map(([id]) => id), ['h2h-status-cards', 'h2h-schedules']);
});
