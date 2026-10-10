let previousContent;
let messages = {};

export function interfaceText(key, values = {}, root = globalThis.document) {
    const content = root?.querySelector('[data-player-interface-messages]')?.textContent ?? '{}';
    if (content !== previousContent) {
        try { messages = JSON.parse(content); } catch { messages = {}; }
        previousContent = content;
    }
    const text = typeof messages[key] === 'string' ? messages[key] : key;
    return text.replace(/:([a-zA-Z][a-zA-Z0-9_]*)/g, (token, name) => values[name] === undefined ? token : String(values[name]));
}
