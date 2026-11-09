import assert from 'node:assert/strict';
import test from 'node:test';
import { competitionPanel } from '../../resources/js/competition-panel.js';

function setup(t, compact = true) {
    globalThis.window = { matchMedia: () => ({ matches: compact }), scrollY: 400, scrollTo() {} };
    globalThis.document = { activeElement: { focus() {} } };
    t.after(() => { delete globalThis.window; delete globalThis.document; });
    return Object.assign(competitionPanel(), {
        activeTab: 'overview',
        $root: { querySelector: () => ({ textContent: ' Players (2) ', matches: () => false, close() {}, showModal() {}, show() {} }) },
        $nextTick: callback => callback(),
    });
}

test('mobile sections open with a title and return to the original view on close', (t) => {
    const panel = setup(t);
    panel.openPanel('participants');
    panel.activeTab = 'participants';
    assert.equal(panel.panelOpen, true);
    assert.equal(panel.panelTitle, 'Players (2)');
    assert.equal(panel.returnScrollY, 400);
    panel.openPanel('activity');
    panel.activeTab = 'activity';
    panel.closePanel();
    assert.equal(panel.panelOpen, false);
    assert.equal(panel.activeTab, 'overview');
    panel.closePanel();
    assert.equal(panel.activeTab, 'overview');
});

test('desktop tabs stay inline and resizing an open panel preserves selected content', (t) => {
    const panel = setup(t, false);
    panel.openPanel('participants');
    assert.equal(panel.panelOpen, false);
    panel.compactView = true;
    panel.openPanel('participants');
    panel.activeTab = 'participants';
    panel.resizePanel();
    assert.equal(panel.compactView, false);
    assert.equal(panel.panelOpen, false);
    assert.equal(panel.activeTab, 'participants');
});

test('mobile defaults use overview or results without replacing explicit links', (t) => {
    const panel = setup(t);
    panel.activeTab = 'matches';
    panel.setDefaultTab('overview', false);
    assert.equal(panel.selectedTab, 'overview');
    assert.equal(panel.activeTab, 'matches');
    panel.setDefaultTab('submit-results', false);
    assert.equal(panel.selectedTab, 'submit-results');
    assert.equal(panel.activeTab, 'matches');
    assert.equal(panel.panelOpen, false);
    panel.activeTab = 'matches';
    panel.setDefaultTab('overview', true);
    assert.equal(panel.selectedTab, 'matches');
});

test('results stay inline even when selected from an open mobile modal', (t) => {
    const panel = setup(t);
    let modal = false;
    const dialog = {
        matches: () => modal,
        close() { modal = false; },
        showModal() { modal = true; },
        show() { modal = false; },
    };
    panel.$root.querySelector = selector => selector === '.competition-panel' ? dialog : { textContent: 'Bracket' };
    panel.openPanel('submit-results');
    assert.equal(panel.panelOpen, false);
    assert.equal(modal, false);
    panel.openPanel('matches');
    assert.equal(modal, true);
    panel.openPanel('submit-results');
    assert.equal(panel.panelOpen, false);
    assert.equal(modal, false);
});

test('desktop keeps its existing default tab', (t) => {
    const panel = setup(t, false);
    panel.activeTab = 'matches';
    panel.setDefaultTab('overview', false);
    assert.equal(panel.activeTab, 'matches');
});
