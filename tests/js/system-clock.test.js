import assert from 'node:assert/strict';
import test from 'node:test';
import { systemClock } from '../../resources/js/system-clock.js';

for (const now of ['2026-10-13T15:20:00Z', '2026-12-13T16:20:00Z']) {
    test(`sidebar shows 17:20 in Amsterdam across DST: ${now}`, () => {
        const clock = systemClock({ now, timezone: 'Europe/Amsterdam' });
        assert.equal(clock.time, '17:20:00');
    });
}

test('midnight uses 00 rather than 24 or 12 AM', () => {
    const clock = systemClock({ now: '2026-10-13T22:00:00Z', timezone: 'Europe/Amsterdam' });
    assert.equal(clock.time, '00:00:00');
});
