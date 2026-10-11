import assert from 'node:assert/strict';
import test from 'node:test';
import { streamChat } from '../../resources/js/stream-chat.js';

function chat(id = 1) {
    const state = streamChat({}, id);
    state.$nextTick = () => {};
    return state;
}

test('socket delivery and the send response display a message once across repeated sends', () => {
    const state = chat();
    state.mergeMessages([{ id: 1, message: 'hello' }]);
    state.syncSnapshot({ streamId: 1, lastMessageId: 1, messages: [{ id: '1', message: 'hello' }] });
    state.mergeMessages([{ id: 2, message: 'again' }]);
    state.syncSnapshot({ streamId: 1, lastMessageId: 2, messages: [{ id: 1, message: 'hello' }, { id: 2, message: 'again' }] });
    state.mergeMessages([{ id: '2', message: 'again' }]);
    assert.deepEqual(state.messages.map(message => message.message), ['hello', 'again']);
});

test('a stale send response preserves newer messages from other viewers', () => {
    const state = chat();
    state.mergeMessages([{ id: 11, message: 'Admin message' }]);
    state.syncSnapshot({ streamId: 1, lastMessageId: 10, messages: [{ id: 10, message: 'Player message' }] });
    assert.deepEqual(state.messages.map(message => message.id), [10, 11]);
});

test('snapshots recover missed broadcasts and remove deleted messages', () => {
    const state = chat();
    state.mergeMessages([{ id: 1 }, { id: 2 }]);
    state.syncSnapshot({ streamId: 1, lastMessageId: 3, messages: [{ id: 1 }, { id: 3 }] });
    assert.deepEqual(state.messages.map(message => message.id), [1, 3]);
    state.removeMessage('3');
    state.mergeMessages([{ id: 3 }]);
    assert.deepEqual(state.messages.map(message => message.id), [1]);
});

test('chat updates stay within their stream and retain the latest sixty messages in order', () => {
    const state = chat();
    state.mergeMessages(Array.from({ length: 75 }, (_, index) => ({ id: 75 - index })));
    state.syncSnapshot({ streamId: 2, lastMessageId: 100, messages: [{ id: 100 }] });
    state.onMessageDeleted({ streamId: 2, messageId: 75 });
    assert.equal(state.messages.length, 60);
    assert.equal(state.messages[0].id, 16);
    assert.equal(state.messages.at(-1).id, 75);
});
