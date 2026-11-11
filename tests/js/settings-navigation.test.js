import assert from 'node:assert/strict';
import test from 'node:test';
import { settingsNavigation } from '../../resources/js/settings-navigation.js';

test('settings deep links, navigation and browser history preserve the selected panel', () => {
    const original = globalThis.window;
    const visited = [];
    globalThis.window = {
        location: { href: 'https://admin.example/admin/system-settings?section=payments&other=kept' },
        history: { pushState(_state, _title, url) { visited.push(url.href); window.location.href = url.href; } },
    };
    try {
        const state = settingsNavigation(['general', 'payments', 'security']);
        state.init();
        assert.equal(state.section, 'payments');
        state.select('security');
        assert.equal(state.section, 'security');
        assert.equal(new URL(visited[0]).searchParams.get('other'), 'kept');
        state.select('security');
        state.select('unknown');
        assert.equal(visited.length, 1);
        window.location.href = 'https://admin.example/admin/system-settings?section=payments';
        state.restore();
        assert.equal(state.section, 'payments');
        window.location.href = 'https://admin.example/admin/system-settings?section=maintenance';
        state.restore();
        assert.equal(state.section, 'general');
    } finally {
        globalThis.window = original;
    }
});
