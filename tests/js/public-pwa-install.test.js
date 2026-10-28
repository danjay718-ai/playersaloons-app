import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const pwaSource = source.slice(source.indexOf('function attachPublicPwaInstallEvents()'));

function setup({ ios = false, readyState = 'complete' } = {}) {
    const elements = [];
    const listeners = {};
    const element = (tag = 'button') => {
        const classes = new Set();
        const node = {
            tag, dataset: {}, children: [], attributes: {}, listeners: {},
            classList: { add: (...names) => names.forEach(n => classes.add(n)), remove: (...names) => names.forEach(n => classes.delete(n)), toggle() {}, contains: name => classes.has(name) },
            setAttribute(name, value) { this.attributes[name] = value; },
            hasAttribute(name) { return name in this.attributes; },
            addEventListener(name, callback) { this.listeners[name] = callback; },
            append(...nodes) { this.children.push(...nodes); },
            replaceChildren(value) { this.textContent = value; },
            querySelector(selector) { return this.children.find(n => selector === '[data-pwa-install-message]' ? 'pwaInstallMessage' in n.dataset : n.tag === 'label'); },
            querySelectorAll() { return []; },
            showModal() { this.open = true; }, close() { this.open = false; },
        };
        elements.push(node);
        return node;
    };
    const desktop = element(); desktop.attributes['data-pwa-install-desktop'] = '';
    const mobile = element(); mobile.attributes['data-pwa-install-mobile'] = '';
    const combined = element(); combined.attributes['data-pwa-install-mobile'] = ''; combined.attributes['data-pwa-install-desktop'] = '';
    let registered = 0;
    const context = vm.createContext({
        console,
        interfaceText: text => text,
        document: {
            readyState,
            body: element('body'),
            addEventListener() {},
            querySelectorAll: selector => selector === '.pwa-install-btn' ? [desktop, mobile, combined] : [],
            getElementById: id => elements.find(n => n.id === id),
            createElement: element,
        },
        navigator: {
            userAgent: ios ? 'iPhone' : 'Android',
            serviceWorker: { addEventListener() {}, controller: {}, async register() { registered++; return { addEventListener() {}, update() {} }; } },
        },
        window: {
            navigator: {},
            matchMedia: () => ({ matches: false }),
            addEventListener: (name, cb) => { listeners[name] = cb; },
            location: { reload() {} },
        },
    });
    vm.runInContext(pwaSource + '\ninitPublicPwaInstall();', context);
    return { context, desktop, mobile, combined, listeners, elements, registered: () => registered };
}

test('mobile buttons remain available and iPhone instructions stay open until dismissed', async () => {
    const app = setup({ ios: true });
    assert.ok(app.desktop.classList.contains('hidden'));
    assert.ok(!app.mobile.classList.contains('hidden'));
    assert.ok(!app.combined.classList.contains('hidden'));
    await app.mobile.listeners.click();
    const dialog = app.elements.find(n => n.tag === 'dialog');
    assert.equal(dialog.open, true);
    assert.match(dialog.querySelector('[data-pwa-install-message]').textContent, /Share, then Add to Home Screen/);
    dialog.children.find(n => n.tag === 'button').listeners.click();
    assert.equal(dialog.open, false);
});

test('mobile native prompt rejection falls back to visible install instructions', async () => {
    const app = setup();
    app.listeners.beforeinstallprompt({ preventDefault() {}, async prompt() { throw new Error('Unavailable'); } });
    await app.mobile.listeners.click();
    assert.equal(app.context.window.__playerSaloonsPwaPrompting, false);
    assert.equal(app.context.window.__playerSaloonsPwaPrompt, null);
    assert.equal(app.elements.find(n => n.tag === 'dialog').open, true);
});

test('service worker registers even when initialization happens after the load event', async () => {
    const app = setup();
    await Promise.resolve();
    assert.equal(app.registered(), 1);
    vm.runInContext('initPublicPwaInstall();', app.context);
    assert.equal(app.registered(), 1);
    const loading = setup({ readyState: 'loading' });
    assert.equal(loading.registered(), 0);
    loading.listeners.load();
    assert.equal(loading.registered(), 1);
});

test('an accepted native prompt updates every mobile install button', async () => {
    const app = setup();
    let prompted = 0;
    app.listeners.beforeinstallprompt({ preventDefault() {}, async prompt() { prompted++; }, userChoice: Promise.resolve({ outcome: 'accepted' }) });
    assert.equal(app.mobile.dataset.pwaState, 'ready');
    await app.mobile.listeners.click();
    assert.equal(prompted, 1);
    assert.equal(app.mobile.dataset.pwaState, 'installed');
    assert.equal(app.combined.dataset.pwaState, 'installed');
});
