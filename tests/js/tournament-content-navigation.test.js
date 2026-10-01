import assert from 'node:assert/strict';
import test from 'node:test';
import { scrollToTournamentContent, scrollToMatchContent } from '../../resources/js/tournament-content-navigation.js';

function setup(t, { reducedMotion = false } = {}) {
    const frames = [];
    globalThis.requestAnimationFrame = (callback) => frames.push(callback);
    globalThis.window = { matchMedia: () => ({ matches: reducedMotion }) };
    t.after(() => { delete globalThis.requestAnimationFrame; delete globalThis.window; });
    return { frame() { frames.splice(0).forEach(callback => callback()); } };
}

function panel() {
    return {
        isConnected: true,
        visible: true,
        scrolls: [],
        getClientRects() { return this.visible ? [{}] : []; },
        scrollIntoView(options) { this.scrolls.push(options); },
        querySelector() { return null; },
    };
}

test('opening results waits for the new content to paint before scrolling', (t) => {
    const clock = setup(t);
    const result = panel();
    const submit = panel();
    result.querySelector = selector => selector === '[data-match-panel="submit"]' ? submit : null;
    const root = { querySelector(selector) { assert.equal(selector, '[data-tournament-content="submit-results"]'); return result; } };
    submit.visible = false;
    scrollToTournamentContent(root, 'submit-results');
    assert.equal(submit.scrolls.length, 0);
    clock.frame();
    submit.visible = true;
    clock.frame();
    assert.deepEqual(submit.scrolls, [{ behavior: 'smooth', block: 'start' }]);
    assert.equal(result.scrolls.length, 0);
});

test('fixtures and bracket controls scroll to the selected content rather than the tabs', (t) => {
    const clock = setup(t);
    const bracket = panel();
    const fixtures = panel();
    const root = { querySelector: selector => selector === '[data-tournament-content="matches-bracket"]' ? bracket : fixtures };
    scrollToTournamentContent(root, 'matches', 'bracket');
    clock.frame(); clock.frame();
    assert.equal(bracket.scrolls.length, 1);
    assert.equal(fixtures.scrolls.length, 0);
    scrollToTournamentContent(root, 'matches', 'fixtures');
    clock.frame(); clock.frame();
    assert.equal(fixtures.scrolls.length, 1);
});

test('review match dispute scrolls to the actual dispute rather than the matchup', (t) => {
    const clock = setup(t);
    const result = panel();
    const dispute = panel();
    result.querySelector = selector => selector === '[data-match-content="dispute"]' ? dispute : null;
    scrollToTournamentContent({ querySelector: () => result }, 'submit-results', 'bracket', 'dispute');
    clock.frame(); clock.frame();
    assert.equal(dispute.scrolls.length, 1);
    assert.equal(result.scrolls.length, 0);
});

test('players, overview, activity, and streams each scroll to their own panel', (t) => {
    const clock = setup(t);
    for (const tab of ['participants', 'overview', 'activity', 'streams', 'team-lobby']) {
        const content = panel();
        const root = { querySelector(selector) { assert.equal(selector, `[data-tournament-content="${tab}"]`); return content; } };
        scrollToTournamentContent(root, tab);
        clock.frame(); clock.frame();
        assert.equal(content.scrolls.length, 1);
    }
});

test('result history tabs respect reduced motion and skip hidden or removed content', (t) => {
    const clock = setup(t, { reducedMotion: true });
    const submissions = panel();
    const root = { querySelector(selector) { assert.equal(selector, '[data-match-panel="submissions"]'); return submissions; } };
    scrollToMatchContent(root, 'submissions');
    clock.frame(); clock.frame();
    assert.deepEqual(submissions.scrolls, [{ behavior: 'auto', block: 'start' }]);
    submissions.isConnected = false;
    scrollToMatchContent(root, 'submissions');
    clock.frame(); clock.frame();
    assert.equal(submissions.scrolls.length, 1);
    submissions.isConnected = true;
    submissions.visible = false;
    scrollToMatchContent(root, 'submissions');
    clock.frame(); clock.frame();
    assert.equal(submissions.scrolls.length, 1);
});
