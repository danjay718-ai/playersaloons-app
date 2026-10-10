import assert from 'node:assert/strict';
import test from 'node:test';
import { translationImportUpload } from '../../resources/js/translation-import-upload.js';

test('drop zone selects one file and triggers the upload input change event', t => {
    const original = globalThis.DataTransfer;
    globalThis.DataTransfer = class {
        files = [];
        items = { add: file => this.files.push(file) };
    };
    t.after(() => {
        if (original === undefined) delete globalThis.DataTransfer;
        else globalThis.DataTransfer = original;
    });
    const chosen = { name: 'french.json' };
    const state = translationImportUpload();
    state.dragging = true;
    const input = { dispatchEvent(event) { this.event = event; } };
    state.dropFile({ dataTransfer: { files: [chosen, { name: 'ignored.json' }] } }, input);
    assert.deepEqual(input.files, [chosen]);
    assert.equal(input.event.type, 'change');
    assert.equal(input.event.bubbles, true);
    assert.equal(state.dragging, false);
});

test('an empty drop does not start an upload', () => {
    const state = translationImportUpload();
    const input = { dispatchEvent() { throw new Error('Unexpected upload'); } };
    state.dropFile({ dataTransfer: { files: [] } }, input);
    assert.equal(state.uploading, false);
});
