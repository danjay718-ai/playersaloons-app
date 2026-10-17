import assert from 'node:assert/strict';
import test from 'node:test';
import { serverClock } from '../../resources/js/server-clock.js';
import { systemClock } from '../../resources/js/system-clock.js';
import { countdownTimer, tournamentCountdown } from '../../resources/js/slot-picker.js';

const labels = { startsIn: 'Starts in', reached: 'Start time reached' };

function pageClock(t) {
    globalThis.document = {};
    t.after(() => { delete globalThis.document; });
    let elapsed = 0;
    t.mock.method(performance, 'now', () => elapsed);
    // The user's device clock must not affect server-based countdowns.
    t.mock.method(Date, 'now', () => Date.parse('2030-01-01T00:00:00Z'));
    return { advance(milliseconds) { elapsed += milliseconds; } };
}

for (const now of ['2026-10-17T17:15:00+02:00', '2026-12-17T17:15:00+01:00']) {
    test(`delayed H2H and tournament lists match the sidebar across DST: ${now}`, (t) => {
        const page = pageClock(t);
        const epoch = Date.parse(now);
        const target = epoch + 5 * 60000;
        const sidebar = systemClock({ now, timezone: 'Europe/Amsterdam' });
        page.advance(90000);
        const h2h = tournamentCountdown(epoch, labels);
        page.advance(90000);
        const tournament = tournamentCountdown(epoch, labels);
        const detail = countdownTimer(target, epoch);

        sidebar.tick();
        h2h.updateClock();
        tournament.updateClock();
        detail.tick();
        assert.equal(sidebar.time, '17:18:00');
        assert.equal(h2h.now, sidebar.currentEpoch);
        assert.equal(tournament.now, sidebar.currentEpoch);
        assert.equal(h2h.countdown(target, 'REGISTRATION_OPEN'), 'Starts in 00h 02m 00s');
        assert.equal(tournament.countdown(target, 'REGISTRATION_OPEN'), h2h.countdown(target, 'REGISTRATION_OPEN'));
        assert.deepEqual([detail.minutes, detail.seconds], [2, 0]);
    });
}

test('fresh response resynchronizes existing sidebar and cards after device sleep', (t) => {
    const page = pageClock(t);
    const epoch = Date.parse('2026-10-17T17:15:00+02:00');
    const sidebar = systemClock({ now: new Date(epoch).toISOString(), timezone: 'Europe/Amsterdam' });
    const card = tournamentCountdown(epoch, labels);
    page.advance(1000);
    const refreshed = tournamentCountdown(epoch + 120000, labels);
    sidebar.tick();
    card.updateClock();
    assert.equal(sidebar.time, '17:17:00');
    assert.equal(card.now, refreshed.now);
    assert.equal(card.now, sidebar.currentEpoch);
    // An older response that arrives later must not rewind any of the clocks.
    page.advance(1000);
    assert.equal(serverClock(epoch)(), epoch + 121000);
});

test('a sidebar initialized after the cards uses their elapsed time', (t) => {
    const page = pageClock(t);
    const now = '2026-10-17T23:59:30+02:00';
    const card = tournamentCountdown(Date.parse(now), labels);
    page.advance(60000);
    const sidebar = systemClock({ now, timezone: 'Europe/Amsterdam' });
    card.updateClock();
    assert.equal(sidebar.time, '00:00:30');
    assert.equal(card.now, sidebar.currentEpoch);
});

test('restoring a two-hour-old list snapshot does not add two hours to its countdown', (t) => {
    const page = pageClock(t);
    const now = '2026-10-17T13:15:00+02:00';
    const epoch = Date.parse(now);
    const sidebar = systemClock({ now, timezone: 'Europe/Amsterdam' });
    page.advance(2 * 3600000);
    const card = tournamentCountdown(epoch, labels);
    sidebar.tick();
    assert.equal(sidebar.time, '15:15:00');
    assert.equal(card.now, sidebar.currentEpoch);
    assert.equal(card.countdown(Date.parse('2026-10-17T16:00:00+02:00'), 'REGISTRATION_OPEN'), 'Starts in 00h 45m 00s');
});

test('a cached sidebar timestamp cannot fall two hours behind a fresh H2H list', (t) => {
    pageClock(t);
    const epoch = Date.parse('2026-10-17T15:15:00+02:00');
    const card = tournamentCountdown(epoch, labels);
    const sidebar = systemClock({ now: '2026-10-17T13:15:00+02:00', timezone: 'Europe/Amsterdam' });
    assert.equal(sidebar.time, '15:15:00');
    assert.equal(sidebar.currentEpoch, card.now);
});
