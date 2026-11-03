import assert from 'node:assert/strict';
import test from 'node:test';
import { competitionPanel } from '../../resources/js/competition-panel.js';

function setup(t, compact = true) {
    globalThis.window = { matchMedia: () => ({ matches: compact }), scrollY: 400, scrollTo() {} };
    globalThis.document = { activeElement: { focus() {} } };
    t.after(() => { delete globalThis.window; delete globalThis.document; });
    return {
        ...competitionPanel(),
        activeTab: 'overview',
        $root: { querySelector: () => ({ textContent: ' Players (2) ', matches: () => false, close() {}, showModal() {}, show() {} }) },
        $nextTick: callback => callback(),
    };
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
