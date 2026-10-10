import assert from 'node:assert/strict';
import test from 'node:test';
import { translationLanguageVisibility } from '../../resources/js/translation-language-visibility.js';

function storage() {
    const data = new Map();
    return { getItem(key) { return data.get(key) ?? null; }, setItem(key, value) { data.set(key, value); } };
}

test('visibility changes are local and persist without calling Livewire', () => {
    const local = storage();
    const state = translationLanguageVisibility(1, ['en', 'fr', 'es'], local);
    state.$wire = new Proxy({}, { get() { throw new Error('Visibility must not contact Livewire'); } });
    state.toggleLanguage('fr');
    state.toggleLanguage('es');
    assert.deepEqual(state.hiddenLanguages, ['fr', 'es']);
    assert.deepEqual(translationLanguageVisibility(1, ['en', 'fr', 'es'], local).hiddenLanguages, ['fr', 'es']);
    assert.deepEqual(translationLanguageVisibility(2, ['en', 'fr', 'es'], local).hiddenLanguages, []);
    state.toggleLanguage('fr');
    assert.deepEqual(state.hiddenLanguages, ['es']);
    state.showAllLanguages();
    assert.deepEqual(state.hiddenLanguages, []);
});

test('English remains visible, unsupported preferences are ignored, and added languages can be toggled', () => {
    const local = storage();
    local.setItem('translation-hidden-languages-1', JSON.stringify(['en', 'fr', 'unknown']));
    const state = translationLanguageVisibility(1, ['en', 'fr'], local);
    assert.deepEqual(state.hiddenLanguages, ['fr']);
    state.toggleLanguage('en');
    state.toggleLanguage('unknown');
    assert.deepEqual(state.hiddenLanguages, ['fr']);
    state.supported.push('fil');
    state.toggleLanguage('fil');
    assert.deepEqual(state.hiddenLanguages, ['fr', 'fil']);
});

test('visibility controls tolerate unavailable storage', () => {
    const unavailable = { getItem() { throw new Error('Unavailable'); }, setItem() { throw new Error('Unavailable'); } };
    const state = translationLanguageVisibility(1, ['en', 'fr'], unavailable);
    state.toggleLanguage('fr');
    assert.deepEqual(state.hiddenLanguages, ['fr']);
    state.showAllLanguages();
    assert.deepEqual(state.hiddenLanguages, []);
});
