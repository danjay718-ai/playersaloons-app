import assert from 'node:assert/strict';
import test from 'node:test';
import { formatSlotCountdown, tournamentCountdown } from '../../resources/js/slot-picker.js';

const labels = {
    startsIn: 'Starts in', ongoing: 'In progress', completed: 'Completed',
    cancelled: 'Cancelled', refunded: 'Refunded', pending: 'Start time pending',
    reached: 'Start time reached',
};

test('equivalent timezone timestamps have the same countdown', () => {
    const now = Date.parse('2026-09-17T00:00:00Z');
    const manila = Date.parse('2026-09-18T10:03:04+08:00');
    const utc = Date.parse('2026-09-18T02:03:04Z');
    assert.equal(formatSlotCountdown(manila, 'PUBLISHED', now, labels), 'Starts in 1d 02h 03m 04s');
    assert.equal(formatSlotCountdown(manila, 'PUBLISHED', now, labels), formatSlotCountdown(utc, 'PUBLISHED', now, labels));
});

test('start boundary never shows negative time or prematurely reaches zero', () => {
    assert.equal(formatSlotCountdown(1001, 'PUBLISHED', 1000, labels), 'Starts in 00h 00m 01s');
    assert.equal(formatSlotCountdown(1000, 'PUBLISHED', 1000, labels), 'Start time reached');
    assert.equal(formatSlotCountdown(1000, 'PUBLISHED', 2000, labels), 'Start time reached');
    assert.equal(formatSlotCountdown(null, 'PUBLISHED', 1000, labels), 'Start time pending');
});

test('finished and ongoing slots show their actual status', () => {
    for (const [status, label] of [['ONGOING', 'In progress'], ['COMPLETED', 'Completed'], ['CANCELLED', 'Cancelled'], ['REFUNDED', 'Refunded']]) {
        assert.equal(formatSlotCountdown(99999, status, 1000, labels), label);
    }
});

test('card countdown starts immediately and is cleaned up on navigation', () => {
    const countdown = tournamentCountdown(1000, labels);
    countdown.init();
    assert.notEqual(countdown.clockTimer, null);
    assert.ok(countdown.now >= 1000);
    countdown.destroy();
});

test('list and detail countdowns wait five minutes independent of browser clock and timezone', async (t) => {
    const { countdownTimer } = await import('../../resources/js/slot-picker.js');
    const serverNow = Date.parse('2026-10-13T17:15:00+02:00');
    const target = Date.parse('2026-10-13T17:20:00+02:00');
    t.mock.method(Date, 'now', () => serverNow - 5 * 3600000);
    t.mock.method(globalThis.performance, 'now', () => 1000);
    const detail = countdownTimer(target, serverNow);
    detail.start();
    assert.deepEqual([detail.days, detail.hours, detail.minutes, detail.seconds], [0, 0, 5, 0]);
    assert.equal(formatSlotCountdown(target, 'REGISTRATION_OPEN', serverNow, labels), 'Starts in 00h 05m 00s');
    t.mock.method(globalThis.performance, 'now', () => 301000);
    detail.tick();
    assert.equal(detail.expired, true);
    assert.equal(detail.interval, null);
    assert.deepEqual([detail.hours, detail.minutes, detail.seconds], [0, 0, 0]);
});
