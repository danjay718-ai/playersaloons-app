import assert from 'node:assert/strict';
import test from 'node:test';
import { chatInbox } from '../../resources/js/chat-inbox.js';
import { chatConsole } from '../../resources/js/chat-console.js';

function environment(t) {
    const win = new EventTarget();
    const doc = new EventTarget();
    const channels = new Map();
    const connectionListeners = new Map();
    let focused = true;
    doc.hidden = false;
    doc.hasFocus = () => focused;
    doc.querySelector = () => null;
    win.location = { search: '' };
    win.Livewire = { dispatch() {} };
    const echo = {
        private(name) {
            if (!channels.has(name)) channels.set(name, {
                listeners: new Map(),
                listen(event, callback) { this.listeners.set(event, callback); return this; },
                stopListening(event, callback) { if (this.listeners.get(event) === callback) this.listeners.delete(event); return this; },
            });
            return channels.get(name);
        },
        leave(name) { channels.delete(name); },
        connector: { pusher: { connection: {
            bind(event, handler) { connectionListeners.set(event, handler); },
            unbind(event, handler) { if (connectionListeners.get(event) === handler) connectionListeners.delete(event); },
        } } },
    };
    win.Echo = echo;
    win.ensurePlayerSaloonsEcho = () => echo;
    globalThis.window = win;
    globalThis.document = doc;
    const oldCustomEvent = globalThis.CustomEvent;
    globalThis.CustomEvent = class extends Event { constructor(name, options) { super(name); this.detail = options?.detail; } };
    t.mock.method(globalThis, 'setInterval', () => 1);
    t.mock.method(globalThis, 'clearInterval', () => {});
    t.mock.method(globalThis, 'setTimeout', () => 2);
    t.mock.method(globalThis, 'clearTimeout', () => {});
    t.after(() => { delete globalThis.window; delete globalThis.document; globalThis.CustomEvent = oldCustomEvent; });
    return { win, doc, channels, connectionListeners, focus(value) { focused = value; } };
}

const conversation = { uuid: 'direct-a', sender: 'Other Player', url: '/chat?conversation=direct-a' };
const summary = { unread_count: 1, conversations: [conversation] };
const event = { conversationUuid: 'direct-a', messageId: 11, alert: true };

function inbox(t) {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => summary }));
    return chatInbox({ url: '/inbox', userUuid: 'recipient', initial: { unread_count: 100 } });
}

function consoleState() {
    const state = chatConsole({ endpoints: { conversations: '/conversations', messages: '/messages' }, currentUserUuid: 'recipient', csrf: 'test' });
    state.$nextTick = async (callback) => callback?.();
    state.scrollToBottom = () => {};
    return state;
}

test('inbox loads authoritative count, caps badge at 99+, and does not replay old alerts', async (t) => {
    environment(t);
    const state = inbox(t);
    assert.equal(state.chatUnreadLabel, '99+');
    await state.init();
    assert.equal(state.chatUnreadCount, 1);
    assert.equal(state.chatAlert, null);
    state.destroy();
});

test('incoming direct alert links to exact chat and duplicate events do not replay it', async (t) => {
    environment(t);
    const state = inbox(t);
    await state.refreshInbox(event);
    assert.equal(state.chatAlert.url, conversation.url);
    state.chatAlert = null;
    await state.refreshInbox(event);
    assert.equal(state.chatAlert, null);
});

test('active, hidden, and unfocused conversations update badge without brief alerts', async (t) => {
    const env = environment(t);
    const state = inbox(t);
    env.win.playerActiveChat = 'direct-a';
    await state.refreshInbox(event);
    assert.equal(state.chatAlert, null);
    env.win.playerActiveChat = null;
    env.doc.hidden = true;
    await state.refreshInbox({ ...event, messageId: 12 });
    assert.equal(state.chatAlert, null);
    env.doc.hidden = false;
    env.focus(false);
    await state.refreshInbox({ ...event, messageId: 13 });
    assert.equal(state.chatUnreadCount, 1);
    assert.equal(state.chatAlert, null);
});

test('stale inbox requests cannot overwrite a newer read or mute state', async (t) => {
    environment(t);
    const state = inbox(t);
    let resolveFirst;
    let calls = 0;
    globalThis.fetch = async () => ({ ok: true, json: () => ++calls === 1 ? new Promise((resolve) => { resolveFirst = resolve; }) : Promise.resolve({ unread_count: 0, conversations: [] }) });
    const first = state.refreshInbox(event);
    await Promise.resolve();
    await state.refreshInbox();
    resolveFirst(summary);
    await first;
    assert.equal(state.chatUnreadCount, 0);
    assert.equal(state.chatAlert, null);
});

test('inbox navigation releases its listener and reconnect handler without removing other listeners', async (t) => {
    const env = environment(t);
    const state = inbox(t);
    await state.init();
    const channel = env.channels.get('user.recipient');
    channel.listen('.notification.received', () => {});
    assert.equal(env.connectionListeners.size, 1);
    state.destroy();
    assert.equal(channel.listeners.has('.chat.inbox.updated'), false);
    assert.equal(channel.listeners.has('.notification.received'), true);
    assert.equal(env.connectionListeners.size, 0);
});

test('chat deep links select and acknowledge the requested conversation after loading', async (t) => {
    const env = environment(t);
    env.win.location.search = '?conversation=direct-a';
    const state = consoleState();
    const requests = [];
    state.request = async (url, options) => {
        requests.push([url, options]);
        if (url === '/conversations') return { conversations: [{ uuid: 'global', type: 'global' }, { ...conversation, type: 'direct', is_unread: true }] };
        if (url.endsWith('/messages')) return { messages: [{ id: 11, uuid: 'message-a' }], conversation: { last_read_message_id: 0 } };
        return {};
    };
    await state.init();
    assert.equal(state.selected.uuid, 'direct-a');
    assert.equal(env.win.playerActiveChat, 'direct-a');
    assert.equal(JSON.parse(requests.find(([url]) => url.endsWith('/read'))[1].body).message_id, 11);
    assert.equal(state.unread['direct-a'], false);
    state.destroy();
    assert.equal(env.channels.size, 0);
    assert.equal(env.win.playerActiveChat, null);
});

test('hidden tabs and failed message loads do not acknowledge unread messages', async (t) => {
    const env = environment(t);
    env.doc.hidden = true;
    const state = consoleState();
    state.selected = { uuid: 'direct-a', is_unread: true };
    const requests = [];
    state.request = async (url) => { requests.push(url); return { messages: [{ id: 11, uuid: 'a' }] }; };
    await state.loadSelectedMessages();
    assert.equal(requests.some((url) => url.endsWith('/read')), false);
    assert.equal(state.selected.is_unread, true);
    env.doc.hidden = false;
    state.request = async () => { throw { message: 'Offline' }; };
    await state.loadSelectedMessages();
    assert.equal(state.error, 'Offline');
    assert.equal(state.selected.is_unread, true);
    assert.equal(env.win.playerActiveChat, null);
});

test('switching conversations ignores a slow message response for the previous selection', async (t) => {
    environment(t);
    const state = consoleState();
    let resolveFirst;
    state.request = async (url) => {
        if (url === '/messages/direct-a/messages') return new Promise((resolve) => { resolveFirst = resolve; });
        if (url.endsWith('/messages')) return { messages: [{ id: 20, uuid: 'b' }] };
        return {};
    };
    const first = state.selectConversation({ uuid: 'direct-a' });
    await state.selectConversation({ uuid: 'direct-b' });
    resolveFirst({ messages: [{ id: 11, uuid: 'a' }] });
    await first;
    assert.equal(state.selected.uuid, 'direct-b');
    assert.deepEqual(state.messages.map((message) => message.uuid), ['b']);
});

test('mute updates conversation preference without treating messages as read', async (t) => {
    environment(t);
    const state = consoleState();
    state.selected = { uuid: 'direct-a', is_unread: true, notifications_muted: false };
    state.conversations = [state.selected];
    let request;
    state.request = async (url, options) => {
        request = [url, options];
        return { conversation: { notifications_muted: true, is_unread: true } };
    };
    await state.toggleMute();
    assert.equal(request[0], '/messages/direct-a/mute');
    assert.deepEqual(JSON.parse(request[1].body), { muted: true });
    assert.equal(state.selected.notifications_muted, true);
    assert.equal(state.selected.is_unread, true);
});

test('an unavailable deep link never opens or marks a different conversation read', async (t) => {
    const env = environment(t);
    env.win.location.search = '?conversation=not-accessible';
    const state = consoleState();
    state.request = async (url) => { assert.equal(url, '/conversations'); return { conversations: [{ uuid: 'global' }] }; };
    await state.init();
    assert.equal(state.selected, null);
    assert.equal(state.error, 'This conversation is not available.');
    state.destroy();
});


test('a new message arriving during a snapshot load remains displayed and acknowledged', async (t) => {
    environment(t);
    const state = consoleState();
    state.selected = { uuid: 'direct-a' };
    let complete;
    const acknowledgements = [];
    state.request = async (url, options) => {
        if (url.endsWith('/messages')) return new Promise((resolve) => { complete = resolve; });
        acknowledgements.push(JSON.parse(options.body).message_id);
        return {};
    };
    const loading = state.loadSelectedMessages();
    state.pushMessage({ id: 12, uuid: 'arrived-live', user: { uuid: 'other' } });
    complete({ messages: [{ id: 11, uuid: 'snapshot' }] });
    await loading;
    assert.deepEqual(state.messages.map((message) => message.id), [11, 12]);
    assert.deepEqual(acknowledgements, [12]);
});

test('a read acknowledgement keeps an unread indicator when the server has a newer unseen message', async (t) => {
    environment(t);
    const state = consoleState();
    state.loadingMessages = false;
    state.selected = { uuid: 'direct-a', is_unread: true };
    state.conversations = [state.selected];
    state.messages = [{ id: 11, uuid: 'loaded' }];
    state.request = async () => ({ conversation: { is_unread: true } });
    await state.acknowledgeCurrent();
    assert.equal(state.unread['direct-a'], true);
    assert.equal(state.selected.is_unread, true);
});

test('muting or reading a conversation dismisses an already visible brief alert', async (t) => {
    environment(t);
    const state = inbox(t);
    await state.refreshInbox(event);
    assert.equal(state.chatAlert.uuid, 'direct-a');
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ unread_count: 0, conversations: [] }) });
    await state.refreshInbox();
    assert.equal(state.chatAlert, null);
});
