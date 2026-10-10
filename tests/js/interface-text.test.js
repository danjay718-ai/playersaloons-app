import assert from 'node:assert/strict';
import test from 'node:test';
import { interfaceText } from '../../resources/js/interface-text.js';

test('browser labels use the current page language and substitute values as text', () => {
    const script = { textContent: JSON.stringify({ Install: 'Installer', ':count viewers': ':count spectateurs' }) };
    const root = { querySelector() { return script; } };
    assert.equal(interfaceText('Install', {}, root), 'Installer');
    assert.equal(interfaceText(':count viewers', { count: 12 }, root), '12 spectateurs');
    script.textContent = JSON.stringify({ Install: 'Instalar' });
    assert.equal(interfaceText('Install', {}, root), 'Instalar');
    assert.equal(interfaceText('Untranslated label', {}, root), 'Untranslated label');
});

test('missing or malformed dictionaries keep the original label', () => {
    const root = { querySelector() { return { textContent: '{bad json' }; } };
    assert.equal(interfaceText('Install', {}, root), 'Install');
    assert.equal(interfaceText('Install', {}, { querySelector() { return null; } }), 'Install');
});
